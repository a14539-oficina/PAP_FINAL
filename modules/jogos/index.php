<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
    die("ERRO: Não está logado. user_id não existe na sessão.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ERRO: ID inválido ou não passado. GET id = " . var_export($_GET['id'] ?? null, true));
}

$jogo_id      = intval($_GET['id']);
$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id      = $_SESSION['user_id'];
$user_role    = $_SESSION['user_role'] ?? '';
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

if ($isAdminPrincipal) {
    $sql = "SELECT m.*, t.nome AS team_nome, t.escaloes, c.nome AS clube_nome, s.nome AS temporada_nome
            FROM matches m
            JOIN teams t ON t.id = m.team_id
            JOIN clubs c ON c.id = t.club_id
            LEFT JOIN seasons s ON s.id = m.season_id
            WHERE m.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jogo_id);
} else {
    $sql = "SELECT m.*, t.nome AS team_nome, t.escaloes, c.nome AS clube_nome, s.nome AS temporada_nome
            FROM matches m
            JOIN teams t ON t.id = m.team_id
            JOIN clubs c ON c.id = t.club_id
            LEFT JOIN seasons s ON s.id = m.season_id
            WHERE m.id = ? AND t.club_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $jogo_id, $user_club_id);
}

$stmt->execute();
$res  = $stmt->get_result();
$jogo = $res->fetch_assoc();
$stmt->close();

if (!$jogo) {
    die("ERRO: Jogo não encontrado. <br>
         jogo_id = $jogo_id <br>
         user_club_id = $user_club_id <br>
         user_id = $user_id <br>
         SQL = " . htmlspecialchars($sql));
}

echo "OK: Jogo encontrado — " . htmlspecialchars($jogo['adversario']);

// Buscar o jogo
if ($isAdminPrincipal) {
    $sql = "
        SELECT m.*, 
               t.nome AS team_nome, t.escaloes,
               c.nome AS clube_nome,
               s.nome AS temporada_nome
        FROM matches m
        JOIN teams t ON t.id = m.team_id
        JOIN clubs c ON c.id = t.club_id
        LEFT JOIN seasons s ON s.id = m.season_id
        WHERE m.id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jogo_id);
} else {
    $sql = "
        SELECT m.*, 
               t.nome AS team_nome, t.escaloes,
               c.nome AS clube_nome,
               s.nome AS temporada_nome
        FROM matches m
        JOIN teams t ON t.id = m.team_id
        JOIN clubs c ON c.id = t.club_id
        LEFT JOIN seasons s ON s.id = m.season_id
        WHERE m.id = ? AND t.club_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $jogo_id, $user_club_id);
}

$stmt->execute();
$res = $stmt->get_result();
$jogo = $res->fetch_assoc();
$stmt->close();

if (!$jogo) {
    $_SESSION['erro'] = "Jogo não encontrado ou sem permissão.";
    header("Location: index.php");
    exit;
}

// Helper
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Formatar data
function formatarData($data) {
    if (empty($data)) return ['data' => '--', 'hora' => '--:--', 'dia' => '--', 'mes' => '---', 'ano' => '----'];
    $ts = strtotime($data);
    if (!$ts) return ['data' => '--', 'hora' => '--:--', 'dia' => '--', 'mes' => '---', 'ano' => '----'];
    $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return [
        'data' => date('d/m/Y', $ts),
        'hora' => date('H:i', $ts),
        'dia'  => date('d', $ts),
        'mes'  => $meses[(int)date('n', $ts) - 1],
        'ano'  => date('Y', $ts),
    ];
}

// Resultado
function getResultado($m, $s) {
    if ($m > $s)  return ['label' => 'Vitória',  'class' => 'win',    'icon' => 'bx-trophy',       'emoji' => '🏆'];
    if ($m == $s) return ['label' => 'Empate',   'class' => 'draw',   'icon' => 'bx-minus-circle', 'emoji' => '🤝'];
    return             ['label' => 'Derrota',  'class' => 'defeat', 'icon' => 'bx-x-circle',     'emoji' => '❌'];
}

