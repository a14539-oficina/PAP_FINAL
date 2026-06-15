<?php
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id      = $_SESSION['user_id'];
$user_role    = $_SESSION['user_role'] ?? '4';

$isAdminPrincipal = ($user_role == 0); // FIX: loose == suporta string '0' e int 0

if ($user_club_id <= 0 && !$isAdminPrincipal) {
  die("Erro: Utilizador sem clube associado. Contacte o administrador.");
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$porPagina = isset($_GET['entries']) ? max(10, min(50, (int)$_GET['entries'])) : 10;
$pagina    = isset($_GET['page'])    ? max(1, (int)$_GET['page'])              : 1;
$offset    = ($pagina - 1) * $porPagina;

// Contar total de equipas
if ($isAdminPrincipal) {
  $sqlCount  = "SELECT COUNT(*) as total FROM teams";
  $stmtCount = $conn->prepare($sqlCount);
} else {
  $sqlCount  = "SELECT COUNT(*) as total FROM teams WHERE club_id = ?";
  $stmtCount = $conn->prepare($sqlCount);
  $stmtCount->bind_param("i", $user_club_id);
}
$stmtCount->execute();
$totalEquipas = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalEquipas / $porPagina);
$stmtCount->close();

// Buscar equipas
$sql = "SELECT t.id, t.nome, t.club_id, c.nome AS clube_nome, t.escaloes, t.ativo, t.logo, c.logo AS clube_logo
        FROM teams t
        LEFT JOIN clubs c ON t.club_id = c.id";

if ($isAdminPrincipal) {
  $sql .= " ORDER BY c.nome ASC, t.escaloes ASC LIMIT ? OFFSET ?";
  $stmtTeams = $conn->prepare($sql);
  $stmtTeams->bind_param("ii", $porPagina, $offset);
} else {
  $sql .= " WHERE t.club_id = ? ORDER BY t.escaloes ASC LIMIT ? OFFSET ?";
  $stmtTeams = $conn->prepare($sql);
  $stmtTeams->bind_param("iii", $user_club_id, $porPagina, $offset);
}

$stmtTeams->execute();
$res = $stmtTeams->get_result();

