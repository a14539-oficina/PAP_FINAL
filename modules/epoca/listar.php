<?php 
session_start(); 
$currentPage = 'epoca';  

ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);  

require_once(__DIR__ . '/../../config/db.php'); 
if (!isset($conn) || !($conn instanceof mysqli)) {     
    die("Ligação \$conn não inicializada."); 
} 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);  

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function table_has_column(mysqli $conn, string $table, string $column): bool {     
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";     
    $stmt = $conn->prepare($sql);     
    $stmt->bind_param("ss", $table, $column);     
    $stmt->execute();     
    $stmt->store_result();     
    $exists = $stmt->num_rows > 0;     
    $stmt->free_result();     
    $stmt->close();     
    return $exists; 
}  

$table = 'seasons';  
$colId = 'id';

$colName   = null; 
foreach (['name','season_name','title','nome'] as $c) {     
    if (table_has_column($conn, $table, $c)) { $colName = $c; break; } 
}  

$colStart  = null; 
foreach (['start_date','start','begin_date','data_inicio'] as $c) {     
    if (table_has_column($conn, $table, $c)) { $colStart = $c; break; } 
}  

$colEnd    = null; 
foreach (['end_date','end','finish_date','data_fim'] as $c) {     
    if (table_has_column($conn, $table, $c)) { $colEnd = $c; break; } 
}  

$colActive = null; 
foreach (['active','is_active','ativa','status'] as $c) {     
    if (table_has_column($conn, $table, $c)) { $colActive = $c; break; } 
}  

$selectParts = [];  
if ($colId)    { $selectParts[] = "$colId AS id"; } 
if ($colName)  { $selectParts[] = "$colName AS name"; }  
if ($colStart) { $selectParts[] = "DATE_FORMAT($colStart, '%Y-%m-%d') AS start_date"; } 
if ($colEnd)   { $selectParts[] = "DATE_FORMAT($colEnd, '%Y-%m-%d') AS end_date"; } 
if ($colActive){ $selectParts[] = "$colActive AS active"; }  
if (empty($selectParts)) { $selectParts[] = "1 AS id"; }  

$orderExpr = $colStart ? "COALESCE($colStart, '1000-01-01')" : "1";  

$porPagina = isset($_GET['entries']) ? max(10, min(50, (int)$_GET['entries'])) : 10;
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * $porPagina;

$total = 0; 
try {     
    $resTot = $conn->query("SELECT COUNT(*) AS c FROM $table");     
    $total = (int)($resTot->fetch_assoc()['c'] ?? 0); 
} catch (Throwable $e) { /* ignora */ }

$totalPaginas = $total > 0 ? ceil($total / $porPagina) : 1;

$sql = "SELECT " . implode(", ", $selectParts) . " FROM $table ORDER BY $orderExpr DESC LIMIT ? OFFSET ?";  
$stmt = $conn->prepare($sql); 
$stmt->bind_param("ii", $porPagina, $offset); 
$stmt->execute(); 
$res = $stmt->get_result(); 
$rows = $res->fetch_all(MYSQLI_ASSOC); 
$stmt->close();  

$user_id   = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? '4'; 