$dt          = formatarData($jogo['data_jogo']);
$gm          = intval($jogo['golos_marcados']);
$gs          = intval($jogo['golos_sofridos']);
$estadoNorm  = strtolower($jogo['estado'] ?? '');
$isConcluido = in_array($estadoNorm, ['concluído', 'concluido', 'decorrido']);
$resultado   = $isConcluido ? getResultado($gm, $gs) : null;

$estadoConfig = [
    'agendado'  => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'bx-calendar'],
    'concluído' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'bx-check-circle'],
    'concluido' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'bx-check-circle'],
    'decorrido' => ['bg' => '#dcfce7', 'color' => '#166534', 'icon' => 'bx-check-circle'],
    'adiado'    => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'bx-error-circle'],
    'cancelado' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'bx-x-circle'],
];
$estadoCfg = $estadoConfig[$estadoNorm] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'icon' => 'bx-info-circle'];

$resultadoColors = [
    'win'    => ['bg' => '#dcfce7', 'color' => '#166534', 'border' => '#16a34a'],
    'draw'   => ['bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#ca8a04'],
    'defeat' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#dc2626'],
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Jogo - <?= h($jogo['clube_nome']) ?></title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background: #f8fafc;
  color: #1e293b;
}

.main-content {
  margin-left: 240px;
  padding: 2rem;
  width: calc(100% - 240px);
  min-height: 100vh;
  transition: margin-left .3s ease, width .3s ease;
}

.content-wrapper {
  max-width: 860px;
  margin: 0 auto;
}

/* ── Voltar ── */
.btn-back {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  background: white;
  color: #475569;
  border: 1.5px solid #e2e8f0;
  padding: .625rem 1.25rem;
  border-radius: 10px;
  font-size: .9rem;
  font-weight: 600;
  text-decoration: none;
  transition: all .2s;
  margin-bottom: 1.5rem;
}
.btn-back:hover { background: #f1f5f9; color: #1e293b; }

/* ── Card principal ── */
.match-card {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.04);
}

/* ── Hero header ── */
.match-hero {
  background: linear-gradient(135deg, #3b5bdb 0%, #4c6ef5 100%);
  padding: 2rem 2.5rem;
  position: relative;
}

.match-hero::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 40px;
  background: white;
  border-radius: 20px 20px 0 0;
}

.hero-meta {
  display: flex;
  align-items: center;
  gap: .625rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  background: rgba(255,255,255,.18);
  color: #fff;
  font-size: .8rem;
  font-weight: 500;
  padding: .3rem .85rem;
  border-radius: 999px;
}

.hero-teams {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1.5rem;
  text-align: center;
  position: relative;
  z-index: 2;
}

.hero-team {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .5rem;
  flex: 1;
  max-width: 200px;
}

.hero-team-icon {
  width: 56px; height: 56px;
  background: rgba(255,255,255,.2);
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
}

.hero-team-icon i { font-size: 28px; color: #fff; }

.hero-team-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.3;
}

.hero-team-sub {
  font-size: .75rem;
  color: rgba(255,255,255,.75);
  font-weight: 500;
}

.hero-vs {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .375rem;
  min-width: 80px;
}

.hero-score {
  font-size: 2.25rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: 2px;
  line-height: 1;
}

