<?php
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id      = $_SESSION['user_id'];
$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_team_id = isset($_SESSION['team_id']) ? intval($_SESSION['team_id']) : 0;
$user_role    = $_SESSION['user_role'] ?? '';

$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);
$isAdmin     = ($user_role == 1);
$isDiretor   = ($user_role == 2);
$isTreinador = in_array($user_role, ['3', 'Treinador']);
$isJogador   = ($user_role == 4);

if (!in_array($user_role, ['1', '2', '3', '4', 'Treinador'])) {
    header("Location: ../../dashboard.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: listar.php");
    exit;
}

// Buscar jogo
$stmt = $conn->prepare("
    SELECT m.*, 
           t.nome AS team_nome, t.escaloes,
           c.nome AS clube_nome,
           s.nome AS temporada_nome
    FROM matches m
    JOIN teams t  ON t.id  = m.team_id
    JOIN clubs c  ON c.id  = t.club_id
    LEFT JOIN seasons s ON s.id = m.season_id
    WHERE m.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$jogo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$jogo) {
    header("Location: listar.php");
    exit;
}

// Verificar permissão de acesso ao jogo
if (!$isAdminPrincipal) {
    if ($isTreinador || $isJogador) {
        if ($jogo['team_id'] != $user_team_id) {
            header("Location: listar.php");
            exit;
        }
    } else {
        // Admin/Diretor — verificar clube
        $stmtCheck = $conn->prepare("SELECT t.club_id FROM teams t WHERE t.id = ?");
        $stmtCheck->bind_param("i", $jogo['team_id']);
        $stmtCheck->execute();
        $teamClub = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();
        if (!$teamClub || $teamClub['club_id'] != $user_club_id) {
            header("Location: listar.php");
            exit;
        }
    }
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$timestamp     = strtotime($jogo['data_jogo']);
$dataCurta     = $timestamp ? date('d/m/Y', $timestamp) : '--';
$hora          = $timestamp ? date('H:i', $timestamp) : '--:--';
$golosMarcados = intval($jogo['golos_marcados']);
$golosSofridos = intval($jogo['golos_sofridos']);
$estadoLower   = strtolower($jogo['estado']);
$isConcluido   = in_array($estadoLower, ['concluído', 'concluido', 'decorrido']);

if ($isConcluido) {
    if ($golosMarcados > $golosSofridos) { $resultado = 'Vitória';  $resClass = 'win'; }
    elseif ($golosMarcados == $golosSofridos) { $resultado = 'Empate'; $resClass = 'draw'; }
    else { $resultado = 'Derrota'; $resClass = 'defeat'; }
} elseif ($estadoLower == 'adiado') {
    $resultado = 'Adiado'; $resClass = 'adiado';
} elseif ($estadoLower == 'cancelado') {
    $resultado = 'Cancelado'; $resClass = 'cancelado';
} else {
    $resultado = 'Agendado'; $resClass = 'agendado';
}

$estadoCores = [
    'concluído' => ['bg' => '#dcfce7', 'cor' => '#166534', 'icone' => 'bi-check-circle-fill'],
    'concluido' => ['bg' => '#dcfce7', 'cor' => '#166534', 'icone' => 'bi-check-circle-fill'],
    'decorrido' => ['bg' => '#dcfce7', 'cor' => '#166534', 'icone' => 'bi-check-circle-fill'],
    'agendado'  => ['bg' => '#dbeafe', 'cor' => '#1e40af', 'icone' => 'bi-calendar-check-fill'],
    'adiado'    => ['bg' => '#fee2e2', 'cor' => '#991b1b', 'icone' => 'bi-exclamation-circle-fill'],
    'cancelado' => ['bg' => '#fee2e2', 'cor' => '#991b1b', 'icone' => 'bi-x-circle-fill'],
];
$ec = $estadoCores[$estadoLower] ?? ['bg' => '#f1f5f9', 'cor' => '#475569', 'icone' => 'bi-info-circle-fill'];

$resCores = [
    'win'      => ['bg' => '#dcfce7', 'cor' => '#166534', 'icone' => 'bi-trophy-fill',       'texto' => 'VITÓRIA'],
    'draw'     => ['bg' => '#fef3c7', 'cor' => '#92400e', 'icone' => 'bi-dash-circle-fill',  'texto' => 'EMPATE'],
    'defeat'   => ['bg' => '#fee2e2', 'cor' => '#991b1b', 'icone' => 'bi-x-circle-fill',     'texto' => 'DERROTA'],
    'agendado' => ['bg' => '#dbeafe', 'cor' => '#1e40af', 'icone' => 'bi-calendar-check',    'texto' => 'AGENDADO'],
    'adiado'   => ['bg' => '#fee2e2', 'cor' => '#991b1b', 'icone' => 'bi-exclamation-circle','texto' => 'ADIADO'],
    'cancelado'=> ['bg' => '#fee2e2', 'cor' => '#991b1b', 'icone' => 'bi-x-circle',          'texto' => 'CANCELADO'],
];
$rc = $resCores[$resClass] ?? ['bg' => '#f1f5f9', 'cor' => '#64748b', 'icone' => 'bi-circle', 'texto' => strtoupper($resultado)];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Jogo - SportGes</title>
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
      padding: 2rem;
      width: calc(100% - 240px);
      min-height: 100vh;
    }

    .content-wrapper {
      max-width: 780px;
      margin: 0 auto;
    }

    /* CARD PRINCIPAL */
    .jogo-card {
      background: #fff;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    }

    /* HEADER */
    .jogo-header {
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      padding: 2rem 2rem 1.75rem;
      color: #fff;
      position: relative;
    }

    .jogo-header-top {
      display: flex;
      align-items: flex-start;
      gap: 1.25rem;
      margin-bottom: 1.25rem;
    }

    .jogo-icon {
      width: 56px;
      height: 56px;
      background: rgba(255,255,255,0.15);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.875rem;
      flex-shrink: 0;
    }

    .jogo-header h1 {
      font-size: 1.625rem;
      font-weight: 700;
      margin: 0 0 0.75rem;
      line-height: 1.2;
    }

    .jogo-header-badges {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .header-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      background: rgba(255,255,255,0.18);
      border-radius: 20px;
      padding: 0.3rem 0.875rem;
      font-size: 0.8125rem;
      font-weight: 600;
    }

    /* TABS */
    .jogo-tabs {
      display: flex;
      border-bottom: 2px solid #e2e8f0;
      padding: 0 2rem;
      background: #fff;
    }

    .jogo-tab {
      padding: 1rem 0;
      margin-right: 2rem;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #94a3b8;
      cursor: pointer;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.2s;
      background: none;
      border-top: none;
      border-left: none;
      border-right: none;
      text-decoration: none;
    }

    .jogo-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; }
    .jogo-tab:hover:not(.active) { color: #475569; }

    /* BODY */
    .jogo-body {
      padding: 2rem;
    }

    .jogo-panel { display: none; }
    .jogo-panel.active { display: block; }

    /* GRID de campos */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .info-field {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1.125rem 1.25rem;
    }

    .info-field.full { grid-column: 1 / 3; }

    .info-label {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.6875rem;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      margin-bottom: 0.5rem;
    }

    .info-value {
      font-size: 1rem;
      font-weight: 600;
      color: #1e293b;
    }

    .estado-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.375rem 0.875rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;
    }

    /* RESULTADO */
    .resultado-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .resultado-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 2rem 1.5rem;
      text-align: center;
      grid-column: 1 / 3;
    }

    .resultado-score {
      font-size: 4rem;
      font-weight: 800;
      letter-spacing: 4px;
      line-height: 1;
      margin-bottom: 0.75rem;
    }

    .resultado-label {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 1.25rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .resultado-equipas {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid #e2e8f0;
      gap: 1rem;
    }

    .resultado-equipa {
      text-align: center;
      flex: 1;
    }

    .resultado-equipa-label {
      font-size: 0.6875rem;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.375rem;
    }

    .resultado-equipa-nome {
      font-size: 1rem;
      font-weight: 700;
      color: #1e293b;
    }

    .resultado-vs {
      font-size: 1.25rem;
      font-weight: 700;
      color: #cbd5e1;
      flex-shrink: 0;
    }

    /* FOOTER */
    .jogo-footer {
      padding: 1.5rem 2rem;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }

    .btn-voltar {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: #f1f5f9;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #475569;
      text-decoration: none;
      transition: 0.2s;
    }
    .btn-voltar:hover { background: #e2e8f0; color: #334155; }

    .btn-editar {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.75rem;
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      border: none;
      border-radius: 10px;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #fff;
      text-decoration: none;
      transition: 0.2s;
      box-shadow: 0 4px 12px rgba(79,70,229,0.3);
    }
    .btn-editar:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(79,70,229,0.4); color: #fff; }

    @media (max-width: 1024px) {
      .main-content { margin-left: 0; width: 100%; padding: 1.5rem; }
    }

    @media (max-width: 600px) {
      .main-content { padding: 1rem; }
      .info-grid { grid-template-columns: 1fr; }
      .info-field.full { grid-column: 1; }
      .resultado-grid { grid-template-columns: 1fr; }
      .resultado-box { grid-column: 1; }
      .jogo-header h1 { font-size: 1.25rem; }
      .jogo-body { padding: 1.25rem; }
      .jogo-footer { flex-direction: column; }
      .btn-voltar, .btn-editar { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
  <div class="content-wrapper">

    <div class="jogo-card">

      <!-- HEADER -->
      <div class="jogo-header">
        <div class="jogo-header-top">
          <div class="jogo-icon"><i class='bx bx-football'></i></div>
          <div>
            <h1><?= h($jogo['equipa'] ?? ($jogo['team_nome'] . ' - ' . $jogo['escaloes'])) ?> vs <?= h($jogo['adversario']) ?></h1>
            <div class="jogo-header-badges">
              <span class="header-badge"><i class="bi bi-calendar3"></i> <?= $dataCurta ?></span>
              <span class="header-badge"><i class="bi bi-clock"></i> <?= $hora ?></span>
              <span class="header-badge"><i class="bi <?= $ec['icone'] ?>"></i> <?= h($jogo['estado']) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- TABS -->
      <div class="jogo-tabs">
        <button class="jogo-tab active" onclick="jogoTab(this, 'info')">
          <i class="bi bi-info-circle"></i> Informações
        </button>
        <button class="jogo-tab" onclick="jogoTab(this, 'resultado')">
          <i class="bi bi-trophy"></i> Resultado
        </button>
      </div>

      <!-- BODY -->
      <div class="jogo-body">

        <!-- Painel Informações -->
        <div class="jogo-panel active" id="panelInfo">
          <div class="info-grid">

            <div class="info-field">
              <div class="info-label"><i class="bi bi-calendar3"></i> Data</div>
              <div class="info-value"><?= $dataCurta ?></div>
            </div>

            <div class="info-field">
              <div class="info-label"><i class="bi bi-clock"></i> Hora</div>
              <div class="info-value"><?= $hora ?></div>
            </div>

            <div class="info-field">
              <div class="info-label"><i class="bi bi-people-fill"></i> Equipa</div>
              <div class="info-value"><?= h($jogo['team_nome']) ?></div>
            </div>

            <div class="info-field">
              <div class="info-label"><i class="bi bi-building"></i> Clube</div>
              <div class="info-value"><?= h($jogo['clube_nome']) ?></div>
            </div>

            <div class="info-field">
              <div class="info-label"><i class="bi bi-flag"></i> Escalão</div>
              <div class="info-value"><?= h($jogo['escaloes']) ?></div>
            </div>

            <div class="info-field">
              <div class="info-label"><i class="bi bi-activity"></i> Estado</div>
              <div class="info-value">
                <span class="estado-badge" style="background:<?= $ec['bg'] ?>;color:<?= $ec['cor'] ?>">
                  <i class="bi <?= $ec['icone'] ?>"></i> <?= h($jogo['estado']) ?>
                </span>
              </div>
            </div>

            <?php if (!empty($jogo['temporada_nome'])): ?>
            <div class="info-field">
              <div class="info-label"><i class="bi bi-calendar-range"></i> Época</div>
              <div class="info-value"><?= h($jogo['temporada_nome']) ?></div>
            </div>
            <?php endif; ?>

            <div class="info-field <?= empty($jogo['temporada_nome']) ? 'full' : '' ?>">
              <div class="info-label"><i class="bi bi-geo-alt-fill"></i> Local</div>
              <div class="info-value"><?= h($jogo['local']) ?></div>
            </div>

          </div>
        </div>

        <!-- Painel Resultado -->
        <div class="jogo-panel" id="panelResultado">
          <div class="resultado-box">
            <?php if ($isConcluido): ?>
              <div class="resultado-score" style="color:<?= $rc['cor'] ?>">
                <?= $golosMarcados ?> — <?= $golosSofridos ?>
              </div>
              <span class="resultado-label" style="background:<?= $rc['bg'] ?>;color:<?= $rc['cor'] ?>">
                <i class="bi <?= $rc['icone'] ?>"></i> <?= $rc['texto'] ?>
              </span>
            <?php else: ?>
              <div class="resultado-score" style="color:#94a3b8">— — —</div>
              <span class="resultado-label" style="background:<?= $rc['bg'] ?>;color:<?= $rc['cor'] ?>">
                <i class="bi <?= $rc['icone'] ?>"></i> <?= $rc['texto'] ?>
              </span>
            <?php endif; ?>

            <div class="resultado-equipas">
              <div class="resultado-equipa">
                <div class="resultado-equipa-label">Equipa</div>
                <div class="resultado-equipa-nome"><?= h($jogo['team_nome']) ?></div>
              </div>
              <div class="resultado-vs">vs</div>
              <div class="resultado-equipa">
                <div class="resultado-equipa-label">Adversário</div>
                <div class="resultado-equipa-nome"><?= h($jogo['adversario']) ?></div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- FOOTER -->
      <div class="jogo-footer">
        <a href="listar.php" class="btn-voltar">
          <i class="bi bi-arrow-left"></i> Voltar
        </a>
        <?php if ($isAdmin): ?>
          <a href="editar.php?id=<?= (int)$jogo['id'] ?>" class="btn-editar">
            <i class="bi bi-pencil-fill"></i> Editar
          </a>
        <?php endif; ?>
      </div>

    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function jogoTab(btn, panel) {
  document.querySelectorAll('.jogo-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.jogo-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('panel' + panel.charAt(0).toUpperCase() + panel.slice(1)).classList.add('active');
}
</script>

</body>
</html>