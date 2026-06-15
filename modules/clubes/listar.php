<?php
session_start();
require('../../config/db.php');

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id      = $_SESSION['user_id'];
$user_role    = $_SESSION['user_role'] ?? '';

// Super Admin: ID 7 OU role 0
$isSuperAdmin = ($user_id == 7 || $user_role == '0');

if ($user_club_id <= 0 && !$isSuperAdmin) {
  die("Erro: Utilizador sem clube associado. Contacte o administrador.");
}

if ($isSuperAdmin) {
  $sql = "SELECT * FROM clubs ORDER BY nome ASC";
} else {
  $sql = "SELECT * FROM clubs WHERE id = $user_club_id ORDER BY nome ASC";
}

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clubes - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

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

    .content-wrapper { max-width: 1400px; margin: 0 auto; }

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

    .page-header-left h1 i { font-size: 2.5rem; color: #3b82f6; }
    .page-header-left p { color: #64748b; margin: 0; font-size: 1rem; }
    .page-header-actions { display: flex; gap: 1rem; flex-wrap: wrap; }

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

    .btn-add {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(59,130,246,0.3);
    }

    .btn-add:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59,130,246,0.4);
      color: white;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: all 0.3s;
    }

    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

    .stat-icon {
      width: 56px; height: 56px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.75rem; flex-shrink: 0;
    }

    .stat-icon.blue   { background: linear-gradient(135deg,#dbeafe,#bfdbfe); color:#2563eb; }
    .stat-icon.green  { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color:#059669; }
    .stat-icon.orange { background: linear-gradient(135deg,#fed7aa,#fdba74); color:#ea580c; }

    .stat-label { font-size:.875rem; color:#64748b; font-weight:500; margin-bottom:.25rem; }
    .stat-value { font-size:1.875rem; font-weight:700; color:#0f172a; }

    .filters-container {
      background: white;
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .filters-row { display:flex; gap:1rem; flex-wrap:wrap; align-items:center; }

    .search-box { flex:1; min-width:250px; position:relative; }
    .search-box i { position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:1.125rem; }

    .search-input {
      width:100%; padding:.875rem 1rem .875rem 3rem;
      border:2px solid #e2e8f0; border-radius:12px;
      font-size:1rem; color:#1e293b; transition:all .2s;
    }
    .search-input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 4px rgba(59,130,246,.1); }

    .filter-select {
      padding:.875rem 3rem .875rem 1rem;
      border:2px solid #e2e8f0; border-radius:12px;
      font-size:1rem; color:#1e293b; cursor:pointer; appearance:none;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L3 5h8z'/%3E%3C/svg%3E");
      background-repeat:no-repeat; background-position:right 1rem center; transition:all .2s;
    }
    .filter-select:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 4px rgba(59,130,246,.1); }

    .clubs-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }

    .club-card {
      background: white; border-radius:16px; overflow:hidden;
      box-shadow:0 1px 3px rgba(0,0,0,.06);
      transition:all .3s; display:flex; flex-direction:column;
    }
    .club-card:hover { transform:translateY(-8px); box-shadow:0 12px 24px rgba(0,0,0,.1); }

    .club-card-header {
      padding:1.5rem;
      background:linear-gradient(135deg,#f8fafc,#f1f5f9);
      display:flex; align-items:center; gap:1rem;
      border-bottom:2px solid #e2e8f0;
    }

    .club-logo {
      width:80px; height:80px; border-radius:12px;
      border:3px solid white; display:flex; align-items:center; justify-content:center;
      overflow:hidden; background:white; flex-shrink:0;
      box-shadow:0 2px 8px rgba(0,0,0,.08);
    }
    .club-logo img { width:100%; height:100%; object-fit:contain; padding:8px; }
    .club-logo-placeholder { font-size:2.5rem; color:#cbd5e1; }

    .club-header-info { flex:1; min-width:0; }
    .club-name { font-size:1.25rem; font-weight:700; color:#0f172a; margin:0 0 .25rem 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    .club-status {
      display:inline-flex; align-items:center; gap:.375rem;
      padding:.375rem .75rem; border-radius:8px; font-size:.8125rem; font-weight:600;
    }
    .club-status.active   { background:#d1fae5; color:#065f46; }
    .club-status.inactive { background:#fee2e2; color:#991b1b; }
    .club-status i { font-size:.625rem; }

    .club-card-body { padding:1.5rem; flex:1; display:flex; flex-direction:column; gap:.875rem; }

    .club-info-item { display:flex; align-items:center; gap:.75rem; color:#475569; font-size:.9375rem; }
    .club-info-item i { font-size:1.125rem; color:#64748b; width:20px; text-align:center; flex-shrink:0; }
    .club-info-text { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; }

    .club-card-footer {
      padding:1rem 1.5rem; background:#f8fafc;
      display:flex; gap:.75rem; border-top:2px solid #f1f5f9;
    }

    .card-btn {
      flex:1; padding:.75rem 1rem; border-radius:10px;
      font-size:.9375rem; font-weight:600; border:none; cursor:pointer;
      display:flex; align-items:center; justify-content:center; gap:.5rem;
      transition:all .2s; text-decoration:none;
    }
    .card-btn-edit   { background:#eff6ff; color:#2563eb; }
    .card-btn-edit:hover { background:#dbeafe; transform:translateY(-2px); color:#2563eb; }
    .card-btn-delete { background:#fee2e2; color:#dc2626; }
    .card-btn-delete:hover { background:#fecaca; transform:translateY(-2px); color:#dc2626; }

    .empty-state {
      text-align:center; padding:4rem 2rem; background:white;
      border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.06);
    }
    .empty-state-icon { font-size:5rem; color:#cbd5e1; margin-bottom:1.5rem; }
    .empty-state h3 { font-size:1.5rem; font-weight:700; color:#0f172a; margin-bottom:.75rem; }
    .empty-state p  { font-size:1rem; color:#64748b; margin-bottom:2rem; }

    /* Modal */
    .modal {
      display:none; position:fixed; z-index:10000;
      left:0; top:0; width:100%; height:100%;
      background:rgba(0,0,0,.5); opacity:0; transition:opacity .3s ease;
    }
    .modal.active { display:flex; align-items:center; justify-content:center; opacity:1; }

    .modal-content {
      background:white; border-radius:16px; padding:0;
      max-width:500px; width:90%;
      box-shadow:0 20px 60px rgba(0,0,0,.3);
      animation:modalSlideIn .3s ease;
    }

    @keyframes modalSlideIn {
      from { transform:scale(.9) translateY(-20px); opacity:0; }
      to   { transform:scale(1) translateY(0); opacity:1; }
    }

    .modal-header { padding:2rem; border-bottom:2px solid #f1f5f9; }
    .modal-title { font-size:1.5rem; font-weight:700; color:#0f172a; margin:0; display:flex; align-items:center; gap:.75rem; }
    .modal-title i { color:#dc2626; font-size:1.75rem; }
    .modal-body { padding:2rem; }
    .modal-text { font-size:1rem; color:#475569; line-height:1.6; margin:0 0 1rem 0; }
    .modal-text strong { color:#0f172a; font-weight:700; }
    .modal-warning { background:#fef3c7; border-left:4px solid #f59e0b; padding:1rem; border-radius:8px; font-size:.875rem; color:#92400e; }

    .modal-footer {
      padding:1.5rem 2rem; background:#f8fafc;
      display:flex; gap:1rem; justify-content:flex-end;
      border-bottom-left-radius:16px; border-bottom-right-radius:16px;
    }

    .modal-btn { padding:.875rem 1.75rem; border-radius:10px; font-size:1rem; font-weight:600; cursor:pointer; border:none; transition:all .2s; display:flex; align-items:center; gap:.5rem; }
    .modal-btn-cancel  { background:#e2e8f0; color:#475569; }
    .modal-btn-cancel:hover  { background:#cbd5e1; transform:translateY(-1px); }
    .modal-btn-confirm { background:#dc2626; color:white; }
    .modal-btn-confirm:hover { background:#b91c1c; transform:translateY(-1px); }

    @media (max-width:1023px) { .main-content { margin-left:0; width:100%; padding:1.5rem; } .clubs-grid { grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); } }
    @media (max-width:599px) {
      .page-header { padding:1.5rem; }
      .page-header-left h1 { font-size:1.5rem; }
      .stats-grid { grid-template-columns:1fr; }
      .filters-row { flex-direction:column; }
      .search-box, .filter-select { width:100%; }
      .clubs-grid { grid-template-columns:1fr; }
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
            <i class="bi bi-building-fill"></i>
            Gestão de Clubes
          </h1>
          <p><?= $isSuperAdmin ? 'Visualize e gerencie todos os clubes do sistema' : 'Visualize e gerencie o seu clube' ?></p>
        </div>
        <div class="page-header-actions">
          <?php if ($isSuperAdmin): ?>
            <a href="editar.php" class="btn-header btn-add">
              <i class="bi bi-plus-circle-fill"></i>
              Adicionar Clube
            </a>
          <?php endif; ?>
        </div>
      </div>

      <?php
      if ($isSuperAdmin) {
        $total    = $conn->query("SELECT COUNT(*) as c FROM clubs")->fetch_assoc()['c'];
        $ativos   = $conn->query("SELECT COUNT(*) as c FROM clubs WHERE ativo = 1")->fetch_assoc()['c'];
        $inativos = $conn->query("SELECT COUNT(*) as c FROM clubs WHERE ativo = 0")->fetch_assoc()['c'];
      } elseif ($user_club_id > 0) {
        $total    = $conn->query("SELECT COUNT(*) as c FROM clubs WHERE id = $user_club_id")->fetch_assoc()['c'];
        $ativos   = $conn->query("SELECT COUNT(*) as c FROM clubs WHERE ativo = 1 AND id = $user_club_id")->fetch_assoc()['c'];
        $inativos = $conn->query("SELECT COUNT(*) as c FROM clubs WHERE ativo = 0 AND id = $user_club_id")->fetch_assoc()['c'];
      } else {
        $total = $ativos = $inativos = 0;
      }
      ?>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-building-fill"></i></div>
          <div class="stat-content">
            <div class="stat-label">Total de Clubes</div>
            <div class="stat-value"><?= $total ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
          <div class="stat-content">
            <div class="stat-label">Clubes Ativos</div>
            <div class="stat-value"><?= $ativos ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-x-circle-fill"></i></div>
          <div class="stat-content">
            <div class="stat-label">Clubes Inativos</div>
            <div class="stat-value"><?= $inativos ?></div>
          </div>
        </div>
      </div>

      <div class="filters-container">
        <div class="filters-row">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" class="search-input" id="searchInput"
                   placeholder="Pesquisar por nome, cidade ou email..."
                   onkeyup="filterClubs()">
          </div>
          <select class="filter-select" id="statusFilter" onchange="filterClubs()">
            <option value="">Todos os Estados</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
          </select>
        </div>
      </div>

      <div class="clubs-grid" id="clubsGrid">
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($club = $result->fetch_assoc()): ?>
            <div class="club-card"
                 data-name="<?= strtolower(htmlspecialchars($club['nome'])) ?>"
                 data-city="<?= strtolower(htmlspecialchars($club['cidade'] ?? '')) ?>"
                 data-email="<?= strtolower(htmlspecialchars($club['email_contacto'] ?? '')) ?>"
                 data-status="<?= $club['ativo'] ?>">

              <div class="club-card-header">
                <div class="club-logo">
                  <?php if (!empty($club['logo'])): ?>
                    <img src="../../uploads/logos/<?= htmlspecialchars($club['logo']) ?>"
                         alt="Logo <?= htmlspecialchars($club['nome']) ?>"
                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <i class="bi bi-building-fill club-logo-placeholder" style="display:none;"></i>
                  <?php else: ?>
                    <i class="bi bi-building-fill club-logo-placeholder"></i>
                  <?php endif; ?>
                </div>
                <div class="club-header-info">
                  <h3 class="club-name" title="<?= htmlspecialchars($club['nome']) ?>">
                    <?= htmlspecialchars($club['nome']) ?>
                  </h3>
                  <span class="club-status <?= $club['ativo'] == 1 ? 'active' : 'inactive' ?>">
                    <i class="bi bi-circle-fill"></i>
                    <?= $club['ativo'] == 1 ? 'Ativo' : 'Inativo' ?>
                  </span>
                </div>
              </div>

              <div class="club-card-body">
                <?php if (!empty($club['cidade'])): ?>
                  <div class="club-info-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span class="club-info-text"><?= htmlspecialchars($club['cidade']) ?></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($club['email_contacto'])): ?>
                  <div class="club-info-item">
                    <i class="bi bi-envelope-fill"></i>
                    <span class="club-info-text"><?= htmlspecialchars($club['email_contacto']) ?></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($club['telefone'])): ?>
                  <div class="club-info-item">
                    <i class="bi bi-telephone-fill"></i>
                    <span class="club-info-text"><?= htmlspecialchars($club['telefone']) ?></span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="club-card-footer">
                <a href="editar.php?id=<?= $club['id'] ?>" class="card-btn card-btn-edit">
                  <i class="bi bi-pencil-fill"></i>
                  Editar
                </a>
                <?php if ($isSuperAdmin): ?>
                  <button class="card-btn card-btn-delete"
                          onclick="openDeleteModal(<?= $club['id'] ?>, '<?= htmlspecialchars($club['nome'], ENT_QUOTES) ?>')">
                    <i class="bi bi-trash-fill"></i>
                    Eliminar
                  </button>
                <?php endif; ?>
              </div>

            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state" style="grid-column: 1 / -1;">
            <i class="bi bi-building empty-state-icon"></i>
            <h3><?= $isSuperAdmin ? 'Nenhum clube cadastrado' : 'Nenhum clube associado' ?></h3>
            <p><?= $isSuperAdmin ? 'Comece adicionando o primeiro clube' : 'Não tem um clube associado à sua conta' ?></p>
            <?php if ($isSuperAdmin): ?>
              <a href="editar.php" class="btn-header btn-add">
                <i class="bi bi-plus-circle-fill"></i>
                Adicionar Primeiro Clube
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Modal de Confirmação de Eliminação -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">
          <i class="bi bi-exclamation-triangle-fill"></i>
          Confirmar Eliminação
        </h2>
      </div>
      <div class="modal-body">
        <p class="modal-text">
          Tem certeza que deseja eliminar o clube <strong id="clubNameToDelete"></strong>?
        </p>
        <p class="modal-warning">
          <i class="bi bi-info-circle-fill"></i>
          Esta ação não pode ser desfeita. Todos os dados associados ao clube serão permanentemente removidos.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">
          <i class="bi bi-x-circle"></i>
          Cancelar
        </button>
        <button type="button" class="modal-btn modal-btn-confirm" onclick="deleteClub()">
          <i class="bi bi-trash-fill"></i>
          Eliminar
        </button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../toast.js"></script>
  <script>
    let clubIdToDelete = null;

    function filterClubs() {
      const searchInput  = document.getElementById('searchInput').value.toLowerCase();
      const statusFilter = document.getElementById('statusFilter').value;
      document.querySelectorAll('.club-card').forEach(card => {
        const name   = card.getAttribute('data-name');
        const city   = card.getAttribute('data-city');
        const email  = card.getAttribute('data-email');
        const status = card.getAttribute('data-status');

        const matchesSearch = name.includes(searchInput) || city.includes(searchInput) || email.includes(searchInput);
        const matchesStatus = statusFilter === '' || status === statusFilter;

        card.style.display = (matchesSearch && matchesStatus) ? 'flex' : 'none';
      });
    }

    function openDeleteModal(id, nome) {
      clubIdToDelete = id;
      document.getElementById('clubNameToDelete').textContent = nome;
      document.getElementById('deleteModal').classList.add('active');
    }

    function closeDeleteModal() {
      clubIdToDelete = null;
      document.getElementById('deleteModal').classList.remove('active');
    }

    function deleteClub() {
      if (!clubIdToDelete) return;

      const idParaEliminar = clubIdToDelete;
      const nome = document.getElementById('clubNameToDelete').textContent;

      closeDeleteModal();

      toast.info('A eliminar...', 'Aguarde um momento');

      fetch('eliminar_clube.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(idParaEliminar)}`
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          toast.success('Eliminado!', `O clube "${nome}" foi eliminado com sucesso`);
          setTimeout(() => window.location.reload(), 1500);
        } else {
          toast.error('Erro!', data.message || 'Não foi possível eliminar o clube');
        }
      })
      .catch(() => toast.error('Erro!', 'Erro ao eliminar o clube. Tente novamente.'));
    }

    document.getElementById('deleteModal').addEventListener('click', function(e) {
      if (e.target === this) closeDeleteModal();
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeDeleteModal();
    });
  </script>
</body>
</html>