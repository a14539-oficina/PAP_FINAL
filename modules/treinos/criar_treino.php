<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require('../../config/db.php');

$pageTitle = 'Editar Treino';
$dashboardPath = '../../dashboard.php';
$modulesPath = '..';
$logoutPath = '../../logout.php';

$user_role = $_SESSION['user_role'] ?? '';

if(empty($user_role)) {
    header('Location: ../../login.php');
    exit;
}

$tem_permissao = in_array($user_role, ['1', '3', 'Administrador', 'Treinador']);

if(!$tem_permissao) {
    $_SESSION['erro'] = 'Sem permissão para editar treinos';
    header('Location: listar.php');
    exit;
}

$id = $_GET['id'] ?? null;
$treino = null;

if($id) {
    $stmt = $conn->prepare("SELECT * FROM training_sessions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $treino = $res->fetch_assoc();
    
    if(!$treino) {
        $_SESSION['erro'] = 'Treino não encontrado';
        header('Location: listar.php');
        exit;
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_id = $_POST['team_id'] ?? '';
    $data_treino = $_POST['data_treino'] ?? '';
    $foco = $_POST['foco'] ?? '';
    $notas = $_POST['notas'] ?? '';
    
    if(empty($team_id) || empty($data_treino) || empty($foco)) {
        $erro = 'Por favor preencha todos os campos obrigatórios';
    } else {
        if($id) {
            $stmt = $conn->prepare("UPDATE training_sessions SET team_id=?, data_treino=?, foco=?, notas=? WHERE id=?");
            $stmt->bind_param("isssi", $team_id, $data_treino, $foco, $notas, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO training_sessions (team_id, data_treino, foco, notas) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $team_id, $data_treino, $foco, $notas);
        }
        
        if($stmt->execute()) {
            $_SESSION['sucesso'] = $id ? 'Treino atualizado com sucesso!' : 'Treino criado com sucesso!';
            header('Location: listar.php');
            exit;
        } else {
            $erro = 'Erro ao guardar treino: ' . $conn->error;
        }
    }
}

// Buscar equipas com base no role do utilizador
$user_id = $_SESSION['user_id'];
$user_club_id = $_SESSION['club_id'] ?? 0;
$user_team_id = $_SESSION['team_id'] ?? 0;

// Só o utilizador 7 é SuperAdmin (vê tudo)
$isSuperAdmin = ($user_id == 7);

if ($isSuperAdmin) {
    // SuperAdmin vê todas as equipas
    $equipas = $conn->query("
        SELECT t.id, CONCAT(c.nome, ' - ', t.escaloes) AS nome
        FROM teams t
        INNER JOIN clubs c ON t.club_id = c.id
        ORDER BY c.nome, t.escaloes
    ");
} elseif (in_array($user_role, ['1', 'Administrador'])) {
    // Admin do clube vê todas as equipas do seu clube
    $stmt = $conn->prepare("
        SELECT t.id, CONCAT(c.nome, ' - ', t.escaloes) AS nome
        FROM teams t
        INNER JOIN clubs c ON t.club_id = c.id
        WHERE t.club_id = ?
        ORDER BY t.escaloes
    ");
    $stmt->bind_param("i", $user_club_id);
    $stmt->execute();
    $equipas = $stmt->get_result();
} else {
    // Treinador vê SÓ a sua equipa
    $stmt = $conn->prepare("
        SELECT t.id, CONCAT(c.nome, ' - ', t.escaloes) AS nome
        FROM teams t
        INNER JOIN clubs c ON t.club_id = c.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $user_team_id);
    $stmt->execute();
    $equipas = $stmt->get_result();
}

$ASSET_BASE = '../../';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? 'Editar Treino' : 'Novo Treino' ?> — SportGes</title>

  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="icon" href="<?= $ASSET_BASE ?>assets/favicon.png" type="image/png">
  
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

    .toast-icon {
      font-size: 1.5rem;
    }

    .toast.success .toast-icon { color: #10b981; }
    .toast.error .toast-icon { color: #ef4444; }

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

    .apple-input,
    .apple-select,
    .apple-textarea {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 1rem;
      color: #1e293b;
      background: white;
      transition: all 0.2s;
      font-family: inherit;
    }

    .apple-input:focus,
    .apple-select:focus,
    .apple-textarea:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .apple-input::placeholder,
    .apple-textarea::placeholder {
      color: #94a3b8;
    }

    .apple-select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L3 5h8z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      padding-right: 3rem;
    }

    /* Select desativado para treinador */
    .apple-select:disabled {
      background-color: #f1f5f9;
      color: #64748b;
      cursor: not-allowed;
      opacity: 1;
    }

    .apple-textarea {
      resize: vertical;
      min-height: 120px;
    }

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

    .team-locked-info {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.5rem;
      font-size: 0.8125rem;
      color: #64748b;
    }

    .team-locked-info i {
      color: #94a3b8;
    }

    /* RESPONSIVO COMPLETO */
    @media (max-width: 1023px) {
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

      .page-header-left h1 i {
        font-size: 2rem;
      }

      .form-section {
        padding: 2rem 1.5rem;
      }

      .section-header h2 {
        font-size: 1.25rem;
      }

      .submit-section {
        padding: 2rem 1.5rem;
      }
    }

    @media (max-width: 767px) {
      .main-content {
        padding: 1rem;
      }

      .page-header {
        padding: 1.25rem;
        flex-direction: column;
        align-items: flex-start;
      }

      .page-header-left h1 {
        font-size: 1.35rem;
      }

      .page-header-left h1 i {
        font-size: 1.75rem;
      }

      .page-header-left p {
        font-size: 0.875rem;
      }

      .page-header-actions {
        width: 100%;
      }

      .btn-header {
        width: 100%;
        justify-content: center;
        padding: 0.75rem 1.5rem;
      }

      .form-section {
        padding: 1.5rem 1rem;
      }

      .section-header {
        flex-wrap: wrap;
      }

      .section-header i {
        font-size: 1.5rem;
      }

      .section-header h2 {
        font-size: 1.125rem;
      }

      .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
      }

      .apple-input,
      .apple-select,
      .apple-textarea {
        padding: 0.75rem 0.875rem;
        font-size: 16px;
      }

      input[type="datetime-local"] {
        min-height: 44px;
        -webkit-appearance: none;
        appearance: none;
      }

      .apple-textarea {
        min-height: 100px;
      }

      .submit-section {
        padding: 1.5rem 1rem;
      }

      .main-save-btn {
        width: 100%;
        padding: 1rem 2rem;
        font-size: 1rem;
        justify-content: center;
      }

      .toast-container {
        top: 10px;
        right: 10px;
        left: 10px;
      }

      .toast {
        min-width: auto;
        width: 100%;
        max-width: 100%;
      }
    }

    @media (max-width: 480px) {
      .page-header-left h1 {
        font-size: 1.25rem;
      }

      .page-header-left h1 i {
        font-size: 1.5rem;
      }

      .section-header h2 {
        font-size: 1rem;
      }

      .apple-input,
      .apple-select,
      .apple-textarea {
        padding: 0.625rem 0.75rem;
        font-size: 16px;
      }

      input[type="datetime-local"] {
        display: block;
        width: 100%;
        min-height: 48px;
        line-height: 1.5;
      }

      .main-save-btn {
        padding: 0.875rem 1.5rem;
        font-size: 0.9375rem;
      }
    }

    @media (max-width: 430px) {
      input[type="datetime-local"] {
        font-size: 16px !important;
        padding: 0.75rem !important;
        min-height: 50px;
        background-color: white;
      }

      input[type="datetime-local"]::-webkit-datetime-edit {
        padding: 0;
      }

      input[type="datetime-local"]::-webkit-calendar-picker-indicator {
        margin-left: 4px;
        cursor: pointer;
      }
    }
  </style>
</head>

<body>
  <?php require('../../includes/sidebar.php'); ?>

  <div class="toast-container" id="toastContainer"></div>

  <div class="main-content">
    <div class="content-wrapper">
      
      <div class="page-header">
        <div class="page-header-left">
          <h1>
            <i class="bi bi-calendar-<?= $id ? 'check' : 'plus' ?>"></i>
            <?= $id ? 'Editar Treino' : 'Novo Treino' ?>
          </h1>
          <p><?= $id ? 'Atualize as informações do treino' : 'Preencha todos os dados do novo treino' ?></p>
        </div>
        <div class="page-header-actions">
          <a href="listar.php" class="btn-header btn-back">
            <i class="bi bi-arrow-left"></i>
            Voltar
          </a>
        </div>
      </div>

      <div class="form-container">
        <form method="POST">
          
          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-calendar-event-fill"></i>
              <h2>Informações do Treino</h2>
            </div>

            <?php if(isset($erro)): ?>
              <div class="alert alert-danger mb-4">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($erro) ?>
              </div>
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Equipa *</label>

                <?php
                // Verificar se é treinador (não é superadmin nem admin)
                $isTreinador = !$isSuperAdmin && !in_array($user_role, ['1', 'Administrador']);
                ?>

                <?php if($isTreinador): ?>
                  <?php
                  // Buscar nome da equipa do treinador para mostrar
                  $eq_info = $equipas->fetch_assoc();
                  ?>
                  <!-- Campo hidden para submeter o valor -->
                  <input type="hidden" name="team_id" value="<?= htmlspecialchars($eq_info['id'] ?? $user_team_id) ?>">
                  <!-- Campo visual desativado -->
                  <select class="apple-select" disabled>
                    <option selected><?= htmlspecialchars($eq_info['nome'] ?? 'Sem equipa atribuída') ?></option>
                  </select>
                  <div class="team-locked-info">
                    <i class="bi bi-lock-fill"></i>
                    A equipa é definida automaticamente pela tua conta
                  </div>
                <?php else: ?>
                  <select name="team_id" class="apple-select" required>
                    <option value="">Selecione uma equipa</option>
                    <?php while($eq = $equipas->fetch_assoc()): ?>
                      <option value="<?= $eq['id'] ?>" <?= ($treino && $treino['team_id'] == $eq['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($eq['nome']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                <?php endif; ?>
              </div>

              <div class="col-md-6">
                <label class="form-label">Data e Hora *</label>
                <input 
                  type="datetime-local" 
                  name="data_treino" 
                  class="apple-input" 
                  required
                  value="<?= $treino ? date('Y-m-d\TH:i', strtotime($treino['data_treino'])) : '' ?>"
                >
              </div>

              <div class="col-12">
                <label class="form-label">Foco do Treino *</label>
                <input 
                  type="text" 
                  name="foco" 
                  class="apple-input" 
                  required
                  placeholder="Ex: Táctica, Físico, Técnica..."
                  value="<?= $treino ? htmlspecialchars($treino['foco']) : '' ?>"
                >
              </div>

              <div class="col-12">
                <label class="form-label">Observações</label>
                <textarea 
                  name="notas" 
                  class="apple-textarea" 
                  placeholder="Detalhes adicionais sobre o treino..."
                ><?= $treino ? htmlspecialchars($treino['notas']) : '' ?></textarea>
              </div>
            </div>
          </div>

          <div class="submit-section">
            <button type="submit" class="main-save-btn">
              <i class="bi bi-check-circle-fill"></i>
              <?= $id ? 'Atualizar Treino' : 'Criar Treino' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function showToast(type, title, message) {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      
      const icons = {
        success: 'bi-check-circle-fill',
        error: 'bi-x-circle-fill'
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

    <?php if(isset($_SESSION['sucesso'])): ?>
      showToast('success', 'Sucesso!', '<?= $_SESSION['sucesso'] ?>');
      <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['erro'])): ?>
      showToast('error', 'Erro!', '<?= $_SESSION['erro'] ?>');
      <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>
  </script>
</body>
</html>