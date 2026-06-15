<?php
session_start();
require('../../config/db.php');

// ===============================
// PROTEGER ACESSO
// ===============================
if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

$user_id      = $_SESSION['user_id'];
$user_club_id = $_SESSION['club_id']   ?? 0;
$user_role    = $_SESSION['user_role'] ?? '';
$user_team_id = $_SESSION['team_id']   ?? 0;

$isAdminPrincipal = ($user_id == 7 && $user_club_id == 0);
$is_treinador     = ($user_role == 3);

// SuperAdmin (0) não acede
if ($user_role === '0') {
    $_SESSION['erro'] = "SuperAdmins não têm acesso a esta área.";
    header("Location: ../../dashboard.php");
    exit;
}

// ===============================
// PROCESSAR ELIMINAÇÃO AJAX
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_player') {

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    if ($isAdminPrincipal) {
        // Admin principal — elimina tudo
        $stmt = $conn->prepare("DELETE FROM players WHERE id = ?");
        $stmt->bind_param("i", $id);

    } elseif ($is_treinador && $user_team_id > 0) {
        // Treinador — só elimina jogadores da sua equipa
        $stmt = $conn->prepare("DELETE FROM players WHERE id = ? AND team_id = ?");
        $stmt->bind_param("ii", $id, $user_team_id);

    } else {
        // Admin/Diretor — só elimina jogadores do seu clube
        $stmt = $conn->prepare("DELETE p FROM players p WHERE p.id = ? AND p.club_id = ?");
        $stmt->bind_param("ii", $id, $user_club_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao eliminar jogador']);
    }

    exit;
}

// ===============================
// PAGINAÇÃO
// ===============================
$porPagina = isset($_GET['entries']) ? max(10, min(50, (int)$_GET['entries'])) : 10;
$pagina    = isset($_GET['page'])    ? max(1, (int)$_GET['page'])              : 1;
$offset    = ($pagina - 1) * $porPagina;

// ===============================
// CONTAR JOGADORES
// ===============================
if ($isAdminPrincipal) {
    $sqlCount  = "SELECT COUNT(*) AS total FROM players";
    $stmtCount = $conn->prepare($sqlCount);

} elseif ($is_treinador && $user_team_id > 0) {
    // Treinador: só jogadores da sua equipa
    $sqlCount  = "SELECT COUNT(*) AS total FROM players WHERE team_id = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("i", $user_team_id);

} else {
    // Admin/Diretor: todos do clube
    $sqlCount  = "SELECT COUNT(*) AS total FROM players WHERE club_id = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("i", $user_club_id);
}

$stmtCount->execute();
$resCount       = $stmtCount->get_result();
$totalJogadores = $resCount->fetch_assoc()['total'];
$stmtCount->close();

$totalPaginas = ceil(max(1, $totalJogadores) / $porPagina);

// ===============================
// QUERY PRINCIPAL
// ===============================
$sql = "
SELECT
    p.id,
    p.primeiro_nome,
    p.ultimo_nome,
    CONCAT(p.primeiro_nome,' ',p.ultimo_nome) AS nome_completo,
    p.data_nascimento,
    p.foto,
    pos.name  AS posicao,
    t.nome    AS equipa_nome
FROM players p
LEFT JOIN positions pos ON pos.code = p.position_id
LEFT JOIN teams     t   ON t.id     = p.team_id
";

if ($isAdminPrincipal) {
    $sql .= " ORDER BY p.ultimo_nome ASC, p.primeiro_nome ASC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $porPagina, $offset);

} elseif ($is_treinador && $user_team_id > 0) {
    // Treinador: filtra por equipa
    $sql .= " WHERE p.team_id = ?
              ORDER BY p.ultimo_nome ASC, p.primeiro_nome ASC
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_team_id, $porPagina, $offset);

} else {
    // Admin/Diretor: filtra por clube
    $sql .= " WHERE p.club_id = ?
              ORDER BY p.ultimo_nome ASC, p.primeiro_nome ASC
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_club_id, $porPagina, $offset);
}

$stmt->execute();
$res = $stmt->get_result();

