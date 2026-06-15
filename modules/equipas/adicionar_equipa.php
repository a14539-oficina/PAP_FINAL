<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

require('../../config/db.php');

// Obter o club_id da sessão
$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;

// Verificar se o utilizador tem clube associado
if ($user_club_id <= 0) {
    die("Erro: Utilizador sem clube associado. Contacte o administrador.");
}

// Buscar APENAS o clube do utilizador para o select
$clubsQuery = $conn->prepare("SELECT id, nome FROM clubs WHERE id = ? AND ativo = 1");
$clubsQuery->bind_param("i", $user_club_id);
$clubsQuery->execute();
$clubsResult = $clubsQuery->get_result();
$clubs = [];
while ($row = $clubsResult->fetch_assoc()) {
  $clubs[] = $row;
}
$clubsQuery->close();

// Buscar equipa, se existir (e verificar se pertence ao clube do utilizador)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$team = ['club_id' => $user_club_id, 'escaloes' => '', 'ativo' => 1];

if ($id > 0) {
  $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ? AND club_id = ?");
  $stmt->bind_param("ii", $id, $user_club_id);
  $stmt->execute();
  $res = $stmt->get_result();
  
  if ($res && $res->num_rows > 0) {
    $team = $res->fetch_assoc();
  } else {
    // Equipa não existe ou não pertence ao clube do utilizador
    $_SESSION['error'] = 'Equipa não encontrada ou não tens permissão para editá-la.';
    header("Location: listar.php");
    exit;
  }
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? 'Editar Equipa' : 'Nova Equipa' ?> - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
  
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

    .btn-delete {
      background: #fee2e2;
      color: #dc2626;
    }

    .btn-delete:hover {
      background: #fecaca;
      color: #b91c1c;
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
    .apple-select {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 1rem;
      color: #1e293b;
      background: white;
      transition: all 0.2s;
    }

    .apple-input:focus,
    .apple-select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .apple-input::placeholder {
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

    /* Campo desabilitado */
    .apple-select:disabled {
      background-color: #f8fafc;
      cursor: not-allowed;
      opacity: 0.7;
    }

    /* Select2 Custom Styling */
    .select2-container--bootstrap-5 .select2-selection {
      border: 2px solid #e2e8f0 !important;
      border-radius: 10px !important;
      min-height: 54px !important;
      height: auto !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single {
      height: 54px !important;
      padding: 0 !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
      padding: 0.875rem 1rem !important;
      line-height: 1.5 !important;
      font-size: 1rem !important;
      color: #1e293b !important;
      display: flex !important;
      align-items: center !important;
      height: 100% !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
      color: #94a3b8 !important;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
      height: 100% !important;
      top: 0 !important;
      right: 10px !important;
      display: flex !important;
      align-items: center !important;
    }

    .select2-container--bootstrap-5.select2-container--focus .select2-selection,
    .select2-container--bootstrap-5.select2-container--open .select2-selection {
      border-color: #3b82f6 !important;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
    }

    .select2-container--bootstrap-5 .select2-dropdown {
      border: 2px solid #3b82f6;
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
      margin-top: 4px;
    }

    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      padding: 0.625rem;
      font-size: 0.9375rem;
    }

    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field:focus {
      border-color: #3b82f6;
      outline: none;
    }

    .select2-container--bootstrap-5 .select2-results__option {
      padding: 0.75rem 1rem;
      font-size: 1rem;
    }

    .select2-container--bootstrap-5 .select2-results__option--highlighted {
      background-color: #3b82f6 !important;
      color: white !important;
    }

    .select2-container--bootstrap-5 .select2-results__option[aria-selected="true"] {
      background-color: #eff6ff !important;
      color: #1e40af !important;
    }

    /* Select2 disabled */
    .select2-container--bootstrap-5 .select2-selection--single[aria-disabled="true"] {
      background-color: #f8fafc !important;
      cursor: not-allowed !important;
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

    .main-save-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    }

    .main-save-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(4px);
      z-index: 10000;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 1;
    }

    .modal-content {
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      width: 90%;
      max-width: 500px;
      animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
      from {
        transform: translateY(50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .modal-header {
      padding: 2rem 2rem 1rem 2rem;
      border-bottom: none;
    }

    .modal-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .modal-title i {
      color: #dc2626;
      font-size: 1.75rem;
    }

    .modal-body {
      padding: 2rem;
    }

    .modal-text {
      font-size: 1rem;
      color: #475569;
      line-height: 1.6;
      margin: 0 0 1rem 0;
    }

    .modal-text strong {
      color: #0f172a;
      font-weight: 700;
    }

    .modal-warning {
      background: #fef3c7;
      border-left: 4px solid #f59e0b;
      padding: 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      color: #92400e;
      margin: 0;
    }

    .modal-footer {
      padding: 1.5rem 2rem;
      background: #f8fafc;
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      border-bottom-left-radius: 16px;
      border-bottom-right-radius: 16px;
    }

    .modal-btn {
      padding: 0.875rem 1.75rem;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .modal-btn-cancel {
      background: #e2e8f0;
      color: #475569;
    }

    .modal-btn-cancel:hover {
      background: #cbd5e1;
      transform: translateY(-1px);
    }

    .modal-btn-confirm {
      background: #dc2626;
      color: white;
    }

    .modal-btn-confirm:hover {
      background: #b91c1c;
      transform: translateY(-1px);
    }

    .club-info-badge {
      background: #eff6ff;
      border: 2px solid #93c5fd;
      padding: 1rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .club-info-badge i {
      font-size: 1.5rem;
      color: #3b82f6;
    }

    .club-info-badge span {
      font-size: 0.9375rem;
      color: #1e40af;
      font-weight: 600;
    }

    /* Responsive */
    @media (max-width: 1023px) {
      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 1.5rem;
      }
    }
  </style>
</head>

<?php require('../../includes/sidebar.php'); ?>

<body>
  <div class="main-content">
    <div class="content-wrapper">
      
      <div class="page-header">
        <div class="page-header-left">
          <h1>
            <i class="bi bi-shield-<?= $id ? 'fill-check' : 'plus' ?>"></i>
            <?= $id ? 'Editar Equipa' : 'Nova Equipa' ?>
          </h1>
          <p><?= $id ? 'Atualize as informações da equipa do seu clube' : 'Preencha todos os dados da nova equipa do seu clube' ?></p>
        </div>
        <div class="page-header-actions">
          <a href="listar.php" class="btn-header btn-back">
            <i class="bi bi-arrow-left"></i>
            Voltar
          </a>
          <?php if($id > 0): ?>
            <button type="button" class="btn-header btn-delete" onclick="openModal()">
              <i class="bi bi-trash-fill"></i>
              Eliminar
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-container">
        <form id="mainTeamForm">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="club_id" value="<?= $user_club_id ?>">

          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-shield-fill-check"></i>
              <h2>Informações da Equipa</h2>
            </div>

            <?php if (count($clubs) > 0): ?>
              <div class="club-info-badge">
                <i class="bi bi-building-fill"></i>
                <span>Clube: <?= htmlspecialchars($clubs[0]['nome']) ?></span>
              </div>
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Escalão *</label>
                <input 
                  type="text" 
                  name="escaloes"    
                  class="apple-input" 
                  required 
                  value="<?= htmlspecialchars($team['escaloes']) ?>" 
                  placeholder="Ex: Juvenis A, Sub-17"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="ativo" class="apple-select">
                  <option value="1" <?= $team['ativo'] ? 'selected' : '' ?>>Ativa</option>
                  <option value="0" <?= !$team['ativo'] ? 'selected' : '' ?>>Inativa</option>
                </select>
              </div>
            </div>
          </div>

          <div class="submit-section">
            <button type="submit" class="main-save-btn">
              <i class="bi bi-check-circle-fill"></i>
              <?= $id ? 'Atualizar Equipa' : 'Criar Equipa' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-exclamation-triangle-fill"></i>
          Confirmar Eliminação
        </h5>
      </div>
      <div class="modal-body">
        <p class="modal-text">
          Tem a certeza que deseja eliminar esta equipa?
        </p>
        <p class="modal-warning">
          ⚠️ Esta ação é permanente e não pode ser desfeita.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal()">
          <i class="bi bi-x-lg"></i>
          Não
        </button>
        <button type="button" class="modal-btn modal-btn-confirm" onclick="confirmDelete()">
          <i class="bi bi-trash-fill"></i>
          Sim, Eliminar
        </button>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../toast.js"></script>
  
<script>
  function openModal() {
    document.getElementById('deleteModal').classList.add('active');
  }

  function closeModal() {
    document.getElementById('deleteModal').classList.remove('active');
  }

  function confirmDelete() {
    window.location.href = 'remover.php?id=<?= $id ?>';
  }

  document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeModal();
    }
  });

  // Submit do formulário com Toast
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mainTeamForm');
    if (!form) return;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Criar FormData
      const formData = new FormData(this);
      
      // Debug: ver o que está sendo enviado
      console.log('Dados enviados:');
      for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
      }
      
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> A processar...';
      }
      
      fetch('gravar.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        console.log('Resposta:', data);
        if (data.success) {
          toast.success('Sucesso!', data.message || 'Equipa guardada com sucesso');
          setTimeout(() => {
            window.location.href = 'listar.php';
          }, 1500);
        } else {
          throw new Error(data.message || 'Erro ao guardar equipa');
        }
      })
      .catch(error => {
        console.error('Erro:', error);
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> <?= $id ? 'Atualizar Equipa' : 'Criar Equipa' ?>';
        }
        toast.error('Erro!', error.message || 'Erro ao guardar equipa');
      });
    });
  });
  </script>
</body>
</html>