$canManageSeasons = in_array($user_role, ['0', '1']);
$isSuperAdmin     = ($user_role === '0');
$isAdmin          = ($user_role === '1');
?> 
<!doctype html> 
<html lang="pt"> 
<head>   
  <meta charset="utf-8">   
  <meta name="viewport" content="width=device-width, initial-scale=1.0">   
  <title>Épocas - SportGes</title>   
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
      padding: 1rem 2rem 2rem 2rem;
      width: calc(100% - 240px);
      transition: margin-left 0.3s ease, width 0.3s ease, padding 0.3s ease;
      box-sizing: border-box;
      min-height: 100vh;
    }

    .content-wrapper { width: 100%; }

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

    .header-left { display: flex; align-items: center; gap: 1.5rem; flex: 1; }

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

    .header-actions { display: flex; gap: 1rem; align-items: center; flex-shrink: 0; }

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

    .pagination-info-section { display: flex; align-items: center; gap: 2.5rem; flex-wrap: wrap; }
    .pagination-info { font-size: 0.9375rem; color: #475569; font-weight: 500; }
    .entries-control { display: flex; align-items: center; gap: 0.75rem; font-size: 0.9375rem; color: #64748b; }

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

    .seasons-cards-container { width: 100%; display: grid; gap: 0.75rem; }

    .season-card {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 1rem 1.5rem;
      display: grid;
      grid-template-columns: 60px 250px 1fr auto;
      align-items: center;
      gap: 1.5rem;
      transition: all 0.2s;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .season-card:hover {
      border-color: #cbd5e1;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      transform: translateY(-1px);
    }

    .season-icon {
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
    }

    .season-name {
      font-size: 1rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .season-info {
      display: grid;
      grid-template-columns: repeat(2, minmax(150px, 1fr));
      align-items: center;
      gap: 1.5rem;
    }

    .season-detail { display: flex; align-items: center; gap: 0.5rem; }
    .season-detail i { font-size: 1rem; color: #64748b; flex-shrink: 0; }
    .season-detail span { font-size: 0.875rem; color: #475569; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

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

    .status-badge.active { background: #dcfce7; color: #166534; }
    .status-badge.inactive { background: #f3f4f6; color: #6b7280; }

    .season-actions { display: flex; gap: 0.5rem; align-items: center; flex-shrink: 0; margin-left: auto; }

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

    .action-btn.view {
      background: #3b82f6;
      border-color: #3b82f6;
      color: white;
      padding: 0.5rem 1rem;
      min-width: auto;
      gap: 0.5rem;
      font-weight: 500;
      font-size: 0.875rem;
    }

    .action-btn.view:hover { background: #2563eb; border-color: #2563eb; color: white; }
    .action-btn.delete:hover { background: #fef2f2; border-color: #ef4444; color: #ef4444; }
    .action-btn.edit:hover { background: #eff6ff; border-color: #3b82f6; color: #3b82f6; }

    .empty-state {
      background: white;
      border: 2px dashed #cbd5e1;
      border-radius: 16px;
      padding: 4rem 2rem;
      text-align: center;
      margin-top: 2rem;
    }

    .empty-state i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1.5rem; }
    .empty-state h3 { font-size: 1.5rem; font-weight: 700; color: #334155; margin-bottom: 0.75rem; }
    .empty-state p { font-size: 1rem; color: #64748b; margin-bottom: 2rem; }

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

    @media (max-width: 1400px) { .season-card { grid-template-columns: 60px 200px 1fr auto; } }

    @media (max-width: 1200px) {
      .main-content { margin-left: 0; width: 100%; }
      .season-card { grid-template-columns: 60px 180px 1fr auto; }
    }

    @media (max-width: 1024px) {
      .main-content { margin-left: 0; width: 100%; padding: 1rem; }
    }

    @media (max-width: 768px) {
      .import-list-header { padding: 1.25rem; }
      .header-left { flex-direction: column; width: 100%; align-items: stretch; }
      .import-list-header h1 { font-size: 1.5rem; }
      .season-card { display: block; padding: 1.25rem; }
      .season-icon { float: left; margin-right: 1rem; margin-bottom: 1rem; width: 56px; height: 56px; font-size: 1.25rem; }
      .season-name { display: flex; align-items: center; height: 56px; font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; }
      .season-info { clear: both; display: block; width: 100%; }
      .season-detail { display: flex; justify-content: flex-start; align-items: center; gap: 0.625rem; margin-bottom: 0.625rem; }
      .season-actions { display: flex; justify-content: flex-start; gap: 0.625rem; margin-top: 1rem; width: 100%; }
      .action-btn.edit { display: none !important; }
      .action-btn { min-width: 44px; height: 44px; }
      .action-btn.view { margin-left: auto; }
      .pagination-container { flex-direction: column; align-items: stretch; padding: 1rem; }
      .pagination-info-section { flex-direction: column; align-items: stretch; gap: 1rem; }
      .pagination-controls { justify-content: center; }
      .search-wrapper-header { min-width: 100%; max-width: 100%; }
      .header-actions { width: 100%; flex-direction: column; }
      .btn-push-all { width: 100%; justify-content: center; }
    }

    @media (max-width: 480px) {
      .season-card { padding: 1rem; }
      .season-icon { width: 52px; height: 52px; font-size: 1.125rem; }
      .season-name { height: 52px; font-size: 1rem; }
      .action-btn { min-width: 40px; height: 40px; font-size: 0.9375rem; }
      .action-btn.view { padding: 0.625rem 1rem; font-size: 0.875rem; }
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
        <i class="bi bi-calendar-event"></i>
        Épocas
      </h1>
    </div>
    <div class="header-actions">
      <div class="search-wrapper-header">
        <i class="bi bi-search"></i>
        <input type="search" class="search-input-header" id="searchBar" placeholder="Pesquisar época...">
      </div>
      <?php if ($canManageSeasons): ?>
        <a href="criar.php" class="btn-push-all">
          <i class="bi bi-plus-circle-fill"></i>
          Criar Época
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill"></i>
      <span><?= h($_SESSION['success']) ?></span>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span><?= h($_SESSION['error']) ?></span>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if ($total === 0): ?>
    <div class="empty-state">
      <i class="bi bi-calendar-plus"></i>
      <h3>Nenhuma época encontrada</h3>
      <p>
        <?php if ($canManageSeasons): ?>
          Comece a organizar o sistema criando a primeira época
        <?php else: ?>
          Não existem épocas no sistema
        <?php endif; ?>
      </p>
      <?php if ($canManageSeasons): ?>
        <a href="criar.php" class="btn-push-all">
          <i class="bi bi-plus-circle-fill"></i>
          Criar Época
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div class="seasons-cards-container">
      <?php foreach ($rows as $r): ?>
      <div class="season-card epoca-row">
        <div class="season-icon">
          <i class="bi bi-calendar-event-fill"></i>
        </div>
        
        <h3 class="season-name"><?= h($r['name'] ?? '') ?></h3>
        
        <div class="season-info">
          <div class="season-detail">
            <i class="bi bi-calendar-check"></i>
            <span><?= h($r['start_date'] ?? '') ?></span>
          </div>
          <div class="season-detail">
            <i class="bi bi-calendar-x"></i>
            <span><?= h($r['end_date'] ?? '') ?></span>
          </div>
          <div class="season-detail">
            <?php
              $activeVal = $r['active'] ?? null;
              $isActive  = false;
              if ($activeVal !== null) {
                  if (is_string($activeVal)) {
                      $v = strtolower(trim($activeVal));
                      $isActive = in_array($v, ['1','true','y','yes','sim','ativa','active']);
                  } else {
                      $isActive = (bool)$activeVal;
                  }
              }
            ?>
            <?php if ($isActive): ?>
              <span class="status-badge active">
                <i class="bi bi-check-circle-fill me-1"></i>Ativa
              </span>
            <?php else: ?>
              <span class="status-badge inactive">
                <i class="bi bi-x-circle-fill me-1"></i>Inativa
              </span>
            <?php endif; ?>
          </div>
        </div>
        
        <?php if ($canManageSeasons): ?>
        <div class="season-actions">
          <button
            class="action-btn delete btn-eliminar"
            title="Eliminar"
            data-id="<?= h($r['id'] ?? '') ?>"
            data-nome="<?= h($r['name'] ?? '') ?>"
          >
            <i class="bi bi-trash-fill"></i>
          </button>
          <a href="criar.php?id=<?= h($r['id'] ?? '') ?>" class="action-btn edit" title="Editar">
            <i class="bi bi-pencil-fill"></i>
          </a>
          <a href="criar.php?id=<?= h($r['id'] ?? '') ?>" class="action-btn view">
            <i class="bi bi-eye-fill"></i>
            <span>Ver</span>
          </a>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="pagination-container" style="margin-top: 1.5rem;">
      <div class="pagination-info-section">
        <div class="pagination-info">
          Página <strong><?= $pagina ?></strong> de <strong><?= $totalPaginas ?></strong> • Total: <strong><?= $total ?></strong> épocas
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
        <a href="?page=1&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == 1 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-bar-left"></i>
        </a>
        <a href="?page=<?= max(1, $pagina - 1) ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == 1 ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
        <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
          <a href="?page=<?= $i ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $i == $pagina ? 'active' : '' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
        <a href="?page=<?= min($totalPaginas, $pagina + 1) ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == $totalPaginas ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
        <a href="?page=<?= $totalPaginas ?>&entries=<?= $porPagina ?>" class="pagination-btn <?= $pagina == $totalPaginas ? 'disabled' : '' ?>">
          <i class="bi bi-chevron-bar-right"></i>
        </a>
      </div>
    </div>

  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../toast.js"></script>
<script>
// PESQUISA
document.getElementById('searchBar')?.addEventListener('input', function() {
  const termo = this.value.toLowerCase().trim();
  document.querySelectorAll('.epoca-row').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(termo) ? '' : 'none';
  });
});

document.getElementById('searchBar')?.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') e.preventDefault();
});

// ELIMINAR com toast.confirm
document.querySelectorAll('.btn-eliminar').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const id   = this.getAttribute('data-id');
    const nome = this.getAttribute('data-nome');

    toast.confirm({
      type: 'warning',
      title: 'Eliminar Época?',
      message: `Tem certeza que deseja eliminar "${nome}"? Esta ação não pode ser desfeita.`,
      confirmText: 'Eliminar',
      cancelText: 'Cancelar',
      onConfirm: () => eliminarEpoca(id, nome)
    });
  });
});

function eliminarEpoca(id, nome) {
  toast.info('A eliminar...', 'Aguarde um momento');

  fetch('eliminar.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${encodeURIComponent(id)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      toast.success('Eliminado!', `Época "${nome}" eliminada com sucesso`);
      const card = document.querySelector(`.btn-eliminar[data-id="${id}"]`)?.closest('.season-card');
      if (card) {
        card.style.transition = 'opacity 0.3s, transform 0.3s';
        card.style.opacity = '0';
        card.style.transform = 'translateX(20px)';
        setTimeout(() => card.remove(), 300);
      } else {
        setTimeout(() => window.location.reload(), 1500);
      }
    } else {
      toast.error('Erro!', data.message || 'Não foi possível eliminar a época.');
    }
  })
  .catch(err => {
    console.error(err);
    toast.error('Erro!', 'Erro ao eliminar época. Tente novamente.');
  });
}
</script>
</body> 
</html>