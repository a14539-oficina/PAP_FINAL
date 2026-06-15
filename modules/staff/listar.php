<?php
session_start();
$currentPage = 'staff'; 
require('../../config/db.php');
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_role = $_SESSION['user_role'] ?? '4';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Paginação
$porPagina = isset($_GET['entries']) ? max(10, min(50, (int)$_GET['entries'])) : 10;
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * $porPagina;

// Filtro por cargo
$cargo_filter = $_GET['cargo'] ?? '';

// Count total com filtro
$club_id = $_SESSION['club_id'];
$sqlCount = "SELECT COUNT(*) as total FROM staff WHERE club_id = ?";
if ($cargo_filter) {
    $sqlCount .= " AND cargo_principal = ?";
}

try {
    $stmtCount = $conn->prepare($sqlCount);
if ($cargo_filter) {
    $stmtCount->bind_param("is", $club_id, $cargo_filter);
} else {
    $stmtCount->bind_param("i", $club_id);
}
$stmtCount->execute();
$resCount = $stmtCount->get_result();
    $totalStaff = $resCount->fetch_assoc()['total'];
} catch (Exception $e) {
    $totalStaff = 0;
}

$totalPaginas = ceil($totalStaff / $porPagina);

// Query principal com filtro e paginação
$sql = "SELECT * FROM staff WHERE club_id = ?";
if ($cargo_filter) {
    $sql .= " AND cargo_principal = ?";
}
$sql .= " ORDER BY nome ASC LIMIT $porPagina OFFSET $offset";

