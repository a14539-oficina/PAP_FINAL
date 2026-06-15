<?php
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$club = [
    'nome' => '',
    'cidade' => '',
    'email_contacto' => '',
    'telefone' => '',
    'logo' => '',
    'ativo' => 1
];

if ($id > 0) {
    $r = $conn->query("SELECT * FROM clubs WHERE id = $id");
    if ($r && $r->num_rows > 0) {
        $club = $r->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? 'Editar Clube' : 'Novo Clube' ?> - SportGes</title>
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

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 10000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      opacity: 0;
      transition: opacity 0.3s ease;
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
      padding: 0;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
      from {
        transform: scale(0.9) translateY(-20px);
        opacity: 0;
      }
      to {
        transform: scale(1) translateY(0);
        opacity: 1;
      }
    }

    .modal-header {
      padding: 2rem;
      border-bottom: 2px solid #f1f5f9;
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
            <i class="bi bi-building-<?= $id ? 'fill-check' : 'fill-add' ?>"></i>
            <?= $id ? 'Editar Clube' : 'Novo Clube' ?>
          </h1>
          <p><?= $id ? 'Atualize as informações do clube' : 'Preencha todos os dados do novo clube' ?></p>
        </div>
        <div class="page-header-actions">
          <a href="listar.php" class="btn-header btn-back">
            <i class="bi bi-arrow-left"></i>
            Voltar
          </a>
          <?php if($id > 0 && isset($_SESSION['user_role']) && $_SESSION['user_role'] === '1'): ?>
            <button type="button" class="btn-header btn-delete" onclick="openModal()">
              <i class="bi bi-trash-fill"></i>
              Eliminar
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-container">
        <form id="mainClubForm" method="post" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="current_logo" value="<?= htmlspecialchars($club['logo']) ?>">
          <input type="hidden" name="remove_logo" id="removeLogo" value="0">

          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-image-fill"></i>
              <h2>Logo do Clube</h2>
            </div>

            <div class="logo-upload-container">
              <div class="logo-preview-wrapper <?= !empty($club['logo']) ? 'has-image' : '' ?>" id="logoPreviewWrapper">
                <div class="logo-preview" id="logoPreview">
                  <?php if(!empty($club['logo']) && file_exists("../../uploads/logos/" . $club['logo'])): ?>
                    <img src="../../uploads/logos/<?= htmlspecialchars($club['logo']) ?>" alt="Logo" id="previewImage">
                  <?php else: ?>
                    <i class="bi bi-building-fill logo-preview-placeholder" id="previewPlaceholder"></i>
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

          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-building-fill-check"></i>
              <h2>Informações do Clube</h2>
            </div>

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Nome do Clube *</label>
                <input 
                  type="text" 
                  name="nome" 
                  class="apple-input" 
                  required 
                  value="<?= htmlspecialchars($club['nome']) ?>" 
                  placeholder="Ex: Futebol Clube do Porto"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Cidade</label>
                <input 
                  type="text" 
                  name="cidade" 
                  class="apple-input" 
                  value="<?= htmlspecialchars($club['cidade']) ?>" 
                  placeholder="Ex: Porto"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Telefone</label>
                <input 
                  type="text" 
                  name="telefone" 
                  class="apple-input" 
                  value="<?= htmlspecialchars($club['telefone']) ?>" 
                  placeholder="Ex: +351 912 345 678"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Email de Contacto</label>
                <input 
                  type="email" 
                  name="email_contacto" 
                  class="apple-input" 
                  value="<?= htmlspecialchars($club['email_contacto']) ?>" 
                  placeholder="Ex: contacto@clube.pt"
                >
              </div>

              <div class="col-md-6">
                <label class="form-label">Estado</label>
                <select name="ativo" class="apple-select">
                  <option value="1" <?= $club['ativo'] == 1 ? 'selected' : '' ?>>Ativo</option>
                  <option value="0" <?= $club['ativo'] == 0 ? 'selected' : '' ?>>Inativo</option>
                </select>
              </div>
            </div>
          </div>

          <div class="submit-section">
            <button type="submit" class="main-save-btn">
              <i class="bi bi-check-circle-fill"></i>
              <?= $id ? 'Atualizar Clube' : 'Criar Clube' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <?php if($id > 0 && isset($_SESSION['user_role']) && $_SESSION['user_role'] === '1'): ?>
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
          Tem a certeza que deseja eliminar o clube <strong><?= htmlspecialchars($club['nome']) ?></strong>?
        </p>
        <p class="modal-warning">
          ⚠️ Esta ação é permanente e não pode ser desfeita. Todos os dados associados serão eliminados!
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
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../toast.js"></script>
<script>
  function previewLogoImage(input) {
    const wrapper = document.getElementById('logoPreviewWrapper');
    const preview = document.getElementById('logoPreview');
    const removeLogoInput = document.getElementById('removeLogo');
    
    if (input.files && input.files[0]) {
      const file = input.files[0];
      
      // Validar tamanho (5MB)
      if (file.size > 5 * 1024 * 1024) {
        toast.error('Erro!', 'A imagem não pode ter mais de 5MB');
        input.value = '';
        return;
      }
      
      // Validar tipo
      const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        toast.error('Erro!', 'Formato de imagem inválido. Use PNG, JPG, GIF ou WEBP');
        input.value = '';
        return;
      }
      
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.innerHTML = `<img src="${e.target.result}" alt="Logo Preview" id="previewImage">`;
        wrapper.classList.add('has-image');
        removeLogoInput.value = '0';
      };
      reader.readAsDataURL(file);
    }
  }

  function removeLogo() {
    const wrapper = document.getElementById('logoPreviewWrapper');
    const preview = document.getElementById('logoPreview');
    const input = document.getElementById('logoInput');
    const removeLogoInput = document.getElementById('removeLogo');
    
    preview.innerHTML = '<i class="bi bi-building-fill logo-preview-placeholder" id="previewPlaceholder"></i>';
    wrapper.classList.remove('has-image');
    input.value = '';
    removeLogoInput.value = '1';
    
    toast.info('Logo Removido', 'O logo será removido ao guardar');
  }

  function openModal() {
    document.getElementById('deleteModal').classList.add('active');
  }

  function closeModal() {
    document.getElementById('deleteModal').classList.remove('active');
  }

  function confirmDelete() {
    window.location.href = 'eliminar.php?id=<?= $id ?>';
  }

  const modal = document.getElementById('deleteModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeModal();
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mainClubForm');
    if (!form) return;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const isEdit = form.querySelector('[name="id"]').value > 0;
    
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const nome = this.querySelector('[name="nome"]').value.trim();
      
      if (nome.length < 3) {
        toast.warning('Atenção!', 'O nome do clube deve ter pelo menos 3 caracteres');
        return false;
      }
      
      const formData = new FormData(this);
      
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> A processar...';
      }
      fetch('gravar.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Erro na resposta do servidor');
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          toast.success('Sucesso!', data.message || 'Clube guardado com sucesso', 2000);
          setTimeout(() => {
            window.location.href = 'listar.php';
          }, 1500);
        } else {
          throw new Error(data.message || 'Erro ao guardar clube');
        }
      })
      .catch(error => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${isEdit ? 'Atualizar Clube' : 'Criar Clube'}`;
        }
        toast.error('Erro!', error.message || 'Erro ao guardar clube');
        console.error('Erro:', error);
      });
    });
  });
</script>
</body>
</html>