.hero-vs-label {
  font-size: .7rem;
  color: rgba(255,255,255,.6);
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* ── Resultado badge grande ── */
.resultado-banner {
  margin: 1.5rem 2.5rem 0;
  padding: 1rem 1.5rem;
  border-radius: 14px;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.resultado-banner i { font-size: 2rem; }

.resultado-banner .rb-label {
  font-size: .75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .5px;
  opacity: .7;
}

.resultado-banner .rb-title {
  font-size: 1.25rem;
  font-weight: 800;
}

/* ── Tabs ── */
.tabs-nav {
  display: flex;
  gap: 0;
  border-bottom: 1.5px solid #e2e8f0;
  padding: 0 2.5rem;
  margin-top: 1.5rem;
}

.tab-btn {
  padding: .875rem 1.25rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #64748b;
  font-size: .9rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: .4rem;
  transition: all .2s;
  margin-bottom: -1.5px;
}

.tab-btn.active {
  border-bottom-color: #4c6ef5;
  color: #1e293b;
}

.tab-btn i { font-size: 1.1rem; }

/* ── Tab content ── */
.tab-content { display: none; }
.tab-content.active { display: block; }

/* ── Info grid ── */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .875rem;
  padding: 2rem 2.5rem;
}

.info-field {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1rem 1.125rem;
}

.info-field.full { grid-column: 1 / -1; }

.info-field-label {
  font-size: .7rem;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: .3rem;
  margin-bottom: .375rem;
}

.info-field-label i { font-size: .9rem; }

.info-field-value {
  font-size: .9375rem;
  color: #0f172a;
  font-weight: 500;
}

.estado-pill {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .8125rem;
  font-weight: 600;
  padding: .3rem .9rem;
  border-radius: 999px;
}

/* ── Stats tab ── */
.stats-wrap {
  padding: 2rem 2.5rem;
}

.stats-placar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2.5rem;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  margin-bottom: 1.5rem;
}

.stats-team {
  text-align: center;
  flex: 1;
}

.stats-team-name {
  font-size: .8rem;
  color: #64748b;
  font-weight: 600;
  margin-bottom: .375rem;
}

.stats-golo {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1;
}