try {
    $stmt = $conn->prepare($sql);
    if ($cargo_filter) {
        $stmt->bind_param("is", $club_id, $cargo_filter);
    } else {
        $stmt->bind_param("i", $club_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $staff_list = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    $staff_list = [];
    $db_error = $e->getMessage();
}

$podeEditar = ($user_role === '0' || $user_role === '1');
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão de Staff - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #f8fafc;
      color: #1e293b;
    }

    .main-content {
      margin-left: 240px;
      margin-top: 0;
      padding: 24px;
      width: calc(100% - 240px);
      box-sizing: border-box;
      min-height: 100vh;
      overflow-x: hidden;
      transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease;
    }

    .content-wrapper {
      max-width: 1600px;
      margin: 0 auto;
      width: 100%;
    }

    /* Header */
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

    .import-list-header h1 i {
      font-size: 2rem;
      color: #3b82f6;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

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
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
      background: white;
    }

    .header-actions {
      display: flex;
      gap: 1rem;
      align-items: center;
    }

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
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .btn-push-all:hover {
      transform: translateY(-2px);
      color: white;
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
    }

    /* Filter Section */
    .filter-section {
      background: white;
      border: 2px solid #e2e8f0;
      border-radius: 16px;
      padding: 1.5rem 2rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .filter-label {
      font-size: 0.9375rem;
      font-weight: 600;
      color: #475569;
      margin-bottom: 0.75rem;
      display: block;
    }

    .filter-select {
      background: white;
      border: 2px solid #e2e8f0;
      padding: 0.75rem 3rem 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.9375rem;
      color: #334155;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L3 5h8z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      min-width: 250px;
      font-weight: 500;
      transition: all 0.2s;
    }

    .filter-select:hover {
      border-color: #cbd5e1;
    }

    .filter-select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    /* Pagination */
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

    .pagination-info {
      font-size: 0.9375rem;
      color: #475569;
      font-weight: 500;
    }

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

    .entries-select:hover {
      border-color: #cbd5e1;
    }

    .pagination-controls {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

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

    .pagination-btn.active {
      background: #3b82f6;
      border-color: #3b82f6;
      color: white;
      font-weight: 700;
    }

    .pagination-btn.disabled {
      opacity: 0.4;
      pointer-events: none;
    }

    /* Staff Cards */
    .teams-cards-container {
  width: 100%;
  display: grid;
  gap: 0.75rem;
}

     /* Staff Cards (compact version) */
.team-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  display: grid;
  grid-template-columns: 48px 140px 1fr auto;
  align-items: center;
  gap: 1rem;
  transition: all 0.2s;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}

.team-card:hover {
  border-color: #cbd5e1;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
  transform: translateY(-1px);
}

.team-avatar {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1rem;
  font-weight: 700;
  flex-shrink: 0;
}

.team-name {
  font-size: 0.95rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.team-info {
  display: grid;
  grid-template-columns: repeat(3, minmax(100px, 1fr));
  gap: 0.75rem;
  align-items: center;
}

.team-detail {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  color: #475569;
}

.team-detail i {
  font-size: 0.9rem;
  color: #64748b;
}

.status-badge, .cargo-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.inactive {
  background: #f3f4f6;
  color: #6b7280;
}

.cargo-badge.diretor {
  background: #fef3c7;
  color: #92400e;
}
.cargo-badge.treinador {
  background: #dbeafe;
  color: #1e40af;
}
.cargo-badge.adjunto {
  background: #e0e7ff;
  color: #4338ca;
}
.cargo-badge.fisioterapeuta {
  background: #dcfce7;
  color: #166534;
}
.cargo-badge.outro {
  background: #f3f4f6;
  color: #4b5563;
}

/* Botões de ação menores */
.team-actions {
  display: flex;
  gap: 0.4rem;
  align-items: center;
  flex-shrink: 0;
}

.action-btn {
  padding: 0.35rem 0.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: white;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 34px;
  height: 34px;
  font-size: 0.9rem;
  text-decoration: none;
}

.action-btn.view {
  background: #3b82f6;
  border-color: #3b82f6;
  color: white;
  font-weight: 500;
  padding: 0.35rem 0.75rem;
}

.action-btn.view:hover {
  background: #2563eb;
}


    .action-btn.edit:hover {
      background: #eff6ff;
      border-color: #3b82f6;
      color: #3b82f6;
    }

    /* Empty State */
    .empty-state {
      background: white;
      border: 2px dashed #cbd5e1;
      border-radius: 16px;
      padding: 4rem 2rem;
      text-align: center;
      margin-top: 2rem;
    }

    .empty-state i {
      font-size: 4rem;
      color: #cbd5e1;
      margin-bottom: 1.5rem;
    }

    .empty-state h3 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #334155;
      margin-bottom: 0.75rem;
    }

    .empty-state p {
      font-size: 1rem;
      color: #64748b;
      margin-bottom: 2rem;
    }

    /* Alerts */
    .alert {
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      border: 2px solid;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .alert i {
      font-size: 1.25rem;
    }

    .alert-success {
      background: #dcfce7;
      border-color: #86efac;
      color: #166534;
    }

    .alert-danger {
      background: #fee2e2;
      border-color: #fca5a5;
      color: #991b1b;
    }

    /* Responsive */
    @media (max-width: 1400px) {
      .team-card {
        grid-template-columns: 60px 180px 1fr auto;
      }

      .team-info {
        grid-template-columns: repeat(2, minmax(100px, 1fr));
        gap: 1rem;
      }
    }

    @media (max-width: 1200px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }

      .team-card {
        grid-template-columns: 60px 150px 1fr auto;
      }

      .team-info {
        grid-template-columns: 1fr;
        gap: 0.75rem;
      }
    }

    @media (max-width: 768px) {
      .import-list-header {
        padding: 1.25rem;
      }

      .team-card {
        display: block;
        padding: 1.25rem;
      }

      .team-avatar {
        float: left;
        margin-right: 1rem;
        margin-bottom: 1rem;
        width: 56px;
        height: 56px;
        font-size: 1.25rem;
      }

      .team-name {
        display: flex;
        align-items: center;
        height: 56px;
        text-align: left;
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
        line-height: 1.2;
      }

      .team-info {
        clear: both;
        display: block;
        width: 100%;
      }

      .team-detail {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: 0.625rem;
        margin-bottom: 0.625rem;
        text-align: left;
      }

      .team-detail i {
        font-size: 1.125rem;
        color: #64748b;
      }

      .team-detail span {
        font-size: 0.9375rem;
        color: #475569;
      }

      .team-actions {
        display: flex;
        justify-content: flex-start;
        gap: 0.625rem;
        margin-top: 1rem;
        width: 100%;
      }

      .action-btn.edit {
        display: none !important;
      }

      .action-btn {
        min-width: 44px;
        height: 44px;
      }

      .action-btn.view {
        margin-left: auto;
      }

      .pagination-container {
        flex-direction: column;
        align-items: stretch;
        padding: 1rem;
      }

      .pagination-info-section {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
      }

      .pagination-controls {
        justify-content: center;
      }

      .search-wrapper-header {
        min-width: 100%;
        max-width: 100%;
      }

      .header-actions {
        width: 100%;
        flex-direction: column;
      }

      .btn-push-all {
        width: 100%;
        justify-content: center;
      }

      .import-list-header h1 {
        font-size: 1.5rem;
      }

      .filter-select {
        width: 100%;
        min-width: 100%;
      }
    }

    @media (max-width: 480px) {
      .team-card {
        padding: 1rem;
      }

      .team-avatar {
        width: 52px;
        height: 52px;
        font-size: 1.125rem;
      }

      .team-name {
        height: 52px;
        font-size: 1rem;
      }

      .team-detail {
        margin-bottom: 0.5rem;
      }

      .team-detail i {
        font-size: 1rem;
      }

      .team-detail span {
        font-size: 0.875rem;
      }

      .action-btn {
        min-width: 40px;
        height: 40px;
        font-size: 0.9375rem;
      }

      .action-btn.view {
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
      }

      .status-badge, .cargo-badge {
        font-size: 0.6875rem;
        padding: 0.375rem 0.625rem;
      }

      .import-list-header {
        padding: 1rem;
      }

      .pagination-container {
        padding: 0.875rem;
      }

      .filter-section {
        padding: 1rem;
      }
    }
    .action-btn.delete {
  background: white;
  border-color: #e5e7eb;
  color: #64748b;
}

.action-btn.delete:hover {
  background: #fef2f2;
  border-color: #ef4444;
  color: #ef4444;
}

    /* Tablet */
    @media (max-width: 1024px) {
      .main-content {
        margin-left: 0;
        margin-top: 0;
        width: 100%;
        padding: 16px;
      }
    }
  </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
  <div class="import-list-header">
    <div class="header-left">
      <h1>
        <i class="bi bi-people-fill"></i>
        Gestão de Staff
      </h1>
    </div>
    <div class="header-actions">
      <div class="search-wrapper-header">
        <i class="bi bi-search"></i>
        <input type="search" class="search-input-header" id="searchBar" placeholder="Pesquisar membro...">
      </div>
      <?php if ($podeEditar): ?>
      <a href="criar.php" class="btn-push-all">
        <i class="bi bi-plus-circle-fill"></i>
        Novo Membro
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($db_error)): ?>
    <div class="alert alert-danger">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span>Erro ao carregar dados: <?= h($db_error) ?></span>
    </div>
  <?php endif; ?>

  <?php if(isset($_GET['msg']) && $_GET['msg'] == 'eliminado'): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill"></i>
      <span>Membro do staff eliminado com sucesso!</span>
    </div>
  <?php endif; ?>

  <?php if(isset($_GET['msg']) && $_GET['msg'] == 'guardado'): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill"></i>
      <span>Membro do staff guardado com sucesso!</span>
    </div>
  <?php endif; ?>

  <?php if(isset($_GET['erro'])): ?>
    <div class="alert alert-danger">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span>Erro ao processar a operação. Tenta novamente.</span>
    </div>
  <?php endif; ?>

  <?php if ($totalStaff === 0): ?>
    <div class="empty-state">
      <i class="bi bi-person-x"></i>
      <h3>Nenhum membro encontrado</h3>
      <p>
        <?php if ($podeEditar): ?>
          <?= $cargo_filter ? 'Não há membros do staff com este cargo' : 'Comece a organizar o sistema adicionando o primeiro membro do staff' ?>
        <?php else: ?>
          Não existem membros do staff no sistema
        <?php endif; ?>
      </p>
      <?php if ($podeEditar && !$cargo_filter): ?>
      <a href="criar.php" class="btn-push-all">
        <i class="bi bi-plus-circle-fill"></i>
        Novo Membro
      </a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div class="teams-cards-container">
      <?php foreach ($staff_list as $row): 
        // Determina a classe do cargo para o badge
        $cargoClass = strtolower(str_replace(['á', 'é', 'í', 'ó'], ['a', 'e', 'i', 'o'], $row['cargo_principal']));
        
        // Gera iniciais para o avatar
        $nomes = explode(' ', $row['nome']);
        $iniciais = '';
        if (count($nomes) >= 2) {
          $iniciais = strtoupper(substr($nomes[0], 0, 1) . substr($nomes[count($nomes)-1], 0, 1));
        } else {
          $iniciais = strtoupper(substr($row['nome'], 0, 2));
        }
      ?>
      <div class="team-card staff-row">
        <div class="team-avatar">
          <?= $iniciais ?>
        </div>
        
        <h3 class="team-name"><?= h($row['nome']) ?></h3>
        
        <div class="team-info">
          <div class="team-detail">
            <span class="cargo-badge <?= $cargoClass ?>">
              <?= h($row['cargo_principal']) ?>
            </span>
          </div>
          <div class="team-detail">
            <i class="bi bi-telephone-fill"></i>
            <span><?= h($row['telefone'] ?: '—') ?></span>
          </div>
          <div class="team-detail">
            <i class="bi bi-envelope-fill"></i>
            <span><?= h($row['email'] ?: '—') ?></span>
          </div>
          <div class="team-detail">
            <?php if ($row['ativo']): ?>
              <span class="status-badge active">
                <i class="bi bi-check-circle-fill me-1"></i>Ativo
              </span>
            <?php else: ?>
              <span class="status-badge inactive">
                <i class="bi bi-x-circle-fill me-1"></i>Inativo
              </span>
            <?php endif; ?>
          </div>
        </div>
        <div class="team-actions">
          <?php if ($podeEditar): ?>
          
          <a href="editar.php?id=<?= (int)$row['id'] ?>" class="action-btn view">
            <i class="bi bi-eye-fill"></i>
            <span>Editar</span>
          </a>
          <button class="action-btn delete" 
                  title="Eliminar"
                  data-id="<?= (int)$row['id'] ?>" 
                  data-nome="<?= h($row['nome']) ?>">
            <i class="bi bi-trash-fill"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- PAGINAÇÃO EM BAIXO -->
    <div class="pagination-container" style="margin-top: 1.5rem;">
      <div class="pagination-info-section">
        <div class="pagination-info">
          Página <strong><?= $pagina ?></strong> de <strong><?= $totalPaginas ?></strong> • Total: <strong><?= $totalStaff ?></strong> <?= $totalStaff == 1 ? 'membro' : 'membros' ?>
        </div>
        <div class="entries-control">
          <span>Mostrar</span>
          <select class="entries-select" onchange="window.location.href='?entries='+this.value+'&page=1<?= $cargo_filter ? '&cargo=' . urlencode($cargo_filter) : '' ?>'">
            <option value="10" <?= $porPagina == 10 ? 'selected' : '' ?>>10</option>
            <option value="25" <?= $porPagina == 25 ? 'selected' : '' ?>>25</option>
            <option value="50" <?= $porPagina == 50 ? 'selected' : '' ?>>50</option>
          </select>
          <span>por página</span>
        </div>
      </div>

      <div class="pagination-controls">
        <a href="?page=1&entries=<?= $porPagina ?><?= $cargo_filter ? '&cargo=' . urlencode($cargo_filter) : '' ?>" class="pagination-btn <?= $pagina == 1 ? 'disabled' : '' ?>" title="Primeira página">
          <i class="bi bi-chevron-bar-left"></i>
        </a>
        <a href="?page=<?= max(1, $pagina - 1) ?>&entries=<?= $porPagina ?><?= $cargo_filter ? '&cargo=' . urlencode($cargo_filter) : '' ?>" class="pagination-btn <?= $pagina == 1 ? 'disabled' : '' ?>" title="Página anterior">
          <i class="bi bi-chevron-left"></i>
        </a>
        
        <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
          <a href="?page=<?= $i ?>&entries=<?= $porPagina ?><?= $cargo_filter ? '&cargo=' . urlencode($cargo_filter) : '' ?>" class="pagination-btn <?= $i == $pagina ? 'active' : '' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
        
        <a href="?page=<?= min($totalPaginas, $pagina + 1) ?>&entries=<?= $porPagina ?><?= $cargo_filter ? '&cargo=' . urlencode($cargo_filter) : '' ?>" class="pagination-btn <?= $pagina == $totalPaginas ? 'disabled' : '' ?>" title="Próxima página">
          <i class="bi bi-chevron-right"></i>
        </a>
        <a href="?page=<?= $totalPaginas ?>&entries=<?= $porPagina ?><?= $cargo_filter ? '&cargo=' . urlencode($cargo_filter) : '' ?>" class="pagination-btn <?= $pagina == $totalPaginas ? 'disabled' : '' ?>" title="Última página">
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
  document.querySelectorAll('.staff-row').forEach(row => {
    const texto = row.textContent.toLowerCase();
    row.style.display = texto.includes(termo) ? '' : 'none';
  });
});

