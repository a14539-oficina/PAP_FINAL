<?php
session_start();
require_once(__DIR__ . '/../../config/db.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($conn) || !($conn instanceof mysqli)) {
  die("Ligação \$conn não inicializada.");
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 🔒 VERIFICAR PERMISSÕES
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? '4';

// Super Admin (ID 7) tem SEMPRE todos os direitos
$isSuperAdmin = ($user_id == 7);

// Definir se é jogador (sem permissões de criar/editar)
if ($isSuperAdmin) {
    $e_jogador = false; // Super Admin pode fazer TUDO
} else {
    $e_jogador = (strtolower($user_role) === '4'); // Jogador comum não pode
}

// Se for jogador (e não super admin), bloquear acesso
if ($e_jogador) {
    $_SESSION['error'] = 'Não tem permissões para aceder a esta página.';
    header('Location: listar.php');
    exit;
}

$table = 'seasons';

// Função para descobrir colunas equivalentes
function find_col(mysqli $conn, string $table, array $candidates): ?string {
  $placeholders = implode(',', array_fill(0, count($candidates), '?'));
  $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ($placeholders) LIMIT 1";
  $stmt = $conn->prepare($sql);
  $types = str_repeat('s', 1 + count($candidates));
  $params = array_merge([$table], $candidates);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  return $row['COLUMN_NAME'] ?? null;
}

// Mapear colunas
$idCol     = find_col($conn, $table, ['id','season_id']);
$nameCol   = find_col($conn, $table, ['name','season_name','title','nome']);
$startCol  = find_col($conn, $table, ['start_date','start','begin_date','data_inicio']);
$endCol    = find_col($conn, $table, ['end_date','end','finish_date','data_fim']);
$activeCol = find_col($conn, $table, ['active','is_active','ativa','status']);

if (!$idCol) { die('Tabela "seasons" sem coluna de ID reconhecida.'); }

// Criar nova época
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !isset($_POST['id'])) {
  $fields = []; $placeholders = []; $types = ''; $values = [];

  if ($nameCol && !empty($_POST['name'])) {
    $fields[] = $nameCol; $placeholders[] = '?'; $types .= 's'; $values[] = trim($_POST['name']);
  }
  if ($startCol) {
    $fields[] = $startCol; $placeholders[] = '?'; $types .= 's'; 
    $values[] = (!empty($_POST['start']) ? $_POST['start'] : null);
  }
  if ($endCol) {
    $fields[] = $endCol; $placeholders[] = '?'; $types .= 's'; 
    $values[] = (!empty($_POST['end']) ? $_POST['end'] : null);
  }
  if ($activeCol) {
    $fields[] = $activeCol; $placeholders[] = '?'; $types .= 'i'; 
    $values[] = (isset($_POST['active']) ? 1 : 0);
  }

  if (!$fields) {
    echo json_encode(['success' => false, 'message' => 'Nada para inserir.']);
    exit;
  }

  $sql = "INSERT INTO $table (".implode(', ', $fields).") VALUES (".implode(', ', $placeholders).")";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$values);
  $stmt->execute();

  echo json_encode(['success' => true, 'message' => 'Época criada com sucesso!']);
  exit;
}

// Atualizar época existente
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['id'])) {
  $id = (int)$_POST['id'];
  if ($id <= 0) { 
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
  }

  $fields = []; $types = ''; $values = [];

  if ($nameCol && array_key_exists('name', $_POST)) {
    $fields[] = "$nameCol = ?"; $types .= 's'; $values[] = trim($_POST['name']);
  }
  if ($startCol && array_key_exists('start', $_POST)) {
    $fields[] = "$startCol = ?"; $types .= 's'; $values[] = ($_POST['start'] !== '' ? $_POST['start'] : null);
  }
  if ($endCol && array_key_exists('end', $_POST)) {
    $fields[] = "$endCol = ?"; $types .= 's'; $values[] = ($_POST['end'] !== '' ? $_POST['end'] : null);
  }
  if ($activeCol) {
    $fields[] = "$activeCol = ?"; $types .= 'i'; $values[] = (isset($_POST['active']) ? 1 : 0);
  }

  if (!$fields) {
    echo json_encode(['success' => false, 'message' => 'Nada para atualizar.']);
    exit;
  }

  $sql = "UPDATE $table SET ".implode(', ', $fields)." WHERE $idCol = ?";
  $types .= 'i'; $values[] = $id;

  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$values);
  $stmt->execute();

  echo json_encode(['success' => true, 'message' => 'Época atualizada com sucesso!']);
  exit;
}