.stats-golo.marcados { color: #16a34a; }
.stats-golo.sofridos { color: #dc2626; }
.stats-golo.neutro   { color: #94a3b8; }

.stats-sep {
  font-size: 2rem;
  color: #cbd5e1;
  font-weight: 700;
}

.stats-cards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .875rem;
}

.stat-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.25rem;
  text-align: center;
}

.stat-card-label {
  font-size: .75rem;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
  font-weight: 600;
  margin-bottom: .5rem;
}

.stat-card-value {
  font-size: 2rem;
  font-weight: 800;
}

.stat-card-value.verde  { color: #16a34a; }
.stat-card-value.vermelho { color: #dc2626; }

/* ── Sem resultado ── */
.no-result {
  text-align: center;
  padding: 3rem 2rem;
  color: #94a3b8;
}

.no-result i { font-size: 3rem; margin-bottom: .75rem; display: block; }
.no-result p { font-size: .9rem; }

/* ── Admin actions ── */
.match-footer {
  padding: 1.25rem 2.5rem;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: .75rem;
}

.btn-edit {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  background: #2563eb;
  color: #fff;
  border: none;
  padding: .625rem 1.25rem;
  border-radius: 10px;
  font-size: .875rem;
  font-weight: 600;
  text-decoration: none;
  transition: all .2s;
  cursor: pointer;
}

.btn-edit:hover { background: #1d4ed8; color: #fff; }

/* ── Responsive ── */
@media (max-width: 1023px) {
  .main-content { margin-left: 0; width: 100%; padding: 1.5rem; }
}

@media (max-width: 640px) {
  .main-content { padding: 1rem; }
  .match-hero { padding: 1.5rem; }
  .hero-teams { gap: .75rem; }
  .hero-score { font-size: 1.75rem; }
  .hero-team-name { font-size: .9rem; }
  .info-grid { grid-template-columns: 1fr; padding: 1.25rem 1.5rem; }
  .info-field.full { grid-column: 1; }
  .tabs-nav { padding: 0 1.5rem; }
  .resultado-banner { margin: 1.25rem 1.5rem 0; }
  .stats-wrap { padding: 1.25rem 1.5rem; }
  .match-footer { padding: 1rem 1.5rem; flex-wrap: wrap; }
}
</style>
</head>

<?php require('../../includes/sidebar.php'); ?>

<body>
<div class="main-content">
<div class="content-wrapper">

  <!-- Voltar -->
  <a href="index.php" class="btn-back">
    <i class='bx bx-arrow-back'></i> Voltar aos jogos
  </a>

  <div class="match-card">

    <!-- ── HERO ── -->
    <div class="match-hero">

      <!-- Meta badges -->
      <div class="hero-meta">
        <span class="hero-badge"><i class='bx bx-calendar'></i> <?= h($dt['data']) ?></span>
        <span class="hero-badge"><i class='bx bx-time'></i> <?= h($dt['hora']) ?></span>
        <?php if (!empty($jogo['temporada_nome'])): ?>
        <span class="hero-badge"><i class='bx bx-flag'></i> <?= h($jogo['temporada_nome']) ?></span>
        <?php endif; ?>
        <span class="hero-badge">
          <i class='bx <?= h($jogo['local'] == 'Casa' ? 'bx-home' : 'bx-map') ?>'></i>
          <?= h($jogo['local']) ?>
        </span>
      </div>

      <!-- Equipas + placar -->
      <div class="hero-teams">

        <div class="hero-team">
          <div class="hero-team-icon"><i class='bx bx-shield'></i></div>
          <div class="hero-team-name"><?= h($jogo['clube_nome']) ?></div>
          <div class="hero-team-sub"><?= h($jogo['escaloes']) ?></div>
        </div>

        <div class="hero-vs">
          <?php if ($isConcluido): ?>
            <div class="hero-score"><?= $gm ?> — <?= $gs ?></div>
          <?php else: ?>
            <div class="hero-score">vs</div>
          <?php endif; ?>
          <div class="hero-vs-label"><?= h($jogo['estado']) ?></div>
        </div>

        <div class="hero-team">
          <div class="hero-team-icon"><i class='bx bx-shield-alt-2'></i></div>
          <div class="hero-team-name"><?= h($jogo['adversario']) ?></div>
          <div class="hero-team-sub">Adversário</div>
        </div>

      </div>
    </div>

    <!-- ── Resultado banner ── -->
    <?php if ($isConcluido && $resultado): 
          $rc = $resultadoColors[$resultado['class']]; ?>
    <div class="resultado-banner" style="background:<?= $rc['bg'] ?>; color:<?= $rc['color'] ?>; border: 1.5px solid <?= $rc['border'] ?>33;">
      <i class='bx <?= $resultado['icon'] ?>' style="color:<?= $rc['color'] ?>;"></i>
      <div>
        <div class="rb-label">Resultado</div>
        <div class="rb-title"><?= $resultado['emoji'] ?> <?= $resultado['label'] ?></div>
      </div>
      <div style="margin-left:auto; font-size:2rem; font-weight:800; color:<?= $rc['color'] ?>;">
        <?= $gm ?> — <?= $gs ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── TABS ── -->
    <div class="tabs-nav">
      <button class="tab-btn active" onclick="setTab('info', this)">
        <i class='bx bx-info-circle'></i> Informações
      </button>
      <button class="tab-btn" onclick="setTab('stats', this)">
        <i class='bx bx-bar-chart-alt-2'></i> Resultado & Golos
      </button>
    </div>

    <!-- ── TAB: INFORMAÇÕES ── -->
    <div id="tab-info" class="tab-content active">
      <div class="info-grid">

        <div class="info-field">
          <div class="info-field-label"><i class='bx bx-calendar'></i> Data</div>
          <div class="info-field-value"><?= h($dt['data']) ?></div>
        </div>

        <div class="info-field">
          <div class="info-field-label"><i class='bx bx-time'></i> Hora</div>
          <div class="info-field-value"><?= h($dt['hora']) ?></div>
        </div>

        <div class="info-field">
          <div class="info-field-label"><i class='bx bx-group'></i> Equipa</div>
          <div class="info-field-value"><?= h($jogo['clube_nome']) ?> — <?= h($jogo['team_nome'] ?? $jogo['escaloes']) ?></div>
        </div>

        <div class="info-field">
          <div class="info-field-label"><i class='bx bx-shield-alt-2'></i> Adversário</div>
          <div class="info-field-value"><?= h($jogo['adversario']) ?></div>
        </div>

        <div class="info-field">
          <div class="info-field-label"><i class='bx bx-universal-access'></i> Escalão</div>
          <div class="info-field-value"><?= h($jogo['escaloes']) ?></div>
        </div>

        <div class="info-field">
          <div class="info-field-label">
            <i class='bx <?= $jogo['local'] == 'Casa' ? 'bx-home' : 'bx-map' ?>'></i> Local
          </div>
          <div class="info-field-value"><?= h($jogo['local']) ?></div>
        </div>

        <?php if (!empty($jogo['temporada_nome'])): ?>
        <div class="info-field">
          <div class="info-field-label"><i class='bx bx-flag'></i> Temporada</div>
          <div class="info-field-value"><?= h($jogo['temporada_nome']) ?></div>
        </div>
        <?php endif; ?>

        <div class="info-field">
          <div class="info-field-label"><i class='bx bx-pulse'></i> Estado</div>
          <span class="estado-pill" style="background:<?= $estadoCfg['bg'] ?>; color:<?= $estadoCfg['color'] ?>;">
            <i class='bx <?= $estadoCfg['icon'] ?>'></i>
            <?= h($jogo['estado']) ?>
          </span>
        </div>

        <?php if (!empty($jogo['notas']) || !empty($jogo['observacoes'])): 
              $nota = $jogo['notas'] ?? $jogo['observacoes'] ?? ''; ?>
        <div class="info-field full">
          <div class="info-field-label"><i class='bx bx-note'></i> Notas</div>
          <div class="info-field-value" style="white-space:pre-wrap;"><?= h($nota) ?></div>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- ── TAB: RESULTADO & GOLOS ── -->
    <div id="tab-stats" class="tab-content">
      <?php if ($isConcluido): ?>
      <div class="stats-wrap">

        <!-- Placar grande -->
        <div class="stats-placar">
          <div class="stats-team">
            <div class="stats-team-name"><?= h($jogo['clube_nome']) ?></div>
            <div class="stats-golo marcados"><?= $gm ?></div>
          </div>
          <div class="stats-sep">—</div>
          <div class="stats-team">
            <div class="stats-team-name"><?= h($jogo['adversario']) ?></div>
            <div class="stats-golo sofridos"><?= $gs ?></div>
          </div>
        </div>

        <!-- Mini stat cards -->
        <div class="stats-cards">
          <div class="stat-card">
            <div class="stat-card-label">Golos marcados</div>
            <div class="stat-card-value verde"><?= $gm ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-card-label">Golos sofridos</div>
            <div class="stat-card-value vermelho"><?= $gs ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-card-label">Diferença de golos</div>
            <div class="stat-card-value <?= ($gm - $gs) >= 0 ? 'verde' : 'vermelho' ?>">
              <?= ($gm - $gs) >= 0 ? '+' : '' ?><?= $gm - $gs ?>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-label">Resultado</div>
            <?php if ($resultado): $rc = $resultadoColors[$resultado['class']]; ?>
            <div style="font-size:1.25rem; font-weight:800; color:<?= $rc['color'] ?>;">
              <?= $resultado['emoji'] ?> <?= $resultado['label'] ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
      <?php else: ?>
      <div class="no-result">
        <i class='bx bx-time-five'></i>
        <p>O jogo ainda não foi concluído.<br>O resultado estará disponível após o fim do jogo.</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Footer com ações (só admin) ── -->
    <?php if ($isAdmin): ?>
    <div class="match-footer">
      <a href="editar.php?id=<?= $jogo_id ?>" class="btn-edit">
        <i class='bx bx-edit'></i> Editar Jogo
      </a>
    </div>
    <?php endif; ?>

  </div><!-- /match-card -->

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setTab(tabId, btn) {
  document.querySelectorAll('.tab-content').forEach(function(el) {
    el.classList.remove('active');
  });
  document.querySelectorAll('.tab-btn').forEach(function(el) {
    el.classList.remove('active');
  });
  document.getElementById('tab-' + tabId).classList.add('active');
  btn.classList.add('active');
}
</script>
</body>
</html>