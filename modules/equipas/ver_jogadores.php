<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id = $_SESSION['user_id'];
$isAdminPrincipal = (isset($_SESSION['user_role']) && (string)$_SESSION['user_role'] === '0');

if ($user_club_id <= 0 && !$isAdminPrincipal) {
  die("Erro: Utilizador sem clube associado.");
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function getFotoPath($foto) {
  if (empty($foto)) return '';
  if (strpos($foto, '/logos/') === 0) return $foto;
  return '/logos/' . $foto;
}

$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
if ($team_id <= 0) {
  header("Location: listar.php");
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($isAdminPrincipal) {
  $stmtTeam = $conn->prepare("SELECT t.id, t.nome, t.escaloes, t.ativo, t.logo, c.nome AS clube_nome, c.logo AS clube_logo
                               FROM teams t LEFT JOIN clubs c ON t.club_id = c.id
                               WHERE t.id = ?");
  $stmtTeam->bind_param("i", $team_id);
} else {
  $stmtTeam = $conn->prepare("SELECT t.id, t.nome, t.escaloes, t.ativo, t.logo, c.nome AS clube_nome, c.logo AS clube_logo
                               FROM teams t LEFT JOIN clubs c ON t.club_id = c.id
                               WHERE t.id = ? AND t.club_id = ?");
  $stmtTeam->bind_param("ii", $team_id, $user_club_id);
}
$stmtTeam->execute();
$team = $stmtTeam->get_result()->fetch_assoc();
$stmtTeam->close();

if (!$team) {
  header("Location: listar.php");
  exit;
}

$display_name = !empty($team['nome']) ? $team['nome'] : $team['clube_nome'] . ' - ' . $team['escaloes'];

$stmtPlayers = $conn->prepare("
  SELECT p.id, p.primeiro_nome, p.ultimo_nome, p.numero_camisola, p.position_id,
         pos.name AS posicao_nome, pos.code AS posicao_code,
         p.data_nascimento, p.foto, p.ativo
  FROM players p
  LEFT JOIN positions pos ON p.position_id = pos.id
  WHERE p.team_id = ?
  ORDER BY p.numero_camisola ASC, p.ultimo_nome ASC
");
$stmtPlayers->bind_param("i", $team_id);
$stmtPlayers->execute();
$playersResult = $stmtPlayers->get_result();
$allPlayers = [];
while ($p = $playersResult->fetch_assoc()) {
  $allPlayers[] = $p;
}
$totalJogadores = count($allPlayers);
$stmtPlayers->close();

$playerIds = array_column($allPlayers, 'id');
$statsMap = [];
if (!empty($playerIds)) {
  $in = implode(',', array_map('intval', $playerIds));
  $stmtStats = $conn->query("
    SELECT player_id,
           COUNT(*) AS jogos,
           SUM(started) AS titulares,
           SUM(COALESCE(minutes_played, 0)) AS minutos,
           SUM(goals) AS golos,
           SUM(assists) AS assistencias,
           SUM(yellow_cards) AS amarelos,
           SUM(red_cards) AS vermelhos
    FROM player_match_stats
    WHERE player_id IN ($in)
    GROUP BY player_id
  ");
  while ($s = $stmtStats->fetch_assoc()) {
    $statsMap[$s['player_id']] = $s;
  }
}

if ($isAdminPrincipal) {
  $stmtEquipas = $conn->query("SELECT id, nome, escaloes FROM teams ORDER BY escaloes ASC");
} else {
  $stmtEquipas = $conn->prepare("SELECT id, nome, escaloes FROM teams WHERE club_id = ? ORDER BY escaloes ASC");
  $stmtEquipas->bind_param("i", $user_club_id);
  $stmtEquipas->execute();
  $stmtEquipas = $stmtEquipas->get_result();
}
$equipas = [];
while ($e = $stmtEquipas->fetch_assoc()) {
  if ($e['id'] != $team_id) $equipas[] = $e;
}

$logo_path = '';
if (!empty($team['logo']) && file_exists('logos/' . $team['logo'])) {
  $logo_path = 'logos/' . $team['logo'];
} elseif (!empty($team['clube_logo']) && file_exists('../clubes/uploads/' . $team['clube_logo'])) {
  $logo_path = '../clubes/uploads/' . $team['clube_logo'];
}

$podeEditar = in_array($_SESSION['user_role'] ?? '4', ['0','1','2','3']);

$playersJS = [];
foreach ($allPlayers as $p) {
  $stats = $statsMap[$p['id']] ?? ['jogos'=>0,'titulares'=>0,'minutos'=>0,'golos'=>0,'assistencias'=>0,'amarelos'=>0,'vermelhos'=>0];
  $nome = trim(($p['primeiro_nome'] ?? '') . ' ' . ($p['ultimo_nome'] ?? ''));
  if (empty($nome)) $nome = 'Sem nome';
  $foto = getFotoPath($p['foto']);
  $idade = '';
  if (!empty($p['data_nascimento'])) {
    $dn = new DateTime($p['data_nascimento']);
    $hoje = new DateTime();
    $idade = $dn->diff($hoje)->y . ' anos';
  }
  $playersJS[] = [
    'id'           => (int)$p['id'],
    'nome'         => $nome,
    'foto'         => $foto,
    'numero'       => $p['numero_camisola'],
    'posicao'      => $p['posicao_nome'] ?? '',
    'posicao_code' => $p['posicao_code'] ?? '',
    'idade'        => $idade,
    'nascimento'   => $p['data_nascimento'] ?? '',
    'ativo'        => (bool)$p['ativo'],
    'jogos'        => (int)($stats['jogos'] ?? 0),
    'titulares'    => (int)($stats['titulares'] ?? 0),
    'minutos'      => (int)($stats['minutos'] ?? 0),
    'golos'        => (int)($stats['golos'] ?? 0),
    'assistencias' => (int)($stats['assistencias'] ?? 0),
    'amarelos'     => (int)($stats['amarelos'] ?? 0),
    'vermelhos'    => (int)($stats['vermelhos'] ?? 0),
  ];
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($display_name) ?> - Jogadores</title>
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
    }

    .content-wrapper { max-width: 1400px; margin: 0 auto; }

    .page-header {
      background: #fff;
      padding: 1.5rem 2rem;
      border-radius: 16px;
      margin-bottom: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .header-left { display: flex; align-items: center; gap: 1rem; }

    .team-logo-header {
      width: 56px; height: 56px; border-radius: 12px;
      background: linear-gradient(135deg, #3b82f6, #2563eb);
      display: flex; align-items: center; justify-content: center;
      color: white; font-size: 1.5rem; overflow: hidden; flex-shrink: 0;
    }
    .team-logo-header img { width: 100%; height: 100%; object-fit: cover; }

    .header-titles h1 { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 0.25rem 0; }
    .header-titles .subtitle { font-size: 0.875rem; color: #64748b; display: flex; align-items: center; gap: 0.5rem; }

    .status-badge {
      display: inline-flex; align-items: center; padding: 0.2rem 0.6rem;
      border-radius: 6px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;
    }
    .status-badge.active { background: #dcfce7; color: #166534; }
    .status-badge.inactive { background: #f3f4f6; color: #6b7280; }

    .btn-back {
      background: white; border: 2px solid #e2e8f0; color: #475569;
      padding: 0.75rem 1.5rem; border-radius: 12px; font-size: 0.9rem;
      font-weight: 600; text-decoration: none; display: flex; align-items: center;
      gap: 0.5rem; transition: all 0.2s;
    }
    .btn-back:hover { border-color: #cbd5e1; background: #f8fafc; color: #334155; }

    .search-section {
      background: white; border-radius: 12px; padding: 1rem 1.5rem;
      margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    }

    .search-wrapper { position: relative; flex: 1; min-width: 200px; }
    .search-wrapper i { position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }

    .search-input {
      width: 100%; padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem;
      transition: all 0.2s; background: #f8fafc;
    }
    .search-input:focus { outline: none; border-color: #3b82f6; background: white; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); }

    .total-badge {
      background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;
      border-radius: 8px; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 600; white-space: nowrap;
    }

    .view-toggle { display: flex; border: 2px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .view-btn { padding: 0.5rem 0.75rem; background: white; border: none; color: #94a3b8; cursor: pointer; transition: all 0.2s; font-size: 1rem; }
    .view-btn.active { background: #3b82f6; color: white; }

    .players-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }

    .player-card {
      background: white; border: 1px solid #e5e7eb; border-radius: 14px;
      padding: 1.25rem; display: flex; align-items: center; gap: 1rem;
      transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      cursor: pointer;
    }
    .player-card:hover {
      border-color: #93c5fd; box-shadow: 0 4px 16px rgba(59,130,246,0.12);
      transform: translateY(-2px);
    }

    .player-avatar {
      width: 56px; height: 56px; border-radius: 50%;
      background: linear-gradient(135deg, #e0f2fe, #bfdbfe);
      display: flex; align-items: center; justify-content: center;
      color: #2563eb; font-size: 1.4rem; flex-shrink: 0;
      overflow: hidden; border: 2px solid #dbeafe; position: relative;
    }
    .player-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .number-badge {
      position: absolute; bottom: -4px; right: -4px;
      background: #3b82f6; color: white; font-size: 0.65rem; font-weight: 800;
      border-radius: 50%; width: 20px; height: 20px;
      display: flex; align-items: center; justify-content: center; border: 2px solid white;
    }

    .player-info { flex: 1; min-width: 0; }
    .player-name { font-size: 0.9375rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .player-meta { display: flex; flex-direction: column; gap: 0.2rem; }
    .player-meta-item { display: flex; align-items: center; gap: 0.375rem; font-size: 0.8rem; color: #64748b; }
    .player-meta-item i { font-size: 0.75rem; color: #94a3b8; }
    .player-status { flex-shrink: 0; }

    .dot { width: 8px; height: 8px; border-radius: 50%; }
    .dot.active { background: #22c55e; }
    .dot.inactive { background: #d1d5db; }

    .posicao-pill {
      background: #f1f5f9; color: #475569; border-radius: 6px;
      padding: 0.15rem 0.5rem; font-size: 0.7rem; font-weight: 600;
      text-transform: uppercase; letter-spacing: 0.3px;
    }

    .players-list { display: grid; gap: 0.5rem; }
    .player-list-row {
      background: white; border: 1px solid #e5e7eb; border-radius: 10px;
      padding: 0.875rem 1.25rem; display: grid;
      grid-template-columns: 40px 48px 1fr auto auto auto;
      align-items: center; gap: 1rem; transition: all 0.2s; cursor: pointer;
    }
    .player-list-row:hover { border-color: #bfdbfe; background: #fafcff; }
    .num-col { font-size: 1.125rem; font-weight: 800; color: #3b82f6; text-align: center; }

    .empty-state {
      background: white; border: 2px dashed #cbd5e1; border-radius: 16px;
      padding: 4rem 2rem; text-align: center;
    }
    .empty-state i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1.5rem; display: block; }
    .empty-state h3 { font-size: 1.5rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem; }
    .empty-state p { color: #64748b; }

    /* ===== MODAL ===== */
    .modal-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.45);
      backdrop-filter: blur(4px);
      z-index: 99999;
      display: none;
      pointer-events: none;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .modal-overlay.show {
      display: flex;
      pointer-events: auto;
    }

    .modal-box {
      background: white; border-radius: 20px; width: 100%; max-width: 580px;
      max-height: 90vh; overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
      animation: modalIn 0.25s ease;
      position: relative;
      z-index: 100000;
    }

    @keyframes modalIn {
      from { opacity: 0; transform: translateY(20px) scale(0.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .modal-header {
      padding: 1.5rem 1.5rem 1rem;
      display: flex; align-items: center; gap: 1rem;
      border-bottom: 1px solid #f1f5f9;
      position: relative;
    }

    .modal-avatar {
      width: 72px; height: 72px; border-radius: 50%;
      background: linear-gradient(135deg, #e0f2fe, #bfdbfe);
      display: flex; align-items: center; justify-content: center;
      color: #2563eb; font-size: 2rem; overflow: hidden;
      border: 3px solid #dbeafe; flex-shrink: 0; position: relative;
    }
    .modal-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .modal-avatar .number-badge { width: 24px; height: 24px; font-size: 0.75rem; }

    .modal-player-info h2 { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem; }
    .modal-player-info .meta { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }

    .btn-close-modal {
      position: absolute; top: 1rem; right: 1rem;
      background: #f1f5f9; border: none; border-radius: 8px;
      width: 32px; height: 32px; cursor: pointer; font-size: 1rem;
      display: flex; align-items: center; justify-content: center;
      color: #64748b; transition: all 0.2s;
    }
    .btn-close-modal:hover { background: #e2e8f0; color: #334155; }

    .modal-body { padding: 1.25rem 1.5rem; }

    .stats-grid {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;
      margin-bottom: 1.25rem;
    }

    .stat-card {
      background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
      padding: 1rem 0.75rem; text-align: center;
    }
    .stat-card .stat-value { font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1; }
    .stat-card .stat-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; margin-top: 0.35rem; }

    .stat-card.goals .stat-value { color: #16a34a; }
    .stat-card.assists .stat-value { color: #2563eb; }
    .stat-card.yellow .stat-value { color: #ca8a04; }
    .stat-card.red .stat-value { color: #dc2626; }

    .info-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.625rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem;
    }
    .info-row:last-child { border-bottom: none; }
    .info-row .label { color: #64748b; font-weight: 500; display: flex; align-items: center; gap: 0.4rem; }
    .info-row .value { font-weight: 600; color: #1e293b; }

    .section-title {
      font-size: 0.875rem; font-weight: 700; color: #475569;
      text-transform: uppercase; letter-spacing: 0.5px;
      margin: 1.25rem 0 0.75rem;
    }

    /* ===== BOTÃO TRANSFERIR JOGADOR ===== */
    .btn-transferir-jogador {
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
      width: 100%; padding: 0.75rem 1rem;
      background: linear-gradient(135deg, #3b82f6, #2563eb);
      color: white; border: none; border-radius: 12px;
      font-size: 0.9rem; font-weight: 700; cursor: pointer;
      transition: all 0.2s; margin-bottom: 0.25rem;
      box-shadow: 0 2px 8px rgba(59,130,246,0.3);
    }
    .btn-transferir-jogador:hover {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      box-shadow: 0 4px 14px rgba(59,130,246,0.4);
      transform: translateY(-1px);
    }
    .btn-transferir-jogador i { font-size: 1rem; }

    .equipas-list { display: grid; gap: 0.5rem; }

    .equipa-option {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px;
      background: white; transition: all 0.2s;
    }
    .equipa-option:hover { border-color: #3b82f6; background: #eff6ff; }
    .equipa-option .eq-name { font-weight: 600; font-size: 0.9rem; color: #1e293b; }
    .equipa-option .eq-esc { font-size: 0.75rem; color: #94a3b8; }
    .equipa-option .btn-transferir {
      background: #3b82f6; color: white; border: none; border-radius: 8px;
      padding: 0.4rem 0.875rem; font-size: 0.8rem; font-weight: 600; cursor: pointer;
      transition: all 0.2s;
    }
    .equipa-option .btn-transferir:hover { background: #2563eb; }

    .no-equipas { text-align: center; color: #94a3b8; font-size: 0.875rem; padding: 1rem; }

    .transfer-success {
      background: #dcfce7; border: 1px solid #86efac; color: #166534;
      border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.875rem;
      font-weight: 600; display: none; align-items: center; gap: 0.5rem;
      margin-top: 0.75rem;
    }
    .transfer-success.show { display: flex; }

    /* Secção de transferência colapsável */
    .transfer-section { display: none; }
    .transfer-section.show { display: block; }

    @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; padding: 16px; } }
    @media (max-width: 768px) {
      .players-grid { grid-template-columns: 1fr; }
      .page-header { padding: 1.25rem; }
      .stats-grid { grid-template-columns: repeat(3, 1fr); }
      .player-list-row { grid-template-columns: 36px 36px 1fr auto; }
    }
    @media (max-width: 480px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .modal-box { border-radius: 16px; }
    }
  </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
  <div class="content-wrapper">

    <div class="page-header">
      <div class="header-left">
        <div class="team-logo-header">
          <?php if (!empty($logo_path)): ?>
            <img src="<?= h($logo_path) ?>" alt="logo">
          <?php else: ?>
            <i class="bi bi-shield-fill"></i>
          <?php endif; ?>
        </div>
        <div class="header-titles">
          <h1><?= h($display_name) ?></h1>
          <div class="subtitle">
            <i class="bi bi-award"></i>
            <?= h($team['escaloes']) ?>
            &nbsp;•&nbsp;
            <span class="status-badge <?= $team['ativo'] ? 'active' : 'inactive' ?>">
              <?= $team['ativo'] ? 'Ativa' : 'Inativa' ?>
            </span>
          </div>
        </div>
      </div>
      <a href="listar.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>
        Voltar às equipas
      </a>
    </div>

    <?php if ($totalJogadores === 0): ?>
      <div class="empty-state">
        <i class="bi bi-person-plus"></i>
        <h3>Nenhum jogador nesta equipa</h3>
        <p>Ainda não há jogadores associados a esta equipa.</p>
      </div>
    <?php else: ?>

    <div class="search-section">
      <div class="search-wrapper">
        <i class="bi bi-search"></i>
        <input type="search" class="search-input" id="searchPlayers" placeholder="Pesquisar jogador...">
      </div>
      <div class="total-badge">
        <i class="bi bi-people-fill"></i>
        <?= $totalJogadores ?> jogador<?= $totalJogadores != 1 ? 'es' : '' ?>
      </div>
      <div class="view-toggle">
        <button class="view-btn active" id="btnGrid" title="Vista em grelha">
          <i class="bi bi-grid-3x3-gap-fill"></i>
        </button>
        <button class="view-btn" id="btnList" title="Vista em lista">
          <i class="bi bi-list-ul"></i>
        </button>
      </div>
    </div>

    <!-- Grid View -->
    <div class="players-grid" id="gridView">
      <?php foreach ($allPlayers as $p):
        $nome_completo = trim(($p['primeiro_nome'] ?? '') . ' ' . ($p['ultimo_nome'] ?? ''));
        if (empty($nome_completo)) $nome_completo = 'Sem nome';
        $foto_path = getFotoPath($p['foto']);
        $idade = '';
        if (!empty($p['data_nascimento'])) {
          $dn = new DateTime($p['data_nascimento']);
          $hoje = new DateTime();
          $idade = $dn->diff($hoje)->y . ' anos';
        }
        $posicao = !empty($p['posicao_code']) ? $p['posicao_code'] : (!empty($p['posicao_nome']) ? $p['posicao_nome'] : '');
      ?>
      <div class="player-card player-item" data-player-id="<?= (int)$p['id'] ?>">
        <div class="player-avatar">
          <?php if (!empty($foto_path)): ?>
            <img src="<?= h($foto_path) ?>" alt="<?= h($nome_completo) ?>">
          <?php else: ?>
            <i class="bi bi-person-fill"></i>
          <?php endif; ?>
          <?php if (!empty($p['numero_camisola'])): ?>
            <span class="number-badge"><?= (int)$p['numero_camisola'] ?></span>
          <?php endif; ?>
        </div>
        <div class="player-info">
          <div class="player-name"><?= h($nome_completo) ?></div>
          <div class="player-meta">
            <?php if (!empty($posicao)): ?>
            <div class="player-meta-item">
              <i class="bi bi-geo-alt-fill"></i>
              <span class="posicao-pill"><?= h($posicao) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($idade)): ?>
            <div class="player-meta-item">
              <i class="bi bi-calendar3"></i>
              <span><?= h($idade) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="player-status">
          <div class="dot <?= $p['ativo'] ? 'active' : 'inactive' ?>"
               title="<?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- List View -->
    <div class="players-list" id="listView" style="display:none;">
      <?php foreach ($allPlayers as $p):
        $nome_completo = trim(($p['primeiro_nome'] ?? '') . ' ' . ($p['ultimo_nome'] ?? ''));
        if (empty($nome_completo)) $nome_completo = 'Sem nome';
        $foto_path = getFotoPath($p['foto']);
        $idade = '';
        if (!empty($p['data_nascimento'])) {
          $dn = new DateTime($p['data_nascimento']);
          $hoje = new DateTime();
          $idade = $dn->diff($hoje)->y . ' anos';
        }
        $posicao = !empty($p['posicao_code']) ? $p['posicao_code'] : (!empty($p['posicao_nome']) ? $p['posicao_nome'] : '—');
      ?>
      <div class="player-list-row player-item" data-player-id="<?= (int)$p['id'] ?>">
        <div class="num-col"><?= !empty($p['numero_camisola']) ? (int)$p['numero_camisola'] : '—' ?></div>
        <div class="player-avatar" style="width:36px;height:36px;font-size:1rem;position:relative;">
          <?php if (!empty($foto_path)): ?>
            <img src="<?= h($foto_path) ?>" alt="">
          <?php else: ?>
            <i class="bi bi-person-fill"></i>
          <?php endif; ?>
        </div>
        <div style="font-weight:600;font-size:0.9rem;color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          <?= h($nome_completo) ?>
        </div>
        <span class="posicao-pill"><?= h($posicao) ?></span>
        <span style="font-size:0.8rem;color:#64748b;"><?= h($idade) ?></span>
        <div class="dot <?= $p['ativo'] ? 'active' : 'inactive' ?>"
             title="<?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>"></div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>

  </div><!-- /content-wrapper -->
</div><!-- /main-content -->

<!-- ===== MODAL — FORA DE TUDO, MESMO ANTES DO </body> ===== -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box" id="modalBox">
    <div class="modal-header">
      <div class="modal-avatar" id="modalAvatar">
        <i class="bi bi-person-fill"></i>
      </div>
      <div class="modal-player-info">
        <h2 id="modalNome">—</h2>
        <div class="meta" id="modalMeta"></div>
      </div>
      <button class="btn-close-modal" id="btnCloseModal">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-body">
      <div class="section-title"><i class="bi bi-bar-chart-fill"></i> Estatísticas da época</div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value" id="statJogos">0</div>
          <div class="stat-label">Jogos</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" id="statMinutos">0</div>
          <div class="stat-label">Minutos</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" id="statTitulares">0</div>
          <div class="stat-label">Titular</div>
        </div>
        <div class="stat-card goals">
          <div class="stat-value" id="statGolos">0</div>
          <div class="stat-label">Golos</div>
        </div>
        <div class="stat-card assists">
          <div class="stat-value" id="statAssistencias">0</div>
          <div class="stat-label">Assist.</div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-value" id="statAmarelos">0</div>
          <div class="stat-label">Amarelos</div>
        </div>
      </div>

      <?php if ($podeEditar && !empty($equipas)): ?>
      <button class="btn-transferir-jogador" id="btnMostrarTransfer">
        <i class="bi bi-arrow-left-right"></i>
        Transferir jogador para...
      </button>
      <?php endif; ?>

      <div class="section-title"><i class="bi bi-person-lines-fill"></i> Informação</div>
      <div id="modalInfoRows"></div>

      <?php if ($podeEditar && !empty($equipas)): ?>
      <div class="transfer-section" id="transferSection">
        <div class="section-title"><i class="bi bi-arrow-left-right"></i> Escolher equipa de destino</div>
        <div class="equipas-list" id="equipasList">
          <?php foreach ($equipas as $eq):
            $eq_nome = !empty($eq['nome']) ? $eq['nome'] : $eq['escaloes'];
          ?>
          <div class="equipa-option">
            <div>
              <div class="eq-name"><?= h($eq_nome) ?></div>
              <div class="eq-esc"><?= h($eq['escaloes']) ?></div>
            </div>
            <button class="btn-transferir"
                    data-equipa-id="<?= (int)$eq['id'] ?>"
                    data-equipa-nome="<?= h($eq_nome) ?>">
              Transferir
            </button>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="transfer-success" id="transferSuccess">
          <i class="bi bi-check-circle-fill"></i>
          <span id="transferMsg"></span>
        </div>
      </div>
      <?php elseif ($podeEditar): ?>
      <div class="section-title"><i class="bi bi-arrow-left-right"></i> Mudar de equipa</div>
      <div class="no-equipas">Não existem outras equipas no clube.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
var playersData = <?= json_encode($playersJS, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var currentPlayerId = null;

var modalOverlay    = document.getElementById('modalOverlay');
var modalBox        = document.getElementById('modalBox');
var modalAvatar     = document.getElementById('modalAvatar');
var modalNome       = document.getElementById('modalNome');
var modalMeta       = document.getElementById('modalMeta');
var modalInfoRows   = document.getElementById('modalInfoRows');
var transferSuccess = document.getElementById('transferSuccess');
var transferMsg     = document.getElementById('transferMsg');
var transferSection = document.getElementById('transferSection');
var btnMostrarTransfer = document.getElementById('btnMostrarTransfer');

if (btnMostrarTransfer) {
  btnMostrarTransfer.addEventListener('click', function() {
    var isOpen = transferSection.classList.contains('show');
    if (isOpen) {
      transferSection.classList.remove('show');
      btnMostrarTransfer.innerHTML = '<i class="bi bi-arrow-left-right"></i> Transferir jogador para...';
    } else {
      transferSection.classList.add('show');
      btnMostrarTransfer.innerHTML = '<i class="bi bi-chevron-up"></i> Fechar transferência';
      setTimeout(function() {
        transferSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 50);
    }
  });
}

function abrirModal(id) {
  var p = null;
  for (var i = 0; i < playersData.length; i++) {
    if (playersData[i].id === id) { p = playersData[i]; break; }
  }
  if (!p) return;
  currentPlayerId = id;

  var numBadge = p.numero ? '<span class="number-badge">' + p.numero + '</span>' : '';
  if (p.foto) {
    modalAvatar.innerHTML = '<img src="' + p.foto + '" alt="">' + numBadge;
  } else {
    modalAvatar.innerHTML = '<i class="bi bi-person-fill"></i>' + numBadge;
  }

  modalNome.textContent = p.nome;

  modalMeta.innerHTML = '';
  if (p.posicao_code || p.posicao) {
    modalMeta.innerHTML += '<span class="posicao-pill">' + (p.posicao_code || p.posicao) + '</span>';
  }
  modalMeta.innerHTML += '<span class="status-badge ' + (p.ativo ? 'active' : 'inactive') + '">' + (p.ativo ? 'Ativo' : 'Inativo') + '</span>';

  document.getElementById('statJogos').textContent        = p.jogos;
  document.getElementById('statMinutos').textContent      = p.minutos;
  document.getElementById('statTitulares').textContent    = p.titulares;
  document.getElementById('statGolos').textContent        = p.golos;
  document.getElementById('statAssistencias').textContent = p.assistencias;
  document.getElementById('statAmarelos').textContent     = p.amarelos;

  var rows = [];
  if (p.posicao)    rows.push(['<i class="bi bi-geo-alt"></i> Posição',  p.posicao]);
  if (p.idade)      rows.push(['<i class="bi bi-calendar3"></i> Idade',  p.idade]);
  if (p.nascimento) rows.push(['<i class="bi bi-cake2"></i> Nascimento', p.nascimento]);
  if (p.numero)     rows.push(['<i class="bi bi-hash"></i> Camisola',    '#' + p.numero]);

  modalInfoRows.innerHTML = rows.map(function(r) {
    return '<div class="info-row"><span class="label">' + r[0] + '</span><span class="value">' + r[1] + '</span></div>';
  }).join('');

  if (transferSection) transferSection.classList.remove('show');
  if (btnMostrarTransfer) btnMostrarTransfer.innerHTML = '<i class="bi bi-arrow-left-right"></i> Transferir jogador para...';
  if (transferSuccess) transferSuccess.classList.remove('show');

  modalOverlay.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function fecharModal() {
  modalOverlay.classList.remove('show');
  document.body.style.overflow = '';
  currentPlayerId = null;
}

modalOverlay.addEventListener('click', function(e) {
  if (e.target === modalOverlay) fecharModal();
});

document.getElementById('btnCloseModal').addEventListener('click', fecharModal);

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') fecharModal();
});

document.addEventListener('click', function(e) {
  var card = e.target.closest('[data-player-id]');
  if (card && !e.target.closest('.btn-transferir') && !e.target.closest('#btnMostrarTransfer')) {
    abrirModal(parseInt(card.getAttribute('data-player-id'), 10));
  }
});

document.addEventListener('click', function(e) {
  var btn = e.target.closest('.btn-transferir');
  if (!btn) return;
  var equipaId   = parseInt(btn.getAttribute('data-equipa-id'), 10);
  var equipaNome = btn.getAttribute('data-equipa-nome');
  transferirJogador(currentPlayerId, equipaId, equipaNome, btn);
});

function transferirJogador(playerId, equipaId, equipaNome, btn) {
  btn.disabled = true;
  btn.textContent = '...';
  fetch('transferir_jogador.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'player_id=' + playerId + '&team_id=' + equipaId
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      transferMsg.textContent = 'Jogador transferido para "' + equipaNome + '" com sucesso!';
      transferSuccess.classList.add('show');
      btn.textContent = '✓';
      btn.style.background = '#16a34a';
      setTimeout(function() { fecharModal(); window.location.reload(); }, 1800);
    } else {
      btn.disabled = false;
      btn.textContent = 'Transferir';
      alert(data.message || 'Erro ao transferir.');
    }
  })
  .catch(function() {
    btn.disabled = false;
    btn.textContent = 'Transferir';
    alert('Erro de comunicação.');
  });
}

var searchInput = document.getElementById('searchPlayers');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    var termo = this.value.toLowerCase().trim();
    document.querySelectorAll('.player-item').forEach(function(el) {
      el.style.display = el.textContent.toLowerCase().includes(termo) ? '' : 'none';
    });
  });
}

var gridView = document.getElementById('gridView');
var listView = document.getElementById('listView');
var btnGrid  = document.getElementById('btnGrid');
var btnList  = document.getElementById('btnList');

if (btnGrid) {
  btnGrid.addEventListener('click', function() {
    gridView.style.display = 'grid';
    listView.style.display = 'none';
    btnGrid.classList.add('active');
    btnList.classList.remove('active');
  });
}
if (btnList) {
  btnList.addEventListener('click', function() {
    gridView.style.display = 'none';
    listView.style.display = 'grid';
    btnList.classList.add('active');
    btnGrid.classList.remove('active');
  });
}
</script>
</body>
</html>