$podeEditar = ($user_role === '2' || $user_role === '1' || $user_role === '0');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Equipas - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #f8fafc;
      color: #1e293b;
    }

    .main-content {
      margin-left: 240px;
      padding: 24px;
      width: calc(100% - 240px);
      box-sizing: border-box;
      min-height: 100vh;
      overflow-x: hidden;
      transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease;
    }

    .content-wrapper { max-width: 1600px; margin: 0 auto; width: 100%; }

    .import-list-header {
      background: #fff;
      padding: 1.5rem 2rem;
      border-radius: 16px;
      margin-bottom: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
    }

    .import-list-header h1 {
      font-size: 1.75rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .import-list-header h1 i { font-size: 2rem; color: #3b82f6; }

    .header-left { display: flex; align-items: center; gap: 1.5rem; }

    .search-wrapper-header {
      position: relative;
      min-width: 320px;
      flex: 1;
      max-width: 450px;
    }

    .search-wrapper-header i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 1.1rem;
    }

    .search-input-header {
      width: 100%;
      padding: 0.875rem 1.25rem 0.875rem 3rem;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 0.9375rem;
      transition: all 0.2s;
      background: #f8fafc;
    }

    .search-input-header:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
      background: white;
    }

    .header-actions { display: flex; gap: 1rem; align-items: center; }

    .btn-push-all {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      border: none;
      padding: 0.875rem 1.75rem;
      border-radius: 12px;
      font-size: 0.9375rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s;
      text-decoration: none;
      white-space: nowrap;
      box-shadow: 0 4px 12px rgba(59,130,246,0.2);
    }

    .btn-push-all:hover {
      transform: translateY(-2px);
      color: white;
      box-shadow: 0 6px 20px rgba(59,130,246,0.35);
    }

    .pagination-container {
      background: white;
      border: 2px solid #e2e8f0;
      border-radius: 16px;
      padding: 1.5rem 2rem;
      margin-bottom: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .pagination-info-section {
      display: flex;
      align-items: center;
      gap: 2.5rem;
      flex-wrap: wrap;
    }

    .pagination-info { font-size: 0.9375rem; color: #475569; font-weight: 500; }

    .entries-control {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.9375rem;
      color: #64748b;
    }

    .entries-select {
      background: white;
      border: 2px solid #e2e8f0;
      padding: 0.5rem 2.5rem 0.5rem 0.875rem;
      border-radius: 8px;
      font-size: 0.9375rem;
      color: #334155;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L3 5h8z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.875rem center;
      min-width: 70px;
      font-weight: 500;
      transition: all 0.2s;
    }

    .entries-select:hover { border-color: #cbd5e1; }

    .pagination-controls { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    .pagination-btn {
      padding: 0.625rem 0.875rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      background: white;
      color: #64748b;
      text-decoration: none;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 44px;
      font-size: 0.9375rem;
      font-weight: 500;
    }

    .pagination-btn:hover:not(.disabled) {
      background: #f1f5f9;
      color: #334155;
      border-color: #cbd5e1;
      transform: translateY(-1px);
    }

    .pagination-btn.active { background: #3b82f6; border-color: #3b82f6; color: white; font-weight: 700; }
    .pagination-btn.disabled { opacity: 0.4; pointer-events: none; }

    .teams-cards-container {
  width: 100%;
  display: grid;
  gap: 0.75rem;
}

    .team-card {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 1rem 1.5rem;
      display: grid;
      grid-template-columns: 60px 200px 1fr auto;
      align-items: center;
      gap: 1.5rem;
      transition: all 0.2s;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .team-card:hover {
      border-color: #cbd5e1;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transform: translateY(-1px);
    }

    .team-avatar {
      width: 60px;
      height: 60px;
      border-radius: 10px;
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
      flex-shrink: 0;
      overflow: hidden;
    }

    .team-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .team-name {
      font-size: 1rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .team-info {
      display: grid;
      grid-template-columns: repeat(2, minmax(120px, 1fr));
      align-items: center;
      gap: 1.5rem;
    }

    .team-detail { display: flex; align-items: center; gap: 0.5rem; }
    .team-detail i { font-size: 1rem; color: #64748b; flex-shrink: 0; }
    .team-detail span { font-size: 0.875rem; color: #475569; font-weight: 500; white-space: nowrap; }

    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.375rem 0.75rem;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .status-badge.active  { background: #dcfce7; color: #166534; }
    .status-badge.inactive { background: #f3f4f6; color: #6b7280; }

    .team-actions {
      display: flex;
      gap: 0.5rem;
      align-items: center;
      flex-shrink: 0;
      margin-left: auto;
    }

    .action-btn {
      padding: 0.5rem 0.75rem;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: white;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 38px;
      height: 38px;
      text-decoration: none;
      font-size: 1rem;
    }

    .action-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

    .action-btn.toggle-active {
      padding: 0.5rem 1rem;
      min-width: auto;
      gap: 0.5rem;
      font-weight: 600;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .toggle-active.is-active   { background: #dcfce7 !important; border-color: #22c55e !important; color: #166534 !important; }
    .toggle-active.is-inactive { background: #fee2e2 !important; border-color: #ef4444 !important; color: #991b1b !important; }

    .action-btn.edit:hover   { background: #eff6ff; border-color: #3b82f6; color: #3b82f6; }
    .action-btn.delete:hover { background: #fef2f2; border-color: #ef4444; color: #ef4444; }

    .empty-state {
      background: white;
      border: 2px dashed #cbd5e1;
      border-radius: 16px;
      padding: 4rem 2rem;
      text-align: center;
      margin-top: 2rem;
    }

    .empty-state i    { font-size: 4rem; color: #cbd5e1; margin-bottom: 1.5rem; }
    .empty-state h3   { font-size: 1.5rem; font-weight: 700; color: #334155; margin-bottom: 0.75rem; }
    .empty-state p    { font-size: 1rem; color: #64748b; margin-bottom: 2rem; }

    .alert {
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      border: 2px solid;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .alert i { font-size: 1.25rem; }
    .alert-success { background: #dcfce7; border-color: #86efac; color: #166534; }
    .alert-danger  { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

    /* clube badge para superadmin */
    .clube-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      background: #eff6ff;
      color: #2563eb;
      padding: 0.25rem 0.625rem;
      border-radius: 6px;
      font-size: 0.8125rem;
      font-weight: 600;
    }

    @media (max-width: 1400px) {
      .team-card { grid-template-columns: 60px 180px 1fr auto; }
      .team-info { grid-template-columns: repeat(2, minmax(100px, 1fr)); gap: 1rem; }
    }

    @media (max-width: 1200px) {
      .main-content { margin-left: 0; width: 100%; }
      .team-card { grid-template-columns: 60px 150px 1fr auto; }
      .team-info { grid-template-columns: 1fr; gap: 0.75rem; }
    }

    @media (max-width: 1024px) {
      .main-content { margin-left: 0; margin-top: 0; width: 100%; padding: 16px; }
    }

    @media (max-width: 768px) {
      .import-list-header { padding: 1.25rem; }
      .team-card { display: block; padding: 1.25rem; }
      .team-avatar { float: left; margin-right: 1rem; margin-bottom: 1rem; width: 56px; height: 56px; font-size: 1.25rem; }
      .team-name { display: flex; align-items: center; height: 56px; text-align: left; font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; line-height: 1.2; }
      .team-info { clear: both; display: block; width: 100%; }
      .team-detail { display: flex; justify-content: flex-start; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem; text-align: left; }
      .team-actions { display: flex; justify-content: flex-start; gap: 0.625rem; margin-top: 1rem; width: 100%; }
      .action-btn { min-width: 44px; height: 44px; }
      .action-btn.toggle-active { flex: 1; }
      .pagination-container { flex-direction: column; align-items: stretch; padding: 1rem; }
      .pagination-info-section { flex-direction: column; align-items: stretch; gap: 1rem; }
      .pagination-controls { justify-content: center; }
      .search-wrapper-header { min-width: 100%; max-width: 100%; }
      .header-actions { width: 100%; flex-direction: column; }
      .btn-push-all { width: 100%; justify-content: center; }
      .import-list-header h1 { font-size: 1.5rem; }
    }

    @media (max-width: 480px) {
      .team-card { padding: 1rem; }
      .team-avatar { width: 52px; height: 52px; font-size: 1.125rem; }
      .team-name { height: 52px; font-size: 1rem; }
      .action-btn { min-width: 40px; height: 40px; font-size: 0.9375rem; }
      .action-btn.toggle-active { padding: 0.625rem 1rem; font-size: 0.8125rem; }
      .status-badge { font-size: 0.6875rem; padding: 0.375rem 0.625rem; }
      .import-list-header { padding: 1rem; }
      .pagination-container { padding: 0.875rem; }
    }
  </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
  <div class="import-list-header">
    <div class="header-left">
      <h1>
        <i class="bi bi-shield-fill-check"></i>
        <?= $isAdminPrincipal ? 'Todas as Equipas' : 'Equipas - Meu Clube' ?>
      </h1>
    </div>
    <div class="header-actions">
      <div class="search-wrapper-header">
        <i class="bi bi-search"></i>
        <input type="search" class="search-input-header" id="searchBar" placeholder="Pesquisar equipa...">
      </div>
      <?php if ($podeEditar): ?>
      <a href="adicionar_equipa.php" class="btn-push-all">
        <i class="bi bi-plus-circle-fill"></i>
        Adicionar Equipa
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill"></i>
      <span><?= h($_SESSION['success']) ?></span>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span><?= h($_SESSION['error']) ?></span>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if ($totalEquipas === 0): ?>
    <div class="empty-state">
      <i class="bi bi-shield-plus"></i>
      <h3>Nenhuma equipa encontrada</h3>
      <p>
        <?php if ($podeEditar): ?>
          Comece a organizar o seu clube criando a primeira equipa
        <?php else: ?>
          Não existem equipas no seu clube
        <?php endif; ?>
      </p>
      <?php if ($podeEditar): ?>
      <a href="adicionar_equipa.php" class="btn-push-all">
        <i class="bi bi-plus-circle-fill"></i>
        Adicionar Equipa
      </a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div class="teams-cards-container">
      <?php while ($r = $res->fetch_assoc()):
        $display_name = !empty($r['nome']) ? $r['nome'] : $r['clube_nome'] . ' - ' . $r['escaloes'];

        $logo_path = '';
        if (!empty($r['logo']) && file_exists('logos/' . $r['logo'])) {
          $logo_path = 'logos/' . $r['logo'];
        } elseif (!empty($r['clube_logo']) && file_exists('../../uploads/logos/' . $r['clube_logo'])) {
          $logo_path = '../../uploads/logos/' . $r['clube_logo'];
        }
      ?>
      <div class="team-card equipa-row">
        <div class="team-avatar">
          <?php if (!empty($logo_path)): ?>
            <img src="<?= h($logo_path) ?>" alt="<?= h($display_name) ?>">
          <?php else: ?>
            <i class="bi bi-shield-fill"></i>
          <?php endif; ?>
        </div>

        <h3 class="team-name"><?= h($display_name) ?></h3>

        <div class="team-info">
          <div class="team-detail">
            <i class="bi bi-award"></i>
            <span><?= h($r['escaloes']) ?></span>
          </div>
          <?php if ($isAdminPrincipal && !empty($r['clube_nome'])): ?>
          <div class="team-detail">
            <i class="bi bi-building-fill"></i>
            <span class="clube-badge"><?= h($r['clube_nome']) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="team-actions">
          <a href="ver_jogadores.php?team_id=<?= (int)$r['id'] ?>" class="action-btn edit" title="Ver jogadores">
            <i class="bi bi-people-fill"></i>
          </a>

          <?php if ($podeEditar): ?>
          <button
            class="action-btn toggle-active <?= $r['ativo'] ? 'is-active' : 'is-inactive' ?>"
            data-id="<?= (int)$r['id'] ?>"
            data-nome="<?= h($display_name) ?>"
            data-ativo="<?= $r['ativo'] ? '1' : '0' ?>"
            title="<?= $r['ativo'] ? 'Desativar equipa' : 'Ativar equipa' ?>">
            <i class="bi bi-<?= $r['ativo'] ? 'toggle-on' : 'toggle-off' ?>"></i>
            <span><?= $r['ativo'] ? 'Ativa' : 'Inativa' ?></span>
          </button>

          <?php if ($isAdminPrincipal): ?>
          <button class="action-btn delete" title="Eliminar"
                  data-id="<?= (int)$r['id'] ?>"
                  data-nome="<?= h($display_name) ?>">
            <i class="bi bi-trash-fill"></i>
          </button>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <div class="pagination-container" style="margin-top: 1.5rem;">
      <div class="pagination-info-section">
        <div class="pagination-info">
          Página <strong><?= $pagina ?></strong> de <strong><?= $totalPaginas ?></strong> • Total: <strong><?= $totalEquipas ?></strong> equipas
        </div>
        <div class="entries-control">
          <span>Mostrar</span>
          <select class="entries-select" onchange="window.location.href='?entries='+this.value+'&page=1'">
            <option value="10" <?= $porPagina == 10 ? 'selected' : '' ?>>10</option>
            <option value="25" <?= $porPagina == 25 ? 'selected' : '' ?>>25</option>
            <option value="50" <?= $porPagina == 50 ? 'selected' : '' ?>>50</option>
          </select>
          <span>por página</span>
        </div>
      </div>

      <div class="pagination-controls">
        <a href="?page=1&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == 1 ? 'disabled' : '' ?>" title="Primeira página">
          <i class="bi bi-chevron-bar-left"></i>
        </a>
        <a href="?page=<?= max(1, $pagina - 1) ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == 1 ? 'disabled' : '' ?>" title="Página anterior">
          <i class="bi bi-chevron-left"></i>
        </a>
        <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
          <a href="?page=<?= $i ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPaginas, $pagina + 1) ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == $totalPaginas ? 'disabled' : '' ?>" title="Próxima página">
          <i class="bi bi-chevron-right"></i>
        </a>
        <a href="?page=<?= $totalPaginas ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == $totalPaginas ? 'disabled' : '' ?>" title="Última página">
          <i class="bi bi-chevron-bar-right"></i>
        </a>
      </div>
    </div>

  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../toast.js"></script>
<script>
document.getElementById('searchBar')?.addEventListener('input', function() {
  const termo = this.value.toLowerCase().trim();
  document.querySelectorAll('.equipa-row').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(termo) ? '' : 'none';
  });
});

document.getElementById('searchBar')?.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') e.preventDefault();
});

document.querySelectorAll('.action-btn.delete').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const equipaId   = this.getAttribute('data-id');
    const equipaNome = this.getAttribute('data-nome');

    toast.confirm({
      type: 'warning',
      title: 'Eliminar Equipa?',
      message: `Tem certeza que deseja eliminar "${equipaNome}"? Esta ação não pode ser desfeita.`,
      confirmText: 'Eliminar',
      cancelText: 'Cancelar',
      onConfirm: () => eliminarEquipa(equipaId, equipaNome)
    });
  });
});

function eliminarEquipa(id, nome) {
  toast.info('A eliminar...', 'Aguarde um momento');

  fetch('remover.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${encodeURIComponent(id)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      toast.success('Eliminado!', `Equipa "${nome}" eliminada com sucesso`);
      setTimeout(() => window.location.reload(), 1500);
    } else {
      toast.error('Erro!', data.message || 'Não foi possível eliminar a equipa');
    }
  })
  .catch(() => toast.error('Erro!', 'Erro ao eliminar equipa. Tente novamente.'));
}

document.querySelectorAll('.toggle-active').forEach(btn => {
  btn.addEventListener('click', function() {
    const id   = this.dataset.id;
    const ativo = this.dataset.ativo;

    fetch('toggle_equipa.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}&ativo=${ativo}`
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        toast.error("Erro", "Não foi possível alterar o estado");
        return;
      }

      const novo  = data.ativo;
      const icon  = this.querySelector("i");
      const text  = this.querySelector("span");

      this.dataset.ativo = novo;

      if (novo == 1) {
        this.classList.replace("is-inactive", "is-active");
        icon.className  = "bi bi-toggle-on";
        text.textContent = "Ativa";
        this.title = "Desativar equipa";
        toast.success("Feito!", "Equipa ativada ✔️");
      } else {
        this.classList.replace("is-active", "is-inactive");
        icon.className  = "bi bi-toggle-off";
        text.textContent = "Inativa";
        this.title = "Ativar equipa";
        toast.info("Equipa desativada");
      }
    })
    .catch(err => {
      console.error(err);
      toast.error("Erro", "Falha ao comunicar com servidor");
    });
  });
});
</script>
</body>
</html>