// Carregar season para edição
$season = null;
$isEdit = false;
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
  $isEdit = true;
  $select = ["$idCol AS id"];
  if ($nameCol)   $select[] = "$nameCol AS name";
  if ($startCol)  $select[] = "DATE_FORMAT($startCol, '%Y-%m-%d') AS start";
  if ($endCol)    $select[] = "DATE_FORMAT($endCol, '%Y-%m-%d') AS end";
  if ($activeCol) $select[] = "$activeCol AS active";

  $sql = "SELECT ".implode(', ', $select)." FROM $table WHERE $idCol = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $season = $stmt->get_result()->fetch_assoc();
  
  if (!$season) { 
    $_SESSION['error'] = 'Época não encontrada.'; 
    header('Location: listar.php'); 
    exit; 
  }
}

// Definir página atual para o sidebar
$currentPage = 'epoca';
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Editar' : 'Criar' ?> Época - SportGes</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, sans-serif;
      background: #f8fafc;
      color: #1e293b;
      min-height: 100vh;
    }

    .main-content {
      margin-left: 240px;
      padding: 2rem;
      width: calc(100% - 240px);
      min-height: 100vh;
    }

    .content-wrapper {
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Toast Notifications */
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .toast {
      background: white;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
      display: flex;
      align-items: center;
      gap: 0.875rem;
      min-width: 320px;
      max-width: 450px;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .toast.success { border-left: 4px solid #10b981; }
    .toast.error { border-left: 4px solid #ef4444; }
    .toast.info { border-left: 4px solid #3b82f6; }

    .toast-icon {
      font-size: 1.5rem;
    }

    .toast.success .toast-icon { color: #10b981; }
    .toast.error .toast-icon { color: #ef4444; }
    .toast.info .toast-icon { color: #3b82f6; }

    .toast-content {
      flex: 1;
    }

    .toast-title {
      font-weight: 600;
      font-size: 0.9375rem;
      color: #0f172a;
      margin-bottom: 0.25rem;
    }

    .toast-message {
      font-size: 0.875rem;
      color: #64748b;
    }

    .toast-close {
      background: transparent;
      border: none;
      color: #94a3b8;
      cursor: pointer;
      padding: 0.25rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
    }

    .toast-close:hover {
      color: #475569;
    }

    /* Page Header */
    .page-header {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
    }

    .page-header-left h1 {
      font-size: 2rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 0.5rem 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .page-header-left h1 i {
      font-size: 2.5rem;
      color: #3b82f6;
    }

    .page-header-left p {
      color: #64748b;
      margin: 0;
      font-size: 1rem;
    }

    .page-header-actions {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .btn-header {
      padding: 0.875rem 1.75rem;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s;
      text-decoration: none;
      white-space: nowrap;
    }

    .btn-back {
      background: #f1f5f9;
      color: #475569;
    }

    .btn-back:hover {
      background: #e2e8f0;
      color: #334155;
      transform: translateY(-2px);
    }

    /* Form Container */
    .form-container {
      background: white;
      border-radius: 16px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      overflow: hidden;
    }

    .form-section {
      padding: 2.5rem 2rem;
      border-bottom: 1px solid #f1f5f9;
    }

    .form-section:last-child {
      border-bottom: none;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #f1f5f9;
    }

    .section-header i {
      font-size: 1.75rem;
      color: #3b82f6;
    }

    .section-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      flex: 1;
    }

    .form-label {
      display: block;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #334155;
      margin-bottom: 0.75rem;
    }

    .apple-input {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 1rem;
      color: #1e293b;
      background: white;
      transition: all 0.2s;
    }

    .apple-input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .apple-input::placeholder {
      color: #94a3b8;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.25rem;
      margin-bottom: 1.25rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    /* Switch Toggle */
    .switch-container {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1.25rem;
      background: #f8fafc;
      border-radius: 12px;
      border: 2px solid #e2e8f0;
      transition: all 0.2s;
    }

    .switch-container:hover {
      border-color: #cbd5e1;
      background: #f1f5f9;
    }

    .switch {
      position: relative;
      display: inline-block;
      width: 52px;
      height: 28px;
      flex-shrink: 0;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #cbd5e1;
      transition: .3s;
      border-radius: 28px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 22px;
      width: 22px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .3s;
      border-radius: 50%;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    input:checked + .slider {
      background-color: #10b981;
    }

    input:checked + .slider:before {
      transform: translateX(24px);
    }

    .switch-label {
      font-size: 1rem;
      font-weight: 600;
      color: #334155;
      flex: 1;
    }

    .switch-description {
      font-size: 0.875rem;
      color: #64748b;
      margin-top: 0.25rem;
    }

    /* Submit Section */
    .submit-section {
      padding: 2.5rem 2rem;
      background: #f8fafc;
      text-align: center;
    }

    .main-save-btn {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      border: none;
      padding: 1.125rem 3.5rem;
      border-radius: 12px;
      font-size: 1.0625rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.625rem;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .main-save-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    .main-save-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 1.5rem;
      }

      .page-header {
        padding: 1.5rem;
      }

      .page-header-left h1 {
        font-size: 1.5rem;
      }

      .form-section {
        padding: 1.5rem 1rem;
      }

      .form-row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<!-- Incluir Sidebar -->
<?php require('../../includes/sidebar.php'); ?>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Main Content -->
<div class="main-content">
  <div class="content-wrapper">
    
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-left">
        <h1>
          <i class="bi bi-calendar-event-fill"></i>
          <?= $isEdit ? 'Editar Época' : 'Criar Nova Época' ?>
        </h1>
        <p>
          <?php if ($isEdit): ?>
            Atualize as informações da época #<?= (int)$season['id'] ?>
          <?php else: ?>
            Preencha os dados para criar uma nova época no sistema
          <?php endif; ?>
        </p>
      </div>
      <div class="page-header-actions">
        <a href="listar.php" class="btn-header btn-back">
          <i class="bi bi-arrow-left"></i>
          Voltar
        </a>
      </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
      <form id="seasonForm">
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$season['id'] ?>">
        <?php endif; ?>

        <!-- Informações Básicas -->
        <div class="form-section">
          <div class="section-header">
            <i class="bi bi-info-circle-fill"></i>
            <h2>Informações da Época</h2>
          </div>

          <?php if ($nameCol): ?>
          <div class="form-group">
            <label class="form-label">Nome da Época *</label>
            <input 
              type="text" 
              name="name" 
              class="apple-input" 
              required 
              value="<?= $isEdit ? htmlspecialchars($season['name'] ?? '') : '' ?>"
              placeholder="Ex: Época 2024/2025"
            >
          </div>
          <?php endif; ?>

          <div class="form-row">
            <?php if ($startCol): ?>
            <div class="form-group">
              <label class="form-label">Data de Início</label>
              <input 
                type="date" 
                name="start" 
                class="apple-input" 
                value="<?= $isEdit ? htmlspecialchars($season['start'] ?? '') : '' ?>"
              >
            </div>
            <?php endif; ?>

            <?php if ($endCol): ?>
            <div class="form-group">
              <label class="form-label">Data de Fim</label>
              <input 
                type="date" 
                name="end" 
                class="apple-input" 
                value="<?= $isEdit ? htmlspecialchars($season['end'] ?? '') : '' ?>"
              >
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Status -->
        <?php if ($activeCol): ?>
        <div class="form-section">
          <div class="section-header">
            <i class="bi bi-toggle-on"></i>
            <h2>Status da Época</h2>
          </div>

          <div class="switch-container">
            <label class="switch">
              <?php
                $isChecked = false;
                if ($isEdit && isset($season['active'])) {
                  $val = $season['active'];
                  if (is_string($val)) {
                    $val = strtolower(trim($val));
                    $isChecked = in_array($val, ['1', 'true', 'yes', 'sim', 'ativa', 'active']);
                  } else {
                    $isChecked = (bool)$val;
                  }
                }
              ?>
              <input type="checkbox" id="active" name="active" <?= $isChecked ? 'checked' : '' ?>>
              <span class="slider"></span>
            </label>
            <div style="flex: 1;">
              <div class="switch-label">Época Ativa</div>
              <div class="switch-description">Define se esta época está atualmente ativa no sistema</div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Submit -->
        <div class="submit-section">
          <button type="submit" class="main-save-btn">
            <i class="bi bi-check-circle-fill"></i>
            <?= $isEdit ? 'Atualizar Época' : 'Criar Época' ?>
          </button>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
function showToast(type, title, message) {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  
  const icons = {
    success: 'bi-check-circle-fill',
    error: 'bi-x-circle-fill',
    info: 'bi-info-circle-fill'
  };
  
  toast.innerHTML = `
    <i class="toast-icon bi ${icons[type]}"></i>
    <div class="toast-content">
      <div class="toast-title">${title}</div>
      <div class="toast-message">${message}</div>
    </div>
    <button class="toast-close" onclick="this.parentElement.remove()">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 5000);
}

document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('seasonForm');
  if (!form) return;
  
  const submitBtn = form.querySelector('button[type="submit"]');
  const isEdit = form.querySelector('input[name="id"]') !== null;
  
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> A processar...';
    }
    
    fetch(window.location.href, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('success', 'Sucesso!', data.message);
        setTimeout(() => {
          window.location.href = 'listar.php';
        }, 1500);
      } else {
        throw new Error(data.message || `Erro ao ${isEdit ? 'atualizar' : 'criar'} época`);
      }
    })
    .catch(error => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${isEdit ? 'Atualizar Época' : 'Criar Época'}`;
      }
      showToast('error', 'Erro!', error.message || `Erro ao ${isEdit ? 'atualizar' : 'criar'} época`);
    });
  });
});
</script>

</body>
</html>