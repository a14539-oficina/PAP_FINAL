<?php
session_start();
require('../../config/db.php');

// 🔍 DEBUG
error_log("=== DEBUG SESSÃO ===");
error_log("user_team: " . ($_SESSION['user_team'] ?? 'não definido'));
error_log("team_id: " . ($_SESSION['team_id'] ?? 'não definido'));
error_log("user_role: " . ($_SESSION['user_role'] ?? 'não definido'));
error_log("user_id: " . ($_SESSION['user_id'] ?? 'não definido'));

$pageTitle    = 'Editar Treino';
$dashboardPath = '../../dashboard.php';
$modulesPath  = '..';
$logoutPath   = '../../logout.php';

$user_role = $_SESSION['user_role'] ?? '';

if (empty($user_role)) {
    header('Location: ../../login.php');
    exit;
}

$tem_permissao = in_array($user_role, ['1', '3', 'Administrador', 'Treinador']);
if (!$tem_permissao) {
    header('Location: listar.php?erro=sem_permissao');
    exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id          = isset($_GET['id']) ? intval($_GET['id']) : 0;
$treino      = null;
$erro        = '';
$treinoPassou = false;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM training_sessions WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res    = $stmt->get_result();
        $treino = $res->fetch_assoc();
        $stmt->close();

        if (!$treino) {
            header('Location: listar.php?erro=treino_nao_encontrado');
            exit;
        }

        $dataTreino  = new DateTime($treino['data_treino']);
        $dataAtual   = new DateTime();
        $treinoPassou = $dataTreino < $dataAtual;
    } else {
        $erro = 'Erro ao buscar treino: ' . $conn->error;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($treinoPassou && $id > 0) {
        // Treino passado: só actualiza notas
        $notas = trim($_POST['notas'] ?? '');
        $stmt  = $conn->prepare("UPDATE training_sessions SET notas=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("si", $notas, $id);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: listar.php?sucesso=observacoes_atualizadas');
                exit;
            } else {
                $erro = 'Erro ao atualizar observações: ' . $stmt->error;
            }
        } else {
            $erro = 'Erro na preparação da query: ' . $conn->error;
        }
    } else {
        $team_id    = isset($_POST['team_id']) ? intval($_POST['team_id']) : 0;
        $data_treino = $_POST['data_treino'] ?? '';
        $foco       = trim($_POST['foco'] ?? '');
        $notas      = trim($_POST['notas'] ?? '');

        $user_club_id = $_SESSION['user_club'] ?? $_SESSION['club_id'] ?? 0;

        $stmt_verif = $conn->prepare("SELECT id FROM teams WHERE id = ? AND club_id = ?");
        $stmt_verif->bind_param("ii", $team_id, $user_club_id);
        $stmt_verif->execute();
        $verif_result = $stmt_verif->get_result();

        if ($verif_result->num_rows == 0) {
            $erro = 'Equipa inválida ou não pertence ao seu clube!';
        } elseif (empty($data_treino) || empty($foco)) {
            $erro = 'Preencha todos os campos obrigatórios!';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE training_sessions SET team_id=?, data_treino=?, foco=?, notas=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("isssi", $team_id, $data_treino, $foco, $notas, $id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: listar.php?sucesso=treino_atualizado');
                        exit;
                    } else {
                        $erro = 'Erro ao atualizar treino: ' . $stmt->error;
                    }
                } else {
                    $erro = 'Erro na preparação da query: ' . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO training_sessions (team_id, data_treino, foco, notas) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isss", $team_id, $data_treino, $foco, $notas);
                    if ($stmt->execute()) {
                        $stmt->close();
                        header('Location: listar.php?sucesso=treino_criado');
                        exit;
                    } else {
                        $erro = 'Erro ao criar treino: ' . $stmt->error;
                    }
                } else {
                    $erro = 'Erro na preparação da query: ' . $conn->error;
                }
            }
        }
    }
}

// Buscar equipas
$equipas_cols = $conn->query("SHOW COLUMNS FROM teams");
$colunas = [];
if ($equipas_cols) {
    while ($col = $equipas_cols->fetch_assoc()) $colunas[] = $col['Field'];
    $equipas_cols->free();
}
$colNome = 'name';
foreach (['name', 'nome', 'team_name', 'equipa', 'titulo'] as $possivel) {
    if (in_array($possivel, $colunas)) { $colNome = $possivel; break; }
}

$user_club_id = $_SESSION['user_club'] ?? $_SESSION['club_id'] ?? 0;
$sqlEquipas   = "SELECT id, `$colNome` AS nome FROM teams WHERE club_id = ? ORDER BY `$colNome`";
$stmt_equipas = $conn->prepare($sqlEquipas);
$equipas      = null;
if ($stmt_equipas) {
    $stmt_equipas->bind_param("i", $user_club_id);
    $stmt_equipas->execute();
    $equipas = $stmt_equipas->get_result();
    $stmt_equipas->close();
}