// Helper de segurança
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jogadores - SportGes</title>

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
      min-height: 100vh;
      box-sizing: border-box;
      overflow-x: hidden;
      transition: margin-left 0.3s ease, width 0.3s ease;
    }

    .content-wrapper {
      max-width: 1600px;
      margin: 0 auto;
      width: 100%;
    }

    /* HEADER */
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
      background: #f8fafc;
      transition: 0.2s;
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
      text-decoration: none;
      white-space: nowrap;
    }

    /* PAGINAÇÃO */
    .pagination-container {
      background: white;
      border: 2px solid #e2e8f0;
      border-radius: 16px;
      padding: 1.5rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
    }

    .pagination-info-section {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      flex-wrap: wrap;
    }

    .pagination-info { font-size: 0.9rem; color: #64748b; }

    .entries-control {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      color: #64748b;
    }

    .entries-select {
      padding: 0.375rem 0.75rem;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.875rem;
      cursor: pointer;
    }

    .pagination-controls { display: flex; gap: 0.5rem; align-items: center; }

    .pagination-btn {
      padding: 0.5rem 0.75rem;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      background: white;
      color: #374151;
      font-size: 0.875rem;
      font-weight: 500;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: 0.2s;
    }

    .pagination-btn:hover:not(.disabled):not(.active) {
      border-color: #3b82f6;
      color: #3b82f6;
    }

    .pagination-btn.active {
      background: #3b82f6;
      border-color: #3b82f6;
      color: white;
    }

    .pagination-btn.disabled {
      opacity: 0.4;
      pointer-events: none;
    }

    /* CARDS */
    .players-cards-container {
  width: 100%;
  display: grid;
  gap: 0.75rem;
}

    .player-card {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      width: 100%;
      box-sizing: border-box;
      overflow: hidden;
    }

    .player-avatar { flex-shrink: 0; }

    .player-thumb,
    .player-initials-thumb {
      width: 60px;
      height: 60px;
      border-radius: 10px;
      object-fit: cover;
    }

    .player-initials-thumb {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: #fff;
      font-size: 1.25rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .player-info {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .player-name {
      font-size: 1rem;
      font-weight: 600;
      margin: 0;
      color: #0f172a;
    }

    .player-details {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: center;
    }

    .player-detail-item {
      display: flex;
      align-items: center;
      gap: 0.375rem;
      font-size: 0.875rem;
      color: #64748b;
    }

    .player-detail-item i { font-size: 0.875rem; color: #94a3b8; }

    .player-actions { display: flex; gap: 0.5rem; }

    .action-btn {
      padding: 0.5rem 0.75rem;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: white;
      cursor: pointer;
      transition: 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .action-btn.view {
      background: #3b82f6;
      color: white;
      border-color: #3b82f6;
      padding: 0.5rem 1rem;
      font-weight: 500;
      text-decoration: none;
      white-space: nowrap;
    }

    .action-btn.view:hover { background: #2563eb; border-color: #2563eb; }

    .action-btn.delete {
  background: white;
  border: 2px solid #e5e7eb;
  color: #ef4444;
  width: 38px;
  height: 38px;
  border-radius: 8px;
}

.action-btn.delete:hover { background: #fee2e2; border-color: #ef4444; }

    /* EMPTY STATE */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: #94a3b8;
    }

    .empty-state i { font-size: 4rem; margin-bottom: 1rem; display: block; }
    .empty-state h3 { font-size: 1.25rem; color: #64748b; margin-bottom: 0.5rem; }

    /* MODAL DELETE */
    .delete-modal {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.55);
      backdrop-filter: blur(4px);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }

    .delete-modal.show { display: flex; }

    .delete-modal-content {
      background: #fff;
      width: 420px;
      padding: 28px;
      border-radius: 18px;
      text-align: center;
      box-shadow: 0 15px 40px rgba(0,0,0,0.25);
      animation: modalPop 0.25s ease-out;
    }

    .delete-modal-icon {
      width: 70px;
      height: 70px;
      background: #fee2e2;
      color: #b91c1c;
      border-radius: 50%;
      font-size: 2.3rem;
      display: flex;
      justify-content: center;
      align-items: center;
      margin: 0 auto 18px;
    }

    .delete-modal-actions {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      margin-top: 22px;
    }

    .btn-cancel-delete,
    .btn-confirm-delete {
      flex: 1;
      padding: 12px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
    }

    .btn-cancel-delete { background: #f1f5f9; border: 2px solid #cbd5e1; }

    .btn-confirm-delete {
      background: #ef4444;
      border: 2px solid #dc2626;
      color: white;
    }

    @keyframes modalPop {
      0%   { transform: scale(0.85); opacity: 0; }
      100% { transform: scale(1);    opacity: 1; }
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
      .main-content { margin-left: 0; width: 100%; padding: 16px; }
    }

    @media (max-width: 768px) {
      .import-list-header { padding: 1.25rem; flex-direction: column; align-items: stretch; }
      .import-list-header h1 { font-size: 1.5rem; }
      .header-left { width: 100%; }
      .header-actions { width: 100%; flex-direction: column; }
      .search-wrapper-header { min-width: 100%; max-width: 100%; }
      .btn-push-all { width: 100%; justify-content: center; }
      .player-card { padding: 0.875rem 1rem; gap: 0.75rem; }
      .player-thumb, .player-initials-thumb { width: 50px; height: 50px; font-size: 1rem; }
      .action-btn.view span { display: none; }
      .action-btn.view { padding: 0.5rem; min-width: 38px; }
      .pagination-container { flex-direction: column; align-items: stretch; padding: 1rem; }
      .pagination-info-section { flex-direction: column; gap: 1rem; }
      .pagination-controls { justify-content: center; }
    }

    @media (max-width: 480px) {
      .main-content { padding: 0.75rem; }
      .player-card { padding: 0.75rem; gap: 0.625rem; }
      .player-thumb, .player-initials-thumb { width: 44px; height: 44px; font-size: 0.875rem; }
      .player-details { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
      .player-detail-item { font-size: 0.75rem; }
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
        <?php if ($is_treinador): ?>
          Jogadores - Minha Equipa
        <?php else: ?>
          Jogadores - Meu Clube
        <?php endif; ?>
      </h1>
    </div>

    <div class="header-actions">
      <div class="search-wrapper-header">
        <i class="bi bi-search"></i>
        <input type="search" class="search-input-header" id="searchBar" placeholder="Pesquisar jogador...">
      </div>

      <?php if ($user_role != 4): ?>
        <a href="playerCentral.php" class="btn-push-all">
          <i class="bi bi-plus-circle-fill"></i> Adicionar Jogador
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($totalJogadores === 0): ?>

    <div class="empty-state">
      <i class="bi bi-people"></i>
      <h3>Nenhum jogador encontrado</h3>
      <p>
        <?php if ($is_treinador): ?>
          Não existem jogadores na sua equipa
        <?php else: ?>
          Adicione o primeiro jogador ao clube
        <?php endif; ?>
      </p>
    </div>

  <?php else: ?>

    <div class="players-cards-container">
    <?php while ($r = $res->fetch_assoc()): ?>

      <?php
        $idade = null;
        if ($r['data_nascimento']) {
            $nasc  = new DateTime($r['data_nascimento']);
            $hoje  = new DateTime();
            $idade = $hoje->diff($nasc)->y;
        }

        $temFoto = false;
        $fotoSrc = '';

        if (!empty($r['foto'])) {
            $file     = basename($r['foto']);
            $fileDisk = $_SERVER['DOCUMENT_ROOT'] . "/logos/" . $file;
            $fileURL  = "/logos/" . $file;
            if (file_exists($fileDisk)) {
                $temFoto = true;
                $fotoSrc = $fileURL;
            }
        }
      ?>

      <div class="player-card jogador-row" data-id="<?= (int)$r['id'] ?>">

        <div class="player-avatar">
          <?php if ($temFoto): ?>
            <img src="<?= h($fotoSrc) ?>" alt="<?= h($r['nome_completo']) ?>"
                 class="player-thumb"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="player-initials-thumb" style="display:none;">
              <?= strtoupper(substr($r['primeiro_nome'],0,1) . substr($r['ultimo_nome'],0,1)) ?>
            </div>
          <?php else: ?>
            <div class="player-initials-thumb">
              <?= strtoupper(substr($r['primeiro_nome'],0,1) . substr($r['ultimo_nome'],0,1)) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="player-info">
          <h3 class="player-name"><?= h($r['nome_completo']) ?></h3>
          <div class="player-details">
            <?php if ($idade !== null): ?>
              <span class="player-detail-item"><i class="bi bi-calendar"></i> <?= $idade ?> anos</span>
            <?php endif; ?>
            <?php if (!empty($r['posicao'])): ?>
              <span class="player-detail-item"><i class="bi bi-diagram-3"></i> <?= h($r['posicao']) ?></span>
            <?php endif; ?>
            <?php if (!empty($r['equipa_nome'])): ?>
              <span class="player-detail-item"><i class="bi bi-people"></i> <?= h($r['equipa_nome']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="player-actions" style="margin-left:auto;">
          <a href="playerCentral.php?id=<?= (int)$r['id'] ?>" class="action-btn view">
            <i class="bi bi-eye-fill"></i> <span>Ver</span>
          </a>

          <?php if ($user_role != 4): ?>
            <button class="action-btn delete fancy-delete"
                    data-id="<?= (int)$r['id'] ?>"
                    data-nome="<?= h($r['nome_completo']) ?>">
              <i class="bi bi-trash-fill"></i>
            </button>
          <?php endif; ?>
        </div>

      </div>

    <?php endwhile; ?>
    </div>

    <!-- PAGINAÇÃO -->
    <div class="pagination-container" style="margin-top:1.5rem;">
      <div class="pagination-info-section">
        <div class="pagination-info">
          Página <strong><?= $pagina ?></strong> de <strong><?= $totalPaginas ?></strong>
          &bull; Total: <strong><?= $totalJogadores ?></strong> jogadores
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


<!-- MODAL DELETE -->
<div id="deleteModal" class="delete-modal">
  <div class="delete-modal-content">
    <div class="delete-modal-icon">
      <i class="bi bi-exclamation-triangle-fill"></i>
    </div>
    <h2>Eliminar Jogador?</h2>
    <p id="modalText">Tem a certeza que deseja eliminar?</p>
    <div class="delete-modal-actions">
      <button onclick="closeDeleteModal()" class="btn-cancel-delete">Cancelar</button>
      <button onclick="confirmDelete()"    class="btn-confirm-delete">Eliminar</button>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../toast.js"></script>

<script>
// =============================
//  DELETE MODAL + AJAX
// =============================
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".action-btn.delete").forEach(btn => {
    btn.addEventListener("click", function () {
      const id   = this.dataset.id;
      const nome = this.dataset.nome;

      toast.confirm({
        type: 'warning',
        title: 'Eliminar Jogador?',
        message: `Tem certeza que deseja eliminar "${nome}"? Esta ação não pode ser desfeita.`,
        confirmText: 'Eliminar',
        cancelText: 'Cancelar',
        onConfirm: () => eliminarJogador(id, nome)
      });
    });
  });
});

async function eliminarJogador(id, nome) {
  toast.info('A eliminar...', 'Aguarde um momento');

  const formData = new FormData();
  formData.append("action", "delete_player");
  formData.append("id", id);

  try {
    const response = await fetch("listar.php", { method: "POST", body: formData });
    const data     = await response.json();

    if (data.success) {
      toast.success('Eliminado!', `Jogador "${nome}" eliminado com sucesso`);
      const card = document.querySelector(`.jogador-row[data-id="${id}"]`);
      if (card) {
        card.style.transition = "opacity 0.3s, transform 0.3s";
        card.style.opacity    = "0";
        card.style.transform  = "translateX(20px)";
        setTimeout(() => card.remove(), 300);
      }
    } else {
      toast.error('Erro!', data.message || 'Não foi possível eliminar o jogador');
    }
  } catch (err) {
    console.error(err);
    toast.error('Erro!', 'Erro ao eliminar jogador. Tente novamente.');
  }
}

// =============================
//  PESQUISA LIVE
// =============================
document.getElementById('searchBar').addEventListener('input', function () {
  const text = this.value.toLowerCase();
  document.querySelectorAll('.jogador-row').forEach(row => {
    const nome = row.querySelector('.player-name').textContent.toLowerCase();
    row.style.display = nome.includes(text) ? '' : 'none';
  });
});
</script>

</body>
</html>