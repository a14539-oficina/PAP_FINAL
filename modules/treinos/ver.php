<?php
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_role    = $_SESSION['user_role'] ?? '';
$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id      = $_SESSION['user_id'];

$isAdmin     = in_array($user_role, ['admin', '1']);
$isDiretor   = in_array($user_role, ['diretor', '2']);
$isTreinador = in_array($user_role, ['treinador', '3']);
$isJogador   = in_array($user_role, ['jogador', '4']);

// Só admin, diretor e treinador podem editar/eliminar
$pode_editar = $isAdmin || $isDiretor || $isTreinador;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Validar ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: listar.php");
    exit;
}

// Buscar treino
$stmt = $conn->prepare("
    SELECT t.*, 
           e.nome AS equipa_nome, 
           e.club_id,
           c.nome AS clube_nome,
           s.nome AS epoca_nome
    FROM training_sessions t
    LEFT JOIN teams e ON t.team_id = e.id
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN seasons s ON t.season_id = s.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$treino = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$treino) {
    header("Location: listar.php");
    exit;
}

// Verificar acesso ao clube
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);
if (!$isAdminPrincipal && $treino['club_id'] != $user_club_id) {
    header("Location: listar.php");
    exit;
}

$dataTreino  = new DateTime($treino['data_treino']);
$dataAtual   = new DateTime();
$isConcluido = $dataTreino < $dataAtual;
$statusLabel = $isConcluido ? 'Concluído' : 'Agendado';
$statusClass = $isConcluido ? 'concluido' : 'agendado';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Treino - SportGes</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f8fafc;
      color: #1e293b;
    }

    .main-content {
      margin-left: 240px;
      padding: 24px;
      width: calc(100% - 240px);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* OVERLAY */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      backdrop-filter: blur(4px);
      z-index: 200;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    /* MODAL BOX */
    .modal-box {
      background: #fff;
      border-radius: 20px;
      width: 100%;
      max-width: 720px;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      box-shadow: 0 24px 80px rgba(0,0,0,0.25);
      animation: slideUp .3s cubic-bezier(.4,0,.2,1);
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(32px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* HEADER */
    .modal-header {
      background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
      padding: 28px 28px 24px;
      position: relative;
      flex-shrink: 0;
    }

    .modal-close {
      position: absolute;
      top: 16px; right: 16px;
      width: 36px; height: 36px;
      border-radius: 50%;
      border: none;
      background: rgba(255,255,255,.2);
      color: #fff;
      font-size: 18px;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background .2s;
      text-decoration: none;
    }
    .modal-close:hover { background: rgba(255,255,255,.35); color: #fff; }

    .modal-title-row {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .modal-icon {
      width: 64px; height: 64px;
      border-radius: 16px;
      background: rgba(255,255,255,.2);
      display: flex; align-items: center; justify-content: center;
      font-size: 30px;
      color: #fff;
      flex-shrink: 0;
    }

    .modal-title-info h2 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #fff;
      margin-bottom: 6px;
    }

    .modal-badges {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .mbadge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
      background: rgba(255,255,255,.18);
      color: #fff;
    }
    .mbadge.agendado  { background: rgba(16,185,129,.35); }
    .mbadge.concluido { background: rgba(100,116,139,.35); }

    /* TABS */
    .modal-tabs {
      display: flex;
      border-bottom: 2px solid #e2e8f0;
      padding: 0 24px;
      background: #fff;
      flex-shrink: 0;
    }

    .tab-btn {
      padding: 14px 18px;
      border: none;
      background: none;
      font-size: 0.9rem;
      font-weight: 500;
      color: #64748b;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      display: flex; align-items: center; gap: 6px;
      transition: all .2s;
      white-space: nowrap;
    }
    .tab-btn:hover  { color: #2563eb; }
    .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; font-weight: 600; }

    /* BODY */
    .modal-body {
      overflow-y: auto;
      padding: 24px 28px;
      flex: 1;
    }
    .modal-body::-webkit-scrollbar { width: 5px; }
    .modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    .tab-pane        { display: none; }
    .tab-pane.active { display: block; }

    /* INFO GRID */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
    }

    .info-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 16px 18px;
    }
    .info-card.full { grid-column: 1 / -1; }

    .info-label {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #94a3b8;
      margin-bottom: 6px;
      display: flex; align-items: center; gap: 6px;
    }

    .info-value       { font-size: 1rem; font-weight: 600; color: #0f172a; }
    .info-value.muted { color: #94a3b8; font-weight: 400; font-style: italic; }

    /* STATUS PILL */
    .status-pill {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 5px 14px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .status-pill.agendado  { background: #dcfce7; color: #166534; }
    .status-pill.concluido { background: #e2e8f0; color: #475569; }

    /* NOTAS */
    .section-title {
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #64748b;
      margin-bottom: 12px;
      display: flex; align-items: center; gap: 8px;
    }

    .notas-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 18px;
      font-size: 0.95rem;
      color: #475569;
      line-height: 1.7;
      white-space: pre-wrap;
      min-height: 100px;
    }

    .notas-empty {
      color: #94a3b8;
      font-style: italic;
      text-align: center;
      padding: 24px;
    }

    /* FOOTER */
    .modal-footer {
      padding: 16px 28px;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
      background: #fff;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn-footer {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 22px;
      border-radius: 10px;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
      text-decoration: none;
      border: none;
    }

    .btn-cancel { background: #f1f5f9; color: #475569; }
    .btn-cancel:hover { background: #e2e8f0; color: #334155; }

    .btn-edit {
      background: linear-gradient(135deg, #2563eb, #4f46e5);
      color: #fff;
      box-shadow: 0 4px 12px rgba(37,99,235,.25);
    }
    .btn-edit:hover { box-shadow: 0 6px 18px rgba(37,99,235,.4); transform: translateY(-1px); color: #fff; }

    .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
    .btn-delete:hover { background: #fee2e2; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
      .main-content { margin-left: 0; width: 100%; }
    }

    @media (max-width: 600px) {
      .modal-overlay { padding: 0; align-items: flex-end; }
      .modal-box     { border-radius: 20px 20px 0 0; max-height: 95vh; }
      .info-grid     { grid-template-columns: 1fr; }
      .modal-header  { padding: 20px 20px 18px; }
      .modal-body    { padding: 18px 20px; }
      .modal-footer  { padding: 14px 20px; }
      .tab-btn       { padding: 12px 12px; font-size: 0.8rem; }
    }
  </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
  <div class="modal-overlay">
    <div class="modal-box">

      <!-- HEADER -->
      <div class="modal-header">
        <a href="listar.php" class="modal-close" title="Fechar">
          <i class="bi bi-x-lg"></i>
        </a>
        <div class="modal-title-row">
          <div class="modal-icon">
            <i class="bi bi-clipboard-check-fill"></i>
          </div>
          <div class="modal-title-info">
            <h2><?= h($treino['equipa_nome'] ?? 'Sem equipa') ?></h2>
            <div class="modal-badges">
              <span class="mbadge">
                <i class="bi bi-calendar-event"></i>
                <?= $dataTreino->format('d/m/Y') ?>
              </span>
              <span class="mbadge">
                <i class="bi bi-clock"></i>
                <?= $dataTreino->format('H:i') ?>
              </span>
              <span class="mbadge <?= $statusClass ?>">
                <i class="bi bi-<?= $isConcluido ? 'check-circle' : 'hourglass-split' ?>"></i>
                <?= $statusLabel ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- TABS -->
      <div class="modal-tabs">
        <button class="tab-btn active" onclick="switchTab('info', this)">
          <i class="bi bi-info-circle"></i> Informações
        </button>
        <button class="tab-btn" onclick="switchTab('notas', this)">
          <i class="bi bi-chat-text"></i> Notas
        </button>
      </div>

      <!-- BODY -->
      <div class="modal-body">

        <!-- TAB: INFORMAÇÕES -->
        <div class="tab-pane active" id="tab-info">
          <div class="info-grid">

            <div class="info-card">
              <div class="info-label"><i class="bi bi-calendar-event"></i> Data</div>
              <div class="info-value"><?= $dataTreino->format('d/m/Y') ?></div>
            </div>

            <div class="info-card">
              <div class="info-label"><i class="bi bi-clock"></i> Hora</div>
              <div class="info-value"><?= $dataTreino->format('H:i') ?></div>
            </div>

            <div class="info-card">
              <div class="info-label"><i class="bi bi-people-fill"></i> Equipa</div>
              <div class="info-value"><?= h($treino['equipa_nome'] ?? '—') ?></div>
            </div>

            <div class="info-card">
              <div class="info-label"><i class="bi bi-building"></i> Clube</div>
              <div class="info-value"><?= h($treino['clube_nome'] ?? '—') ?></div>
            </div>

            <div class="info-card">
              <div class="info-label"><i class="bi bi-flag"></i> Época</div>
              <div class="info-value"><?= h($treino['epoca_nome'] ?? '—') ?></div>
            </div>

            <div class="info-card">
              <div class="info-label"><i class="bi bi-activity"></i> Estado</div>
              <div class="info-value">
                <span class="status-pill <?= $statusClass ?>">
                  <i class="bi bi-<?= $isConcluido ? 'check-circle-fill' : 'hourglass-split' ?>"></i>
                  <?= $statusLabel ?>
                </span>
              </div>
            </div>

            <?php if (!empty($treino['foco'])): ?>
            <div class="info-card full">
              <div class="info-label"><i class="bi bi-bullseye"></i> Foco do Treino</div>
              <div class="info-value"><?= h($treino['foco']) ?></div>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- TAB: NOTAS -->
        <div class="tab-pane" id="tab-notas">
          <p class="section-title"><i class="bi bi-chat-text-fill"></i> Notas / Observações</p>
          <?php if (!empty($treino['notas'])): ?>
            <div class="notas-box"><?= h($treino['notas']) ?></div>
          <?php else: ?>
            <div class="notas-box">
              <div class="notas-empty">
                <i class="bi bi-chat-square-text" style="font-size:2rem; display:block; margin-bottom:8px;"></i>
                Sem notas registadas para este treino.
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div><!-- /modal-body -->

      <!-- FOOTER -->
      <div class="modal-footer">
        <?php if ($pode_editar): ?>
          <!-- Admin / Diretor / Treinador: Eliminar + Voltar + Editar -->
          <button class="btn-footer btn-delete" onclick="confirmarEliminar(<?= $treino['id'] ?>, '<?= h($treino['equipa_nome'] ?? 'Sem equipa') ?>')">
            <i class="bi bi-trash-fill"></i> Eliminar
          </button>
          <div style="display:flex; gap:10px;">
            <a href="listar.php" class="btn-footer btn-cancel">
              <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <a href="editar.php?id=<?= $treino['id'] ?>" class="btn-footer btn-edit">
              <i class="bi bi-pencil-fill"></i> Editar
            </a>
          </div>
        <?php else: ?>
          <!-- Jogador: apenas Voltar -->
          <div></div>
          <a href="listar.php" class="btn-footer btn-cancel">
            <i class="bi bi-arrow-left"></i> Voltar
          </a>
        <?php endif; ?>
      </div>

    </div><!-- /modal-box -->
  </div><!-- /modal-overlay -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
  }

  // Fechar com ESC
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') window.location.href = 'listar.php';
  });

  // Fechar ao clicar no overlay
  document.querySelector('.modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) window.location.href = 'listar.php';
  });

  <?php if ($pode_editar): ?>
  function confirmarEliminar(id, nome) {
    toast.confirm({
      type: 'warning',
      title: 'Eliminar Treino?',
      message: `Tem certeza que deseja eliminar o treino da equipa "${nome}"? Esta ação não pode ser desfeita.`,
      confirmText: 'Eliminar',
      cancelText: 'Cancelar',
      onConfirm: () => eliminarTreino(id, nome)
    });
  }

  function eliminarTreino(id, nome) {
    toast.info('A eliminar...', 'Aguarde um momento');
    fetch('eliminar_treino.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(id)}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        toast.success('Eliminado!', `O treino da equipa "${nome}" foi eliminado com sucesso`);
        setTimeout(() => window.location.href = 'listar.php', 1500);
      } else {
        toast.error('Erro!', data.message || 'Não foi possível eliminar o treino');
      }
    })
    .catch(() => toast.error('Erro!', 'Erro ao eliminar. Tente novamente.'));
  }
  <?php endif; ?>
</script>
<script src="../../toast.js"></script>
</body>
</html>

<?php $conn->close(); ?>