document.getElementById('searchBar')?.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
  }
});

// Configurar botões de eliminação
document.querySelectorAll('.action-btn.delete').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    
    const staffId = this.getAttribute('data-id');
    const staffNome = this.getAttribute('data-nome');
    
    toast.confirm({
      type: 'warning',
      title: 'Eliminar Membro?',
      message: `Tem certeza que deseja eliminar "${staffNome}" do staff? Esta ação não pode ser desfeita.`,
      confirmText: 'Eliminar',
      cancelText: 'Cancelar',
      onConfirm: () => {
        eliminarStaff(staffId, staffNome);
      }
    });
  });
});

function eliminarStaff(id, nome) {
  toast.info('A eliminar...', 'Aguarde um momento');
  
  fetch('eliminar.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `id=${encodeURIComponent(id)}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      toast.success('Eliminado!', `"${nome}" foi eliminado com sucesso do staff`);
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } else {
      toast.error('Erro!', data.message || 'Não foi possível eliminar o membro do staff');
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    toast.error('Erro!', 'Erro ao eliminar membro. Tente novamente.');
  });
}

// Auto-dismiss alerts após 5 segundos
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(alert => {
    alert.style.transition = 'opacity 0.3s ease';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 300);
  });
}, 5000);
</script>
</body>
</html>