// Status
$statusLabel = $treinoPassou ? 'Concluído' : ($id > 0 ? 'Agendado' : 'Novo');
$statusClass = $treinoPassou ? 'concluido' : 'agendado';
$dataTreino  = isset($treino['data_treino']) ? new DateTime($treino['data_treino']) : null;

// Nome da equipa (para o header quando está em modo leitura)
$equipa_nome = '';
if ($treino && isset($treino['team_id'])) {
    $stmt_eq = $conn->prepare("SELECT `$colNome` AS nome FROM teams WHERE id = ?");
    $stmt_eq->bind_param("i", $treino['team_id']);
    $stmt_eq->execute();
    $res_eq = $stmt_eq->get_result()->fetch_assoc();
    $equipa_nome = $res_eq['nome'] ?? '';
    $stmt_eq->close();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? 'Editar' : 'Novo' ?> Treino - SportGes</title>
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
    .mbadge.novo      { background: rgba(59,130,246,.35); }

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

    /* ALERTA */
    .alert-warning {
      background: #fef3c7;
      border: 1.5px solid #fbbf24;
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      color: #92400e;
      font-size: 0.9rem;
    }
    .alert-warning i { font-size: 20px; color: #f59e0b; flex-shrink: 0; margin-top: 2px; }
    .alert-warning strong { display: block; margin-bottom: 2px; }

    .alert-error {
      background: #fee2e2;
      border: 1.5px solid #fca5a5;
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 20px;
      color: #dc2626;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* FORM GRID */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
    }

    .form-group        { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full   { grid-column: 1 / -1; }

    /* INFO CARD (modo leitura) */
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
    .info-value { font-size: 1rem; font-weight: 600; color: #0f172a; }

    /* LABELS e INPUTS */
    label {
      font-size: 0.78rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    input[type="text"],
    input[type="datetime-local"],
    select,
    textarea {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.95rem;
      font-family: inherit;
      color: #0f172a;
      background: #fff;
      transition: border-color .2s, box-shadow .2s;
    }
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59,130,246,.1);
    }

    textarea { resize: vertical; min-height: 110px; }

    small.hint {
      font-size: 0.78rem;
      color: #94a3b8;
      margin-top: 2px;
    }
    small.hint.blue { color: #3b82f6; }

    /* NOTAS BOX (leitura) */
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
    .section-title {
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #64748b;
      margin-bottom: 12px;
      display: flex; align-items: center; gap: 8px;
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
    .btn-cancel  { background: #f1f5f9; color: #475569; }
    .btn-cancel:hover { background: #e2e8f0; color: #334155; }

    .btn-save {
      background: linear-gradient(135deg, #2563eb, #4f46e5);
      color: #fff;
      box-shadow: 0 4px 12px rgba(37,99,235,.25);
    }
    .btn-save:hover { box-shadow: 0 6px 18px rgba(37,99,235,.4); transform: translateY(-1px); color: #fff; }

    .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
    .btn-delete:hover { background: #fee2e2; }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
      .main-content { margin-left: 0; width: 100%; }
    }
    @media (max-width: 600px) {
      .modal-overlay { padding: 0; align-items: flex-end; }
      .modal-box     { border-radius: 20px 20px 0 0; max-height: 95vh; }
      .form-grid     { grid-template-columns: 1fr; }
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
            <i class="bi bi-<?= $id ? 'pencil-square' : 'plus-circle-fill' ?>"></i>
          </div>
          <div class="modal-title-info">
            <h2>
              <?php if ($treinoPassou): ?>
                <?= h($equipa_nome ?: 'Treino Concluído') ?>
              <?php elseif ($id): ?>
                Editar Treino
              <?php else: ?>
                Novo Treino
              <?php endif; ?>
            </h2>
            <div class="modal-badges">
              <?php if ($dataTreino): ?>
                <span class="mbadge">
                  <i class="bi bi-calendar-event"></i>
                  <?= $dataTreino->format('d/m/Y') ?>
                </span>
                <span class="mbadge">
                  <i class="bi bi-clock"></i>
                  <?= $dataTreino->format('H:i') ?>
                </span>
              <?php else: ?>
                <span class="mbadge">
                  <i class="bi bi-plus-circle"></i>
                  A criar
                </span>
              <?php endif; ?>
              <span class="mbadge <?= $statusClass ?>">
                <i class="bi bi-<?= $treinoPassou ? 'check-circle' : ($id ? 'hourglass-split' : 'pencil') ?>"></i>
                <?= $statusLabel ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- TABS -->
      <div class="modal-tabs">
        <button class="tab-btn active" onclick="switchTab('detalhes', this)">
          <i class="bi bi-info-circle"></i>
          <?= $treinoPassou ? 'Informações' : 'Detalhes' ?>
        </button>
        <button class="tab-btn" onclick="switchTab('notas', this)">
          <i class="bi bi-chat-text"></i> Notas
        </button>
      </div>

      <!-- FORM -->
      <form method="POST" id="mainForm">
        <div class="modal-body">

          <?php if (!empty($erro)): ?>
            <div class="alert-error">
              <i class="bi bi-exclamation-circle-fill"></i>
              <?= h($erro) ?>
            </div>
          <?php endif; ?>

          <?php if ($treinoPassou): ?>
            <div class="alert-warning">
              <i class="bi bi-clock-history"></i>
              <div>
                <strong>Treino já realizado</strong>
                Este treino ocorreu em <?= $dataTreino->format('d/m/Y \à\s H:i') ?>. Só pode editar as notas/observações.
              </div>
            </div>
          <?php endif; ?>

          <!-- TAB: DETALHES -->
          <div class="tab-pane active" id="tab-detalhes">
            <div class="form-grid">

              <!-- EQUIPA -->
              <div class="form-group full">
                <label><i class="bi bi-people-fill"></i> Equipa *</label>
                <?php if ($treinoPassou): ?>
                  <div class="info-card">
                    <div class="info-value"><?= h($equipa_nome ?: '—') ?></div>
                  </div>
                <?php else: ?>
                  <select name="team_id" required>
                    <option value="">Selecione uma equipa...</option>
                    <?php if ($equipas && $equipas->num_rows > 0):
                      while ($eq = $equipas->fetch_assoc()): ?>
                      <option value="<?= $eq['id'] ?>"
                              <?= ($treino && $treino['team_id'] == $eq['id']) ? 'selected' : '' ?>>
                        <?= h($eq['nome']) ?>
                      </option>
                    <?php endwhile; endif; ?>
                  </select>
                <?php endif; ?>
              </div>

              <!-- DATA E HORA -->
              <div class="form-group full">
                <label><i class="bi bi-calendar-event"></i> Data e Hora *</label>
                <?php if ($treinoPassou): ?>
                  <div class="info-card">
                    <div class="info-value"><?= $dataTreino->format('d/m/Y \à\s H:i') ?></div>
                  </div>
                <?php else: ?>
                  <input type="datetime-local" name="data_treino"
                         value="<?= $treino ? date('Y-m-d\TH:i', strtotime($treino['data_treino'])) : '' ?>" required>
                <?php endif; ?>
              </div>

              <!-- FOCO -->
              <div class="form-group full">
                <label><i class="bi bi-bullseye"></i> Foco do Treino *</label>
                <?php if ($treinoPassou): ?>
                  <div class="info-card">
                    <div class="info-value"><?= h($treino['foco']) ?></div>
                  </div>
                <?php else: ?>
                  <input type="text" name="foco"
                         placeholder="Ex: Táctica, Físico, Técnica..."
                         value="<?= $treino ? h($treino['foco']) : '' ?>" required>
                <?php endif; ?>
              </div>

            </div>
          </div>

          <!-- TAB: NOTAS -->
          <div class="tab-pane" id="tab-notas">
            <?php if ($treinoPassou): ?>
              <p class="section-title"><i class="bi bi-pencil-fill"></i> Notas / Observações <span style="color:#3b82f6;font-weight:400;text-transform:none;letter-spacing:0">(editável)</span></p>
            <?php else: ?>
              <p class="section-title"><i class="bi bi-chat-text-fill"></i> Notas / Observações</p>
            <?php endif; ?>

            <div class="form-group">
              <textarea name="notas" placeholder="Detalhes adicionais sobre o treino..."><?= $treino ? h($treino['notas']) : '' ?></textarea>
              <?php if ($treinoPassou): ?>
                <small class="hint blue"><i class="bi bi-pencil"></i> Este é o único campo editável em treinos já realizados.</small>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /modal-body -->

        <!-- FOOTER -->
        <div class="modal-footer">
          <?php if ($id > 0 && !$treinoPassou): ?>
            <button type="button" class="btn-footer btn-delete"
                    onclick="confirmarEliminacao(<?= $id ?>)">
              <i class="bi bi-trash-fill"></i> Eliminar
            </button>
          <?php else: ?>
            <div></div>
          <?php endif; ?>

          <div style="display:flex; gap:10px;">
            <a href="listar.php" class="btn-footer btn-cancel">
              <i class="bi bi-arrow-left"></i> Cancelar
            </a>
            <button type="submit" class="btn-footer btn-save">
              <i class="bi bi-save-fill"></i>
              <?php if ($treinoPassou): ?>
                Guardar Notas
              <?php elseif ($id): ?>
                Atualizar
              <?php else: ?>
                Criar Treino
              <?php endif; ?>
            </button>
          </div>
        </div>

      </form><!-- /form -->

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

  // Fechar ao clicar no overlay (fora do modal)
  document.querySelector('.modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) window.location.href = 'listar.php';
  });

  function confirmarEliminacao(id) {
    if (confirm('Tem a certeza que deseja eliminar este treino?\n\nEsta ação não pode ser revertida.')) {
      window.location.href = 'eliminar_treino.php?id=' + id;
    }
  }
</script>
<script src="../../toast.js"></script>
</body>
</html>

<?php $conn->close(); ?>