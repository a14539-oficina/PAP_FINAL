<?php
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id      = $_SESSION['user_id'];
$user_club_id = isset($_SESSION['club_id'])   ? intval($_SESSION['club_id'])   : 0;
$user_role    = $_SESSION['user_role']        ?? '';
$user_team_id = isset($_SESSION['team_id'])   ? intval($_SESSION['team_id'])   : 0;

if ($user_club_id <= 0 && $user_id != 7) {
    die("Erro: Utilizador sem clube associado. Contacte o administrador.");
}

$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);
$isAdmin          = in_array($user_role, ['admin', '1']);
$isDiretor        = in_array($user_role, ['diretor', '2']);
$isTreinador      = in_array($user_role, ['treinador', '3']);
$isJogador        = in_array($user_role, ['jogador', '4']);

// SuperAdmin não acede
if ($user_role === '0') {
    $_SESSION['erro'] = "SuperAdmins não têm acesso a esta área.";
    header("Location: ../../dashboard.php");
    exit;
}

$pode_editar     = in_array($user_role, ['1']);
$pode_visualizar = in_array($user_role, ['1', '2', '3', '4']);

if (!$pode_visualizar) {
    $_SESSION['erro'] = "Não tem permissão para aceder a esta página.";
    header("Location: ../../dashboard.php");
    exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$porPagina = isset($_GET['entries']) ? max(10, min(50, (int)$_GET['entries'])) : 10;
$pagina    = isset($_GET['page'])    ? max(1, (int)$_GET['page'])              : 1;
$offset    = ($pagina - 1) * $porPagina;

// ===============================
// CONTAR TREINOS
// ===============================
if ($isAdminPrincipal) {
    $sqlCount  = "SELECT COUNT(*) as total FROM training_sessions t LEFT JOIN teams e ON t.team_id = e.id";
    $stmtCount = $conn->prepare($sqlCount);

} elseif (($isTreinador || $isJogador) && $user_team_id > 0) {
    // Treinador e Jogador: só treinos da sua equipa
    $sqlCount  = "SELECT COUNT(*) as total FROM training_sessions t WHERE t.team_id = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("i", $user_team_id);

} else {
    // Admin/Diretor: todos os treinos do clube
    $sqlCount  = "SELECT COUNT(*) as total FROM training_sessions t LEFT JOIN teams e ON t.team_id = e.id WHERE e.club_id = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("i", $user_club_id);
}

$stmtCount->execute();
$totalTreinos = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil(max(1, $totalTreinos) / $porPagina);
$stmtCount->close();

// ===============================
// BUSCAR TREINOS
// ===============================
$sql = "SELECT t.*, e.nome as equipa, c.nome as clube_nome
        FROM training_sessions t
        LEFT JOIN teams e ON t.team_id = e.id
        LEFT JOIN clubs c ON e.club_id = c.id";

if ($isAdminPrincipal) {
    $sql .= " ORDER BY t.data_treino DESC LIMIT ? OFFSET ?";
    $stmtTreinos = $conn->prepare($sql);
    $stmtTreinos->bind_param("ii", $porPagina, $offset);

} elseif (($isTreinador || $isJogador) && $user_team_id > 0) {
    // Treinador e Jogador: filtra pela sua equipa
    $sql .= " WHERE t.team_id = ? ORDER BY t.data_treino DESC LIMIT ? OFFSET ?";
    $stmtTreinos = $conn->prepare($sql);
    $stmtTreinos->bind_param("iii", $user_team_id, $porPagina, $offset);

} else {
    // Admin/Diretor: filtra pelo clube
    $sql .= " WHERE e.club_id = ? ORDER BY t.data_treino DESC LIMIT ? OFFSET ?";
    $stmtTreinos = $conn->prepare($sql);
    $stmtTreinos->bind_param("iii", $user_club_id, $porPagina, $offset);
}

$stmtTreinos->execute();
$res = $stmtTreinos->get_result();

$dataAtual = new DateTime();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Treinos - SportGes</title>
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
      display: flex;
      flex-direction: column;
      transition: margin-left 0.3s ease, width 0.3s ease;
    }

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

    .trainings-cards-container {
      width: 100%;
      margin: 0 auto 1.5rem auto;
      display: grid;
      gap: 1rem;
    }

    .training-card {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      width: 100%;
      box-sizing: border-box;
      overflow: visible;
      transition: all 0.2s;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      position: relative;
    }

    .training-card:hover {
      border-color: #cbd5e1;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transform: translateY(-1px);
    }

    .training-card.concluido {
      background: #f8fafb;
      border-color: #cbd5e1;
      opacity: 0.85;
    }

    .training-card.concluido .equipa-name { color: #64748b; }
    .training-card.concluido .foco-badge  { background: #e2e8f0; color: #64748b; }
    .training-card.concluido .training-date { color: #94a3b8; }
    .training-card.concluido .training-date i { color: #94a3b8; }

    .training-info {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex: 1;
      min-width: 0;
      overflow: hidden;
      padding-right: 1rem;
    }

    .training-detail {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-shrink: 0;
      white-space: nowrap;
    }

    .training-detail i    { font-size: 1rem; color: #64748b; flex-shrink: 0; }
    .training-detail span { font-size: 0.875rem; color: #475569; font-weight: 500; }

    .training-date { color: #64748b; display: flex; align-items: center; gap: 8px; font-size: 0.875rem; }
    .training-date i { color: #3b82f6; }

    .equipa-name { font-weight: 600; color: #3b82f6; font-size: 0.875rem; }

    .foco-badge {
      background: #dbeafe;
      padding: 6px 12px;
      border-radius: 20px;
      display: inline-block;
      color: #3b82f6;
      font-size: 13px;
      font-weight: 500;
    }

    .training-actions {
      display: flex;
      gap: 0.5rem;
      align-items: center;
      flex-shrink: 0;
      margin-left: auto;
      z-index: 1;
      position: relative;
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

    .action-btn.edit {
      background: #3b82f6; border-color: #3b82f6; color: white;
      padding: 0.5rem 1rem; min-width: auto; gap: 0.5rem;
      font-weight: 500; font-size: 0.875rem;
    }

    .action-btn.edit:hover { background: #2563eb; border-color: #2563eb; }

    .action-btn.view {
      background: #10b981; border-color: #10b981; color: white;
      padding: 0.5rem 1rem; min-width: auto; gap: 0.5rem;
      font-weight: 500; font-size: 0.875rem;
    }

    .action-btn.view:hover { background: #059669; border-color: #059669; }

    .action-btn.delete { background: white; border-color: #e5e7eb; color: #64748b; }
    .action-btn.delete:hover { background: #fef2f2; border-color: #ef4444; color: #ef4444; }

    /* Modal */
    .modal-backdrop {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      animation: fadeIn 0.2s;
    }

    .modal-backdrop.show { display: flex; align-items: center; justify-content: center; }

    .modal-content-custom {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      max-width: 600px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      animation: slideUp 0.3s;
    }

    @keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    .modal-header-custom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e2e8f0;
    }

    .modal-header-custom h3 {
      font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;
      display: flex; align-items: center; gap: 0.75rem;
    }

    .modal-header-custom h3 i { color: #3b82f6; font-size: 1.75rem; }

    .modal-close {
      background: #f1f5f9; border: none; border-radius: 8px;
      width: 36px; height: 36px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all 0.2s;
    }

    .modal-close:hover { background: #e2e8f0; transform: rotate(90deg); }
    .modal-close i { font-size: 1.5rem; color: #64748b; }

    .modal-body-custom { color: #475569; font-size: 1rem; line-height: 1.6; white-space: pre-wrap; }
    .observacao-empty  { text-align: center; padding: 2rem; color: #94a3b8; font-style: italic; }

    /* Empty State */
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

    /* Alerts */
    .alert {
      border-radius: 12px; padding: 1rem 1.5rem;
      margin-bottom: 1.5rem; border: 2px solid;
      display: flex; align-items: center; gap: 0.75rem;
    }

    .alert i         { font-size: 1.25rem; }
    .alert-success   { background: #dcfce7; border-color: #86efac; color: #166534; }
    .alert-danger    { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

    /* Responsive */
    @media (max-width: 1024px) {
      .main-content { margin-left: 0; width: 100%; padding: 16px; }
    }

    @media (max-width: 768px) {
      .import-list-header { padding: 1.25rem; }
      .training-card { flex-direction: column; align-items: stretch; padding: 1.25rem; gap: 1rem; }
      .training-info { flex-direction: column; align-items: flex-start; gap: 0.75rem; padding-right: 0; width: 100%; }
      .training-detail { display: flex; align-items: center; gap: 0.5rem; width: 100%; }
      .training-actions { display: flex; justify-content: flex-start; gap: 0.625rem; width: 100%; margin-left: 0; }
      .action-btn { min-width: 44px; height: 44px; flex: 1; }
      .action-btn.edit span, .action-btn.view span { display: none; }
      .action-btn.edit, .action-btn.view { padding: 0.5rem; min-width: 38px; }
      .pagination-container { flex-direction: column; align-items: stretch; padding: 1rem; }
      .pagination-info-section { flex-direction: column; align-items: stretch; gap: 1rem; }
      .pagination-controls { justify-content: center; }
      .search-wrapper-header { min-width: 100%; max-width: 100%; }
      .header-actions { width: 100%; flex-direction: column; }
      .btn-push-all { width: 100%; justify-content: center; }
      .import-list-header h1 { font-size: 1.5rem; }
      .modal-content-custom { width: 95%; padding: 1.5rem; }
    }

    @media (max-width: 480px) {
      .training-card { padding: 1rem; }
      .action-btn { min-width: 40px; height: 40px; font-size: 0.9375rem; }
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
        <i class="bi bi-clipboard-check-fill"></i>
        <?php if (($isTreinador || $isJogador) && $user_team_id > 0): ?>
          Treinos - Minha Equipa
        <?php else: ?>
          Treinos - Meu Clube
        <?php endif; ?>
      </h1>
    </div>
    <div class="header-actions">
      <div class="search-wrapper-header">
        <i class="bi bi-search"></i>
        <input type="search" class="search-input-header" id="searchBar" placeholder="Pesquisar treino...">
      </div>
      <?php if ($pode_editar): ?>
        <a href="criar_treino.php" class="btn-push-all">
          <i class="bi bi-plus-circle-fill"></i> Novo Treino
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

  <?php if ($totalTreinos === 0): ?>
    <div class="empty-state">
      <i class="bi bi-clipboard-plus"></i>
      <h3>Nenhum treino encontrado</h3>
      <p>
        <?php if (($isTreinador || $isJogador) && $user_team_id > 0): ?>
          Não existem treinos registados para a sua equipa
        <?php elseif ($pode_editar): ?>
          Comece a organizar os treinos do seu clube criando o primeiro
        <?php else: ?>
          Não existem treinos registados no seu clube
        <?php endif; ?>
      </p>
      <?php if ($pode_editar): ?>
        <a href="criar_treino.php" class="btn-push-all">
          <i class="bi bi-plus-circle-fill"></i> Novo Treino
        </a>
      <?php endif; ?>
    </div>

  <?php else: ?>

    <div class="trainings-cards-container">
      <?php while ($treino = $res->fetch_assoc()):
        $dataTreino  = new DateTime($treino['data_treino']);
        $isConcluido = $dataTreino < $dataAtual;
      ?>
        <div class="training-card <?= $isConcluido ? 'concluido' : '' ?>">

          <div class="training-info">
            <div class="training-detail">
              <i class="bi bi-calendar-event"></i>
              <span class="training-date"><?= $dataTreino->format('d/m/Y H:i') ?></span>
            </div>
            <div class="training-detail">
              <i class="bi bi-people-fill"></i>
              <span class="equipa-name"><?= h($treino['equipa'] ?? 'Sem equipa') ?></span>
            </div>
          </div>

          <div class="training-actions" style="margin-left:auto;">
            <?php if (!empty($treino['observacoes'])): ?>
              <button class="action-btn"
                      onclick="verObservacoes('<?= h($treino['observacoes']) ?>', '<?= h($treino['equipa'] ?? 'Sem equipa') ?>')"
                      title="Ver observações">
                <i class="bi bi-eye-fill"></i>
              </button>
            <?php endif; ?>

            <?php if ($pode_editar): ?>
              <a href="editar.php?id=<?= $treino['id'] ?>" class="action-btn edit" title="Editar treino">
                <i class="bi bi-pencil-fill"></i> <span>Editar</span>
              </a>
              <a href="ver.php?id=<?= $treino['id'] ?>" class="action-btn view" title="Ver detalhes">
                <i class="bi bi-eye-fill"></i> <span>Ver</span>
              </a>
              <?php if ($isAdmin || $isDiretor || $isTreinador): ?>
                <button class="action-btn delete"
                        title="Eliminar"
                        data-id="<?= $treino['id'] ?>"
                        data-nome="<?= h($treino['equipa'] ?? 'Sem equipa') ?>">
                  <i class="bi bi-trash-fill"></i>
                </button>
              <?php endif; ?>
            <?php else: ?>
              <a href="ver.php?id=<?= $treino['id'] ?>" class="action-btn view" title="Ver detalhes">
                <i class="bi bi-eye-fill"></i> <span>Ver</span>
              </a>
            <?php endif; ?>
          </div>

        </div>
      <?php endwhile; ?>
    </div>

    <!-- Paginação -->
    <div class="pagination-container">
      <div class="pagination-info-section">
        <div class="pagination-info">
          Página <strong><?= $pagina ?></strong> de <strong><?= $totalPaginas ?></strong>
          &bull; Total: <strong><?= $totalTreinos ?></strong> treinos
        </div>
        <div class="entries-control">
          <span>Mostrar</span>
          <select class="entries-select" onchange="window.location.href='?entries='+this.value+'&page=1'">
            <option value="10" <?= $porPagina==10?'selected':'' ?>>10</option>
            <option value="25" <?= $porPagina==25?'selected':'' ?>>25</option>
            <option value="50" <?= $porPagina==50?'selected':'' ?>>50</option>
          </select>
          <span>por página</span>
        </div>
      </div>

      <div class="pagination-controls">
        <a href="?page=1&entries=<?= $porPagina ?>"
           class="pagination-btn <?= $pagina==1?'disabled':'' ?>">
          <i class="bi bi-chevron-bar-left"></i>
        </a>
        <a href="?page=<?= max(1,$pagina-1) ?>&entries=<?= $porPagina ?>"
           class="pagination-btn <?= $pagina==1?'disabled':'' ?>">
          <i class="bi bi-chevron-left"></i>
        </a>

        <?php for ($i = max(1,$pagina-2); $i <= min($totalPaginas,$pagina+2); $i++): ?>
          <a href="?page=<?= $i ?>&entries=<?= $porPagina ?>"
             class="pagination-btn <?= $i==$pagina?'active':'' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <a href="?page=<?= min($totalPaginas,$pagina+1) ?>&entries=<?= $porPagina ?>"
           class="pagination-btn <?= $pagina==$totalPaginas?'disabled':'' ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
        <a href="?page=<?= $totalPaginas ?>&entries=<?= $porPagina ?>"
           class="pagination-btn <?= $pagina==$totalPaginas?'disabled':'' ?>">
          <i class="bi bi-chevron-bar-right"></i>
        </a>
      </div>
    </div>

  <?php endif; ?>
</div><!-- FECHA main-content -->


<!-- Modal Observações -->
<div class="modal-backdrop" id="observacoesModal">
  <div class="modal-content-custom">
    <div class="modal-header-custom">
      <h3>
        <i class="bi bi-chat-text-fill"></i>
        Observações — <span id="modalEquipaName"></span>
      </h3>
      <button class="modal-close" onclick="fecharModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-body-custom" id="observacoesContent"></div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../toast.js"></script>

<script>
  // Pesquisa
  document.getElementById('searchBar')?.addEventListener('input', function () {
    const termo = this.value.toLowerCase().trim();
    document.querySelectorAll('.training-card').forEach(card => {
      card.style.display = card.textContent.toLowerCase().includes(termo) ? '' : 'none';
    });
  });

  document.getElementById('searchBar')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') e.preventDefault();
  });

  // Modal observações
  function verObservacoes(observacoes, equipa) {
    const modal    = document.getElementById('observacoesModal');
    const content  = document.getElementById('observacoesContent');
    const equipaEl = document.getElementById('modalEquipaName');
    equipaEl.textContent = equipa;
    if (observacoes?.trim()) {
      content.textContent = observacoes;
    } else {
      content.innerHTML = '<div class="observacao-empty">Sem observações registadas</div>';
    }
    modal.classList.add('show');
  }

  function fecharModal() {
    document.getElementById('observacoesModal').classList.remove('show');
  }

  document.getElementById('observacoesModal')?.addEventListener('click', function (e) {
    if (e.target === this) fecharModal();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharModal();
  });

  // Eliminar
  document.querySelectorAll('.action-btn.delete').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      const treinoId  = this.getAttribute('data-id');
      const equipaNome = this.getAttribute('data-nome');
      toast.confirm({
        type: 'warning',
        title: 'Eliminar Treino?',
        message: `Tem certeza que deseja eliminar o treino da equipa "${equipaNome}"? Esta ação não pode ser desfeita.`,
        confirmText: 'Eliminar',
        cancelText:  'Cancelar',
        onConfirm: () => eliminarTreino(treinoId, equipaNome)
      });
    });
  });

  function eliminarTreino(id, nome) {
    toast.info('A eliminar...', 'Aguarde um momento');
    fetch('eliminar_treino.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(id)}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        toast.success('Eliminado!', `O treino da equipa "${nome}" foi eliminado com sucesso`);
        setTimeout(() => window.location.reload(), 1500);
      } else {
        toast.error('Erro!', data.message || 'Não foi possível eliminar o treino');
      }
    })
    .catch(() => toast.error('Erro!', 'Erro ao eliminar o treino. Tente novamente.'));
  }

  // Auto-dismiss alerts
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert').forEach(alert => {
      setTimeout(() => new bootstrap.Alert(alert).close(), 5000);
    });
  });
</script>

</body>
</html>

<?php
$stmtTreinos->close();
$conn->close();
?>