<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

require('../../config/db.php');

// Buscar equipa, se existir
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$team = ['nome' => '', 'escaloes' => '', 'ativo' => 1, 'logo' => ''];
$isEdit = false;

if ($id > 0) {
  $res = $conn->query("SELECT * FROM teams WHERE id = $id");
  if ($res && $res->num_rows > 0) {
    $team = $res->fetch_assoc();
    $isEdit = true;
  }
}

$ASSET_BASE = '../../';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? 'Editar Equipa' : 'Nova Equipa' ?> — SportGes</title>

  <!-- Ícones e Estilos -->
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- Favicon (opcional, se existir) -->
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
    .toast.warning { border-left: 4px solid #f59e0b; }
    .toast.info { border-left: 4px solid #3b82f6; }

    .toast-icon {
      font-size: 1.5rem;
    }

    .toast.success .toast-icon { color: #10b981; }
    .toast.error .toast-icon { color: #ef4444; }
    .toast.warning .toast-icon { color: #f59e0b; }
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

    .readonly-badge {
      background: #fef3c7;
      color: #92400e;
      padding: 0.375rem 0.875rem;
      border-radius: 20px;
      font-size: 0.8125rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.375rem;
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

    /* Campos bloqueados */
    .apple-input.locked,
    .apple-select.locked {
      background: #f8fafc;
      color: #64748b;
      cursor: not-allowed;
      border-color: #cbd5e1;
    }

    .readonly-notice {
      background: #fef3c7;
      border-left: 4px solid #f59e0b;
      padding: 1.25rem 1.5rem;
      border-radius: 10px;
      margin-bottom: 2rem;
      display: flex;
      align-items: start;
      gap: 1rem;
    }

    .readonly-notice i {
      color: #f59e0b;
      font-size: 1.5rem;
      margin-top: 0.125rem;
      flex-shrink: 0;
    }

    .readonly-notice-content {
      flex: 1;
    }

    .readonly-notice-title {
      font-weight: 700;
      color: #92400e;
      margin-bottom: 0.375rem;
      font-size: 1rem;
    }

    .readonly-notice-text {
      font-size: 0.875rem;
      color: #78350f;
      margin: 0;
      line-height: 1.5;
    }

    /* Logo Upload Styles */
    .logo-upload-container {
      display: flex;
      align-items: center;
      gap: 2rem;
      flex-wrap: wrap;
    }

    .logo-preview-wrapper {
      position: relative;
      flex-shrink: 0;
    }

    .logo-preview {
      width: 150px;
      height: 150px;
      border-radius: 16px;
      border: 3px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: #f8fafc;
      position: relative;
    }

    .logo-preview img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      padding: 10px;
    }

    .logo-preview-placeholder {
      color: #cbd5e1;
      font-size: 4rem;
    }

    .remove-logo-btn {
      position: absolute;
      top: -8px;
      right: -8px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #dc2626;
      color: white;
      border: 3px solid white;
      cursor: pointer;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 0.875rem;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .remove-logo-btn:hover {
      background: #b91c1c;
      transform: scale(1.1);
    }

    .logo-preview-wrapper.has-image .remove-logo-btn {
      display: flex;
    }

    .logo-upload-controls {
      flex: 1;
      min-width: 200px;
    }

    .upload-btn-wrapper {
      position: relative;
      display: inline-block;
      width: 100%;
    }

    .upload-btn {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      padding: 1rem 2rem;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.625rem;
      transition: all 0.3s;
      width: 100%;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .upload-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    .upload-btn input[type="file"] {
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      cursor: pointer;
    }

    .upload-info {
      margin-top: 1rem;
      font-size: 0.875rem;
      color: #64748b;
      line-height: 1.6;
    }

    .upload-info i {
      color: #3b82f6;
      margin-right: 0.25rem;
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

    /* Responsive */
    @media (max-width: 1023px) {
      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 1.5rem;
      }

      .logo-upload-container {
        flex-direction: column;
        align-items: center;
      }

      .logo-upload-controls {
        width: 100%;
      }
    }

    @media (max-width: 599px) {
      .logo-preview {
        width: 120px;
        height: 120px;
      }

      .logo-preview-placeholder {
        font-size: 3rem;
      }
    }
  </style>
</head>

<?php require('../../includes/sidebar.php'); ?>

<body>
  <div class="toast-container" id="toastContainer"></div>

  <div class="main-content">
    <div class="content-wrapper">
      
      <div class="page-header">
        <div class="page-header-left">
          <h1>
            <i class="bi bi-shield-<?= $id ? 'fill-check' : 'plus' ?>"></i>
            <?= $id ? 'Editar Equipa' : 'Nova Equipa' ?>
          </h1>
          <p><?= $id ? 'Atualize o status da equipa' : 'Preencha todos os dados da nova equipa' ?></p>
        </div>
        <div class="page-header-actions">
          <a href="listar.php" class="btn-header btn-back">
            <i class="bi bi-arrow-left"></i>
            Voltar
          </a>
        </div>
      </div>

      <?php if($isEdit): ?>
      <div class="readonly-notice">
        <i class="bi bi-lock-fill"></i>
        <div class="readonly-notice-content">
          <div class="readonly-notice-title">⚠️ Modo de Edição - Campos Protegidos</div>
          <p class="readonly-notice-text">
            Por razões de segurança e integridade dos dados, o <strong>nome da equipa</strong> e <strong>escalão</strong> 
            não podem ser alterados após a criação. Apenas o <strong>status</strong> pode ser modificado.
          </p>
        </div>
      </div>
      <?php endif; ?>

      <div class="form-container">
        <form id="mainTeamForm" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="current_logo" value="<?= htmlspecialchars($team['logo']) ?>">
          <input type="hidden" name="remove_logo" id="removeLogo" value="0">

          <?php if(!$isEdit): ?>
          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-image-fill"></i>
              <h2>Logo da Equipa</h2>
            </div>

            <div class="logo-upload-container" id="logoContainer">
              <div class="logo-preview-wrapper <?= !empty($team['logo']) ? 'has-image' : '' ?>" id="logoPreviewWrapper">
                <div class="logo-preview" id="logoPreview">
                  <?php if(!empty($team['logo']) && file_exists("logos/" . $team['logo'])): ?>
                    <img src="logos/<?= htmlspecialchars($team['logo']) ?>" alt="Logo" id="previewImage">
                  <?php else: ?>
                    <i class="bi bi-shield-fill logo-preview-placeholder" id="previewPlaceholder"></i>
                  <?php endif; ?>
                </div>
                <button type="button" class="remove-logo-btn" onclick="removeLogo()">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>

              <div class="logo-upload-controls">
                <div class="upload-btn-wrapper">
                  <button type="button" class="upload-btn">
                    <i class="bi bi-cloud-upload-fill"></i>
                    <span>Escolher Imagem</span>
                    <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" onchange="previewLogoImage(this)">
                  </button>
                </div>
                <div class="upload-info">
                  <div><i class="bi bi-info-circle-fill"></i> Formatos aceites: PNG, JPG, GIF, WEBP</div>
                  <div><i class="bi bi-info-circle-fill"></i> Tamanho máximo: 5MB</div>
                  <div><i class="bi bi-info-circle-fill"></i> Recomendado: imagem quadrada</div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-shield-fill-check"></i>
              <h2>Informações da Equipa</h2>
            </div>

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Nome da Equipa *</label>
                <input 
                  type="text" 
                  name="nome"    
                  class="apple-input <?= $isEdit ? 'locked' : '' ?>" 
                  <?= $isEdit ? 'readonly' : 'required' ?>
                  value="<?= htmlspecialchars($team['nome']) ?>" 
                  placeholder="Ex: FC Porto Juvenis A"
                >
              </div>
              
              <div class="col-12">
                <label class="form-label">Escalão *</label>
                <input 
                  type="text" 
                  name="escaloes"    
                  class="apple-input <?= $isEdit ? 'locked' : '' ?>" 
                  <?= $isEdit ? 'readonly' : 'required' ?>
                  value="<?= htmlspecialchars($team['escaloes']) ?>" 
                  placeholder="Ex: Juvenis A"
                >
              </div>
              
              <div class="col-12">
                <label class="form-label">Status da Equipa</label>
                <select name="ativo" class="apple-select">
                  <option value="1" <?= $team['ativo'] ? 'selected' : '' ?>>✅ Ativa</option>
                  <option value="0" <?= !$team['ativo'] ? 'selected' : '' ?>>⛔ Inativa</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="submit-section">
            <button type="submit" class="main-save-btn">
              <i class="bi bi-check-circle-fill"></i>
              <?= $id ? 'Atualizar Status da Equipa' : 'Criar Nova Equipa' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  const isEditMode = <?= $isEdit ? 'true' : 'false' ?>;

  function previewLogoImage(input) {
    if (isEditMode) {
      return;
    }

    const wrapper = document.getElementById('logoPreviewWrapper');
    const preview = document.getElementById('logoPreview');
    const removeLogo = document.getElementById('removeLogo');
    
    if (input.files && input.files[0]) {
      const file = input.files[0];
      
      if (file.size > 5 * 1024 * 1024) {
        showToast('error', 'Erro!', 'A imagem não pode ter mais de 5MB');
        input.value = '';
        return;
      }
      
      const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        showToast('error', 'Erro!', 'Formato de imagem inválido');
        input.value = '';
        return;
      }
      
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.innerHTML = `<img src="${e.target.result}" alt="Logo Preview" id="previewImage">`;
        wrapper.classList.add('has-image');
        removeLogo.value = '0';
      };
      reader.readAsDataURL(file);
    }
  }

  function removeLogo() {
    if (isEditMode) {
      return;
    }

    const wrapper = document.getElementById('logoPreviewWrapper');
    const preview = document.getElementById('logoPreview');
    const input = document.getElementById('logoInput');
    const removeLogo = document.getElementById('removeLogo');
    
    preview.innerHTML = '<i class="bi bi-shield-fill logo-preview-placeholder" id="previewPlaceholder"></i>';
    wrapper.classList.remove('has-image');
    input.value = '';
    removeLogo.value = '1';
    
    showToast('info', 'Logo Removido', 'O logo será removido ao guardar');
  }

  function showToast(type, title, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
      success: 'bi-check-circle-fill',
      error: 'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
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
    const form = document.getElementById('mainTeamForm');
    if (!form) return;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      
      // Em modo de edição, garantir que não envia dados bloqueados
      if (isEditMode) {
        formData.delete('logo');
        formData.delete('remove_logo');
        formData.delete('nome');
        formData.delete('escaloes');
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
        if (data.success) {
          showToast('success', 'Sucesso!', data.message || 'Equipa guardada com sucesso');
          setTimeout(() => {
            window.location.href = 'listar.php';
          }, 1500);
        } else {
          throw new Error(data.message || 'Erro ao guardar equipa');
        }
      })
      .catch(error => {
        if (submitBtn) {
          submitBtn.disabled = false;
          const btnText = isEditMode ? 'Atualizar Status da Equipa' : 'Criar Nova Equipa';
          submitBtn.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${btnText}`;
        }
        showToast('error', 'Erro!', error.message || 'Erro ao guardar equipa');
      });
    });
  });
  </script>
</body>
</html>