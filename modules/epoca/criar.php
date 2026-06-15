<?php
session_start();
require('../../config/db.php');
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_role = $_SESSION['user_role'] ?? '4';
$podeEditar = ($user_role === '2' || $user_role === '1');

if (!$podeEditar) {
    $_SESSION['error'] = 'Não tem permissões para editar épocas';
    header("Location: listar.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Função para descobrir colunas da tabela
function getColumnName($conn, $possibleNames) {
    foreach ($possibleNames as $name) {
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'seasons' 
                AND COLUMN_NAME = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            return $name;
        }
        $stmt->close();
    }
    return null;
}

// Descobrir nomes das colunas
$colId = getColumnName($conn, ['id']);
$colName = getColumnName($conn, ['nome', 'name', 'season_name', 'title']);
$colStart = getColumnName($conn, ['data_inicio', 'start_date', 'start', 'begin_date']);
$colEnd = getColumnName($conn, ['data_fim', 'end_date', 'end', 'finish_date']);
$colActive = getColumnName($conn, ['ativa', 'active', 'is_active', 'status']);

$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$seasonId = $isEdit ? (int)$_GET['id'] : null;
$season = null;

// Se for edição, carrega os dados da season
if ($isEdit && $colId) {
    $stmt = $conn->prepare("SELECT * FROM seasons WHERE $colId = ?");
    $stmt->bind_param("i", $seasonId);
    $stmt->execute();
    $result = $stmt->get_result();
    $season = $result->fetch_assoc();
    
    if (!$season) {
        $_SESSION['error'] = 'Época não encontrada';
        header("Location: listar.php");
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $data_inicio = trim($_POST['data_inicio'] ?? '');
    $data_fim = trim($_POST['data_fim'] ?? '');
    $ativa = isset($_POST['ativa']) ? 1 : 0;
    
    $errors = [];
    
    // Validações
    if (empty($nome)) {
        $errors[] = 'O nome da época é obrigatório';
    }
    
    if (empty($data_inicio)) {
        $errors[] = 'A data de início é obrigatória';
    }
    
    if (empty($data_fim)) {
        $errors[] = 'A data de fim é obrigatória';
    }
    
    if (!empty($data_inicio) && !empty($data_fim)) {
        if (strtotime($data_inicio) > strtotime($data_fim)) {
            $errors[] = 'A data de início não pode ser posterior à data de fim';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($isEdit && $colId && $colName && $colStart && $colEnd) {
                // Atualizar season existente
                if ($colActive) {
                    $sql = "UPDATE seasons SET $colName = ?, $colStart = ?, $colEnd = ?, $colActive = ? WHERE $colId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssii", $nome, $data_inicio, $data_fim, $ativa, $seasonId);
                } else {
                    $sql = "UPDATE seasons SET $colName = ?, $colStart = ?, $colEnd = ? WHERE $colId = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $nome, $data_inicio, $data_fim, $seasonId);
                }
                $stmt->execute();
                
                $_SESSION['success'] = 'Época atualizada com sucesso';
            } else if (!$isEdit && $colName && $colStart && $colEnd) {
                // Criar nova season
                if ($colActive) {
                    $sql = "INSERT INTO seasons ($colName, $colStart, $colEnd, $colActive) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $nome, $data_inicio, $data_fim, $ativa);
                } else {
                    $sql = "INSERT INTO seasons ($colName, $colStart, $colEnd) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $nome, $data_inicio, $data_fim);
                }
                $stmt->execute();
                
                $_SESSION['success'] = 'Época criada com sucesso';
            } else {
                $errors[] = 'Erro: Colunas necessárias não encontradas na tabela seasons';
            }
            
            if (empty($errors)) {
                header("Location: listar.php");
                exit;
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro ao salvar época: ' . $e->getMessage();
        }
    }
}

// Preparar valores para o formulário
$formNome = '';
$formInicio = '';
$formFim = '';
$formAtiva = true;

if ($isEdit && $season) {
    $formNome = $season[$colName] ?? '';
    $formInicio = $season[$colStart] ?? '';
    $formFim = $season[$colEnd] ?? '';
    $formAtiva = isset($season[$colActive]) && in_array($season[$colActive], [1, '1', true, 'true', 'y', 'yes', 'sim', 'ativa', 'active'], true);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formNome = $_POST['nome'] ?? '';
    $formInicio = $_POST['data_inicio'] ?? '';
    $formFim = $_POST['data_fim'] ?? '';
    $formAtiva = isset($_POST['ativa']);
}

// Definir variáveis para o sidebar
$currentPage = 'epoca';
$modulesPath = '../..';
$dashboardPath = '../..';
$logoutPath = '../..';
$e_jogador = (strtolower($user_role) === '4');
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Editar' : 'Adicionar' ?> Época - SportGes</title>
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
      font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', sans-serif;
      background: #fafafa;
      color: #1e293b;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    .main-content {
      margin-left: 240px;
      padding: 1rem 2rem 2rem 2rem;
      width: calc(100% - 240px);
      box-sizing: border-box;
      min-height: 100vh;
    }

    .content-wrapper {
      max-width: 900px;
      margin: 0 auto;
    }

    /* Header */
    .page-header {
      background: white;
      padding: 2rem;
      border-radius: 16px;
      margin-bottom: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .page-header h1 {
      font-size: 1.75rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .page-header h1 i {
      font-size: 2rem;
      color: #3b82f6;
    }

    .back-btn {
      padding: 0.625rem 1rem;
      background: white;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      color: #64748b;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 500;
      transition: all 0.2s;
      margin-left: auto;
    }

    .back-btn:hover {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #334155;
    }

    /* Form Card */
    .form-card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-label {
      display: block;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #334155;
      margin-bottom: 0.5rem;
    }

    .form-label .required {
      color: #ef4444;
      margin-left: 0.25rem;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1.25rem;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 0.9375rem;
      transition: all 0.2s;
      background: #f8fafc;
    }

    .form-control:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
      background: white;
    }

    .form-control.error {
      border-color: #ef4444;
      background: #fef2f2;
    }

    /* Checkbox Switch */
    .form-switch-wrapper {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: #f8fafc;
      border-radius: 12px;
      border: 2px solid #e2e8f0;
    }

    .form-check {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin: 0;
    }

    .form-check-input {
      width: 3rem;
      height: 1.5rem;
      cursor: pointer;
      background-color: #cbd5e1;
      border: none;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='white'/%3e%3c/svg%3e");
    }

    .form-check-input:checked {
      background-color: #3b82f6;
    }

    .form-check-label {
      font-size: 0.9375rem;
      font-weight: 500;
      color: #334155;
      cursor: pointer;
      margin: 0;
    }

    .switch-description {
      font-size: 0.875rem;
      color: #64748b;
      margin-left: auto;
    }

    /* Alert Messages */
    .alert {
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      border: 2px solid;
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
    }

    .alert i {
      font-size: 1.25rem;
      flex-shrink: 0;
      margin-top: 0.125rem;
    }

    .alert-danger {
      background: #fee2e2;
      border-color: #fca5a5;
      color: #991b1b;
    }

    .alert ul {
      margin: 0;
      padding-left: 1.25rem;
    }

    .alert li {
      margin-bottom: 0.25rem;
    }

    .alert li:last-child {
      margin-bottom: 0;
    }

    /* Form Actions */
    .form-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
      padding-top: 2rem;
      border-top: 2px solid #e2e8f0;
    }

    .btn {
      padding: 0.875rem 2rem;
      border-radius: 12px;
      font-size: 0.9375rem;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s;
      border: none;
      text-decoration: none;
      justify-content: center;
    }

    .btn-primary {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
      flex: 1;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
    }

    .btn-secondary {
      background: white;
      color: #64748b;
      border: 2px solid #e2e8f0;
    }

    .btn-secondary:hover {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #334155;
    }

    /* Grid Layout */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    /* Info Box */
    .info-box {
      background: #eff6ff;
      border: 2px solid #bfdbfe;
      border-radius: 12px;
      padding: 1rem 1.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      gap: 0.75rem;
    }

    .info-box i {
      font-size: 1.25rem;
      color: #3b82f6;
      flex-shrink: 0;
      margin-top: 0.125rem;
    }

    .info-box-content {
      font-size: 0.875rem;
      color: #1e40af;
      line-height: 1.6;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 1rem;
      }

      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .back-btn {
        width: 100%;
        margin-left: 0;
        justify-content: center;
      }

      .form-card {
        padding: 1.5rem;
      }

      .form-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }

      .form-actions {
        flex-direction: column-reverse;
      }

      .btn {
        width: 100%;
      }

      .form-switch-wrapper {
        flex-direction: column;
        align-items: flex-start;
      }

      .switch-description {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
  
  <div class="content-wrapper">
    
    <div class="page-header">
      <h1>
        <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?>"></i>
        <?= $isEdit ? 'Editar' : 'Adicionar' ?> Época
      </h1>
      <a href="listar.php" class="back-btn">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
          <strong>Erros encontrados:</strong>
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?= h($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <div class="info-box">
      <i class="bi bi-info-circle-fill"></i>
      <div class="info-box-content">
        <strong>Dica:</strong> Uma época representa um período temporal no sistema (ex: Época 2024/2025). 
        Apenas uma época pode estar ativa de cada vez. As datas ajudam a organizar e filtrar dados históricos.
      </div>
    </div>

    <div class="form-card">
      <form method="POST" action="">
        
        <div class="form-group">
          <label class="form-label" for="nome">
            Nome da Época<span class="required">*</span>
          </label>
          <input 
            type="text" 
            class="form-control" 
            id="nome" 
            name="nome" 
            placeholder="Ex: Época 2024/2025"
            value="<?= h($formNome) ?>"
            required
            autocomplete="off"
          >
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="data_inicio">
              Data de Início<span class="required">*</span>
            </label>
            <input 
              type="date" 
              class="form-control" 
              id="data_inicio" 
              name="data_inicio" 
              value="<?= h($formInicio) ?>"
              required
            >
          </div>

          <div class="form-group">
            <label class="form-label" for="data_fim">
              Data de Fim<span class="required">*</span>
            </label>
            <input 
              type="date" 
              class="form-control" 
              id="data_fim" 
              name="data_fim" 
              value="<?= h($formFim) ?>"
              required
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Estado</label>
          <div class="form-switch-wrapper">
            <div class="form-check form-switch">
              <input 
                class="form-check-input" 
                type="checkbox" 
                role="switch" 
                id="ativa" 
                name="ativa"
                <?= $formAtiva ? 'checked' : '' ?>
              >
              <label class="form-check-label" for="ativa">
                Época Ativa
              </label>
            </div>
            <span class="switch-description">
              <?= $isEdit ? 'Desative se esta época já terminou' : 'Ativa por padrão' ?>
            </span>
          </div>
        </div>

        <div class="form-actions">
          <a href="listar.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i>
            Cancelar
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle-fill"></i>
            <?= $isEdit ? 'Atualizar' : 'Criar' ?> Época
          </button>
        </div>

      </form>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validação de datas em tempo real
document.getElementById('data_inicio')?.addEventListener('change', validarDatas);
document.getElementById('data_fim')?.addEventListener('change', validarDatas);

function validarDatas() {
  const dataInicio = document.getElementById('data_inicio').value;
  const dataFim = document.getElementById('data_fim').value;
  
  if (dataInicio && dataFim) {
    if (new Date(dataInicio) > new Date(dataFim)) {
      document.getElementById('data_fim').classList.add('error');
      alert('A data de início não pode ser posterior à data de fim');
    } else {
      document.getElementById('data_fim').classList.remove('error');
    }
  }
}

// Prevenir submissão múltipla
document.querySelector('form')?.addEventListener('submit', function(e) {
  const submitBtn = this.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> A guardar...';
});
</script>
</body>
</html>