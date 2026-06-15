<?php
session_start();
require('../../config/db.php');

if (isset($_GET['id'])) {
    $cleanId = preg_replace('/[^0-9]/', '', $_GET['id']);
    if (empty($cleanId) || !is_numeric($cleanId)) {
        header('Location: listar.php?error=id_invalido'); exit;
    }
    $checkStmt = $conn->prepare("SELECT id FROM players WHERE id = ?");
    $checkStmt->bind_param("i", $cleanId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows === 0) {
        header('Location: listar.php?error=jogador_nao_encontrado'); exit;
    }
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$positions = $conn->query("SELECT id, code, name FROM positions ORDER BY FIELD(code,'GK','DF','MF','FW'), name")->fetch_all(MYSQLI_ASSOC);
$seasons   = $conn->query("SELECT id, nome FROM seasons ORDER BY data_inicio DESC")->fetch_all(MYSQLI_ASSOC);
$club_id   = $_SESSION['club_id'] ?? 0;

$stmtTeams = $conn->prepare("SELECT id, nome FROM teams WHERE club_id = ? ORDER BY nome");
$stmtTeams->bind_param("i", $club_id);
$stmtTeams->execute();
$teams = $stmtTeams->get_result()->fetch_all(MYSQLI_ASSOC);

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$player = [
    'primeiro_nome'=>'','ultimo_nome'=>'','data_nascimento'=>'',
    'altura_cm'=>'','peso_kg'=>'','pe_dominante'=>'D','position_id'=>null,'foto'=>'','ativo'=>1,'team_id'=>null
];

if ($id === 0 && !empty($club_id)) {
    $stmtDT = $conn->prepare("SELECT id FROM teams WHERE club_id = ? ORDER BY id ASC LIMIT 1");
    $stmtDT->bind_param("i", $club_id);
    $stmtDT->execute();
    $rDT = $stmtDT->get_result()->fetch_assoc();
    if ($rDT) $player['team_id'] = $rDT['id'];
}

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM players WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $player = $res->fetch_assoc();
    } else {
        header('Location: listar.php?error=jogador_nao_encontrado'); exit;
    }
}

$seasonAtual = $conn->query("SELECT id,nome FROM seasons WHERE data_inicio<=CURDATE() AND data_fim>=CURDATE() ORDER BY data_inicio DESC LIMIT 1")->fetch_assoc()
    ?? $conn->query("SELECT id,nome FROM seasons ORDER BY data_inicio DESC LIMIT 1")->fetch_assoc();

$stats = ['jogos'=>0,'titular'=>0,'minutos'=>0,'golos'=>0,'assist'=>0,'amarelos'=>0,'vermelhos'=>0];
if ($id > 0 && isset($seasonAtual['id'])) {
    $sql = "SELECT COUNT(pms.id) jogos, SUM(pms.started) titular, COALESCE(SUM(pms.minutes_played),0) minutos,
                   SUM(pms.goals) golos, SUM(pms.assists) assist,
                   SUM(pms.yellow_cards) amarelos, SUM(pms.red_cards) vermelhos
            FROM player_match_stats pms
            JOIN matches m ON m.id=pms.match_id
            WHERE pms.player_id=? AND m.season_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $seasonAtual['id']);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r && $r->num_rows > 0) {
        $f = $r->fetch_assoc();
        if ($f) $stats = $f;
    }
}

$contractData = ['start_date'=>'','end_date'=>'','wage_monthly'=>'','clauses'=>'','season_id'=>''];
$hasContract  = false;
if ($id > 0 && isset($seasonAtual['id'])) {
    $hasClausesCol = $conn->query("SHOW COLUMNS FROM contracts LIKE 'clauses'")->num_rows;
    $cSql = $hasClausesCol
        ? "SELECT start_date, end_date, wage_monthly, clauses, season_id FROM contracts WHERE player_id=? AND season_id=? ORDER BY start_date DESC LIMIT 1"
        : "SELECT start_date, end_date, wage_monthly, season_id FROM contracts WHERE player_id=? AND season_id=? ORDER BY start_date DESC LIMIT 1";
    $stmt = $conn->prepare($cSql);
    $stmt->bind_param("ii", $id, $seasonAtual['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $contractData = array_merge($contractData, $res->fetch_assoc());
        $hasContract  = true;
    }
}

$mensalidadeData = ['mes_referencia'=>'','valor'=>'','data_vencimento'=>'','status'=>'pendente','metodo_pagamento'=>'','observacoes'=>'','season_id'=>''];
$hasMensalidade  = false;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT mes_referencia, valor, data_vencimento, status, metodo_pagamento, observacoes, season_id FROM mensalidades WHERE jogador_id=? ORDER BY mes_referencia DESC LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $mensalidadeData = $res->fetch_assoc();
        $hasMensalidade  = true;
    }
}

$photoURL    = "/logos/default-player.png";
$photoExists = false;
if (!empty($player['foto'])) {
    $file     = basename($player['foto']);
    $fileDisk = $_SERVER['DOCUMENT_ROOT'] . "/logos/" . $file;
    if (file_exists($fileDisk)) {
        $photoURL    = "/logos/" . $file;
        $photoExists = true;
    }
}

$positionNames = [
    'GK'=>'Guarda-Redes','LB'=>'Lateral Esq.','CB'=>'Defesa Central',
    'RB'=>'Lateral Dir.','CDM'=>'Médio Def.','CM'=>'Médio Centro',
    'LM'=>'Médio Esq.','RM'=>'Médio Dir.','CAM'=>'Médio Of.',
    'CF'=>'Avançado Centro','LW'=>'Extremo Esq.','ST'=>'Avançado','RW'=>'Extremo Dir.'
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? h($player['primeiro_nome']).' '.h($player['ultimo_nome']) : 'Novo Jogador' ?> – SportGes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f4f8;color:#1e293b;min-height:100vh}

    /* ── LAYOUT ── */
    .main-content{margin-left:240px;padding:2rem;width:calc(100% - 240px);min-height:100vh}
    @media(max-width:1023px){.main-content{margin-left:0;width:100%}}
    @media(max-width:599px){.main-content{padding:1rem}}
    .content-wrapper{max-width:1100px;margin:0 auto}

    /* ── TOAST ── */
    .toast-container{position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px}
    .toast-item{background:#fff;border-radius:12px;padding:.9rem 1.1rem;box-shadow:0 8px 24px rgba(0,0,0,.13);display:flex;align-items:center;gap:.75rem;min-width:300px;animation:toastIn .3s ease}
    @keyframes toastIn{from{transform:translateX(360px);opacity:0}to{transform:translateX(0);opacity:1}}
    .toast-item.success{border-left:4px solid #10b981}.toast-item.error{border-left:4px solid #ef4444}
    .toast-item.warning{border-left:4px solid #f59e0b}.toast-item.info{border-left:4px solid #3b82f6}
    .toast-item.success .ti{color:#10b981}.toast-item.error .ti{color:#ef4444}
    .toast-item.warning .ti{color:#f59e0b}.toast-item.info .ti{color:#3b82f6}
    .toast-title{font-weight:700;font-size:.875rem;color:#0f172a}
    .toast-msg{font-size:.8rem;color:#64748b}

    /* ── HERO HEADER ── */
    .player-hero{
      background:linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%);
      border-radius:20px;
      padding:2rem;
      margin-bottom:1.5rem;
      display:flex;
      align-items:center;
      gap:1.5rem;
      position:relative;
      overflow:hidden;
    }
    .player-hero::before{
      content:'';position:absolute;inset:0;
      background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .hero-photo{
      width:110px;height:110px;border-radius:50%;
      object-fit:cover;border:4px solid rgba(255,255,255,.3);
      flex-shrink:0;position:relative;z-index:1;
    }
    .hero-photo-placeholder{
      width:110px;height:110px;border-radius:50%;
      background:rgba(255,255,255,.12);border:4px solid rgba(255,255,255,.2);
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      color:rgba(255,255,255,.6);flex-shrink:0;position:relative;z-index:1;
    }
    .hero-photo-placeholder i{font-size:2.5rem}
    .hero-info{flex:1;position:relative;z-index:1}
    .hero-info h1{font-size:1.8rem;font-weight:800;color:#fff;margin:0 0 .5rem;letter-spacing:-.3px}
    .hero-meta{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
    .meta-badge{background:rgba(255,255,255,.15);color:#fff;padding:.3rem .75rem;border-radius:20px;font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:.35rem;backdrop-filter:blur(8px)}
    .hero-actions{display:flex;gap:.75rem;position:relative;z-index:1;flex-shrink:0}
    .btn-back{background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3);padding:.75rem 1.4rem;border-radius:10px;font-weight:600;font-size:.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;backdrop-filter:blur(8px)}
    .btn-back:hover{background:rgba(255,255,255,.25);color:#fff}
    .btn-del-hero{background:rgba(239,68,68,.2);color:#fca5a5;border:2px solid rgba(239,68,68,.3);padding:.75rem 1.4rem;border-radius:10px;font-weight:600;font-size:.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .2s}
    .btn-del-hero:hover{background:rgba(239,68,68,.35);color:#fff}

    /* ── CARD WRAPPER ── */
    .player-card{background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden}

    /* ── TABS ── */
    .player-tabs{display:flex;border-bottom:2px solid #f1f5f9;background:#fff}
    .tab-btn{flex:1;padding:1rem .5rem;border:none;background:transparent;color:#64748b;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .2s;position:relative;display:flex;align-items:center;justify-content:center;gap:.4rem}
    .tab-btn.active{color:#3b82f6}
    .tab-btn.active::after{content:'';position:absolute;bottom:-2px;left:10%;right:10%;height:3px;background:#3b82f6;border-radius:3px 3px 0 0}
    .tab-btn:hover:not(.active){color:#334155;background:#f8fafc}

    /* ── BODY ── */
    .player-body{padding:2rem}

    /* ── TAB PANELS ── */
    .tab-panel{display:none}.tab-panel.active{display:block}

    /* ── STATS GRID ── */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;margin-bottom:1.75rem}
    .stat-card{background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);border-radius:12px;padding:1.2rem 1rem;text-align:center;border:1.5px solid #e2e8f0;transition:transform .2s,box-shadow .2s}
    .stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.08)}
    .stat-card .label{font-size:.72rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.6rem}
    .stat-card .value{font-size:2rem;font-weight:800;color:#0f172a;line-height:1}
    .stat-card.highlight .value{color:#3b82f6}
    .season-tag{background:#eff6ff;color:#1e40af;border-radius:8px;padding:.5rem 1rem;font-size:.8rem;font-weight:600;margin-bottom:1.5rem;display:inline-flex;align-items:center;gap:.4rem}

    /* ── FORM SECTIONS ── */
    .section-title{font-size:1rem;font-weight:700;color:#0f172a;margin:0 0 1.25rem;display:flex;align-items:center;gap:.5rem}
    .section-title i{color:#3b82f6;font-size:1.2rem}
    .form-label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.5rem}
    .apple-input,.apple-select{width:100%;padding:.75rem 1rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.95rem;color:#1e293b;background:#fff;transition:all .2s;outline:none}
    .apple-input:focus,.apple-select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
    .apple-input::placeholder{color:#94a3b8}
    .apple-select{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L3 5h8z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 1rem center;padding-right:3rem}

    /* ── PITCH ── */
    .pitch-wrap{
      background:linear-gradient(to bottom,#2d8a3e 0%,#33a34d 50%,#2d8a3e 100%);
      border-radius:14px;padding:2rem 1.5rem;
      display:flex;flex-direction:row;justify-content:space-around;align-items:center;
      min-height:420px;position:relative;overflow:hidden;
    }
    .pitch-wrap::before{content:'';position:absolute;left:0;right:0;top:50%;transform:translateY(-50%);height:2px;background:rgba(255,255,255,.3);pointer-events:none}
    .pitch-wrap::after{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:100px;height:100px;border:2px solid rgba(255,255,255,.3);border-radius:50%;pointer-events:none}
    .pitch-col{display:flex;flex-direction:column;justify-content:center;align-items:center;gap:1.6rem;z-index:1;position:relative}
    .pos-slot input{display:none}
    .pos-badge{width:60px;height:60px;border-radius:50%;background:#ef4444;border:3px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .25s;box-shadow:0 4px 12px rgba(0,0,0,.35);font-weight:800;font-size:.8rem;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.3)}
    .pitch-col-gk .pos-badge{background:linear-gradient(135deg,#fbbf24,#f59e0b)}
    .pos-slot input:checked + .pos-badge{background:#3b82f6;transform:scale(1.18);box-shadow:0 0 0 4px rgba(59,130,246,.4),0 6px 18px rgba(59,130,246,.5)}
    .pitch-col-gk .pos-slot input:checked + .pos-badge{background:linear-gradient(135deg,#fbbf24,#f59e0b);box-shadow:0 0 0 5px rgba(251,191,36,.5),0 6px 18px rgba(251,191,36,.4)}
    .pos-badge:hover{transform:scale(1.12)}
    .pos-display{margin-top:1.25rem;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:1rem;text-align:center}
    .pos-display .lbl{font-size:.75rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem}
    .pos-display .val{font-size:1.1rem;font-weight:700;color:#0f172a}

    /* ── TOGGLE ── */
    .toggle-row{display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:1rem 1.25rem;margin-bottom:.75rem}
    .toggle-row.has-data{background:linear-gradient(135deg,#dcfce7,#d1fae5);border-color:#86efac}
    .toggle-label{font-size:.95rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:.6rem}
    .toggle-label i{color:#3b82f6;font-size:1.25rem}
    .filled-badge{background:#10b981;color:#fff;font-size:.7rem;font-weight:700;padding:.25rem .6rem;border-radius:6px;margin-left:.4rem}
    .toggle-switch{position:relative;width:52px;height:28px;flex-shrink:0}
    .toggle-switch input{display:none}
    .slider{position:absolute;inset:0;background:#cbd5e1;border-radius:28px;transition:.3s;cursor:pointer}
    .slider::before{content:'';position:absolute;width:22px;height:22px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 2px 4px rgba(0,0,0,.2)}
    input:checked + .slider{background:#3b82f6}
    input:checked + .slider::before{transform:translateX(24px)}
    .collapsible{max-height:0;overflow:hidden;opacity:0;transition:all .35s ease}
    .collapsible.open{max-height:700px;opacity:1;margin-top:.75rem}
    .info-box{border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;gap:.75rem;align-items:flex-start}
    .info-box.blue{background:#eff6ff;border-left:4px solid #3b82f6;color:#1e40af}
    .info-box.amber{background:#fffbeb;border-left:4px solid #f59e0b;color:#92400e}
    .info-box i{font-size:1.2rem;flex-shrink:0;margin-top:.1rem}
    .info-box strong{display:block;font-weight:700;margin-bottom:.2rem}
    .info-box p{font-size:.875rem;margin:0;line-height:1.5}

    /* ── PHOTO UPLOAD ── */
    .photo-upload-wrap{text-align:center;padding:1.5rem 0}
    .photo-upload-preview{width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid #e2e8f0;margin:0 auto 1rem;display:block}
    .photo-placeholder-wrap{width:120px;height:120px;border-radius:50%;background:#f1f5f9;border:4px solid #e2e8f0;margin:0 auto 1rem;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8}
    .photo-placeholder-wrap i{font-size:2.5rem;margin-bottom:.3rem}
    .photo-placeholder-wrap span{font-size:.75rem}
    .photo-upload-btn{display:inline-flex;align-items:center;gap:.4rem;background:#3b82f6;color:#fff;padding:.7rem 1.5rem;border-radius:10px;font-weight:600;font-size:.875rem;cursor:pointer;border:none;transition:all .2s}
    .photo-upload-btn:hover{background:#2563eb}
    .photo-upload-btn input{display:none}

    /* ── FOOTER / SAVE BAR ── */
    .save-bar{background:#fff;border-top:2px solid #f1f5f9;padding:1.25rem 2rem;display:flex;justify-content:flex-end;gap:.75rem;border-radius:0 0 20px 20px;position:sticky;bottom:0;z-index:10}
    .btn-cancel{background:#f1f5f9;color:#475569;border:none;padding:.8rem 1.5rem;border-radius:10px;font-weight:600;font-size:.9rem;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem}
    .btn-cancel:hover{background:#e2e8f0;color:#334155}
    .btn-save{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;padding:.8rem 2rem;border-radius:10px;font-weight:700;font-size:.9rem;cursor:pointer;display:inline-flex;align-items:center;gap:.45rem;transition:all .2s;box-shadow:0 4px 12px rgba(59,130,246,.25)}
    .btn-save:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 6px 18px rgba(59,130,246,.35)}
    .btn-save:disabled{opacity:.6;cursor:not-allowed}

    /* RESPONSIVE */
    @media(max-width:768px){
      .player-hero{flex-direction:column;text-align:center;padding:1.5rem 1rem}
      .hero-meta{justify-content:center}
      .hero-actions{width:100%;justify-content:center}
      .player-tabs .tab-btn span{display:none}
      .player-tabs .tab-btn{padding:.75rem .5rem}
    }
  </style>
</head>
<body>

<?php require('../../includes/sidebar.php'); ?>

<div class="toast-container" id="toastContainer"></div>

<div class="main-content">
  <div class="content-wrapper">

    <!-- HERO -->
    <div class="player-hero">
      <?php if ($photoExists): ?>
        <img id="heroPhoto" class="hero-photo" src="<?= h($photoURL) ?>" alt="Foto do jogador">
      <?php else: ?>
        <div class="hero-photo-placeholder" id="heroPhoto">
          <i class="bi bi-person-circle"></i>
        </div>
      <?php endif; ?>

      <div class="hero-info">
        <h1>
          <?= $id ? h($player['primeiro_nome']).' '.h($player['ultimo_nome']) : 'Novo Jogador' ?>
        </h1>
        <div class="hero-meta">
          <?php if (!empty($player['position_id'])): ?>
            <span class="meta-badge"><i class="bi bi-geo-alt-fill"></i><?= h($player['position_id']) ?> – <?= h($positionNames[$player['position_id']] ?? '') ?></span>
          <?php endif; ?>
          <?php if (!empty($player['data_nascimento'])): ?>
            <span class="meta-badge"><i class="bi bi-calendar3"></i><?= date('d/m/Y', strtotime($player['data_nascimento'])) ?></span>
          <?php endif; ?>
          <?php if (!empty($player['ativo'])): ?>
            <span class="meta-badge" style="background:rgba(16,185,129,.25)"><i class="bi bi-circle-fill" style="font-size:.55rem;color:#6ee7b7"></i>Ativo</span>
          <?php else: ?>
            <span class="meta-badge" style="background:rgba(239,68,68,.2)"><i class="bi bi-circle-fill" style="font-size:.55rem;color:#fca5a5"></i>Inativo</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="hero-actions">
        <?php if ($id): ?>
          
        <?php endif; ?>
        <a href="listar.php" class="btn-back">
          <i class="bi bi-arrow-left"></i> Voltar
        </a>
      </div>
    </div>

    <!-- CARD COM TABS -->
    <div class="player-card">

      <div class="player-tabs">
        
        <button class="tab-btn" onclick="switchTab('info',this)"><i class="bi bi-person-fill"></i> <span>Dados Pessoais</span></button>
        <button class="tab-btn" onclick="switchTab('position',this)"><i class="bi bi-bullseye"></i> <span>Posição</span></button>
        <button class="tab-btn active" onclick="switchTab('stats',this)"><i class="bi bi-bar-chart-fill"></i> <span>Estatísticas</span></button>
        <button class="tab-btn" onclick="switchTab('contrato',this)"><i class="bi bi-file-earmark-text-fill"></i> <span>Contrato</span></button>
      </div>

      <div class="player-body">
        <form id="mainPlayerForm" method="POST" enctype="multipart/form-data" action="processar.php">
          <input type="hidden" name="action" value="save_player">
          <input type="hidden" name="id" value="<?= (int)$id ?>">
          <input type="hidden" name="position_id" id="position_id_input" value="<?= h($player['position_id']) ?>">

          <!-- ═══ TAB: ESTATÍSTICAS ═══ -->
          <div class="tab-panel active" id="panel-stats">
            <?php if ($id > 0): ?>
              <?php if (isset($seasonAtual['nome'])): ?>
                <div class="season-tag"><i class="bi bi-flag-fill"></i><?= h($seasonAtual['nome']) ?></div>
              <?php endif; ?>
              <div class="stats-grid">
                <div class="stat-card">
                  <div class="label">Jogos</div>
                  <div class="value"><?= (int)$stats['jogos'] ?></div>
                </div>
                <div class="stat-card">
                  <div class="label">Titular</div>
                  <div class="value"><?= (int)$stats['titular'] ?></div>
                </div>
                <div class="stat-card">
                  <div class="label">Minutos</div>
                  <div class="value"><?= (int)$stats['minutos'] ?></div>
                </div>
                <div class="stat-card highlight">
                  <div class="label">Golos</div>
                  <div class="value"><?= (int)$stats['golos'] ?></div>
                </div>
                <div class="stat-card highlight">
                  <div class="label">Assistências</div>
                  <div class="value"><?= (int)$stats['assist'] ?></div>
                </div>
                <div class="stat-card" style="border-color:#fde68a">
                  <div class="label">Amarelos</div>
                  <div class="value" style="color:#d97706"><?= (int)$stats['amarelos'] ?></div>
                </div>
                <div class="stat-card" style="border-color:#fecaca">
                  <div class="label">Vermelhos</div>
                  <div class="value" style="color:#dc2626"><?= (int)$stats['vermelhos'] ?></div>
                </div>
              </div>
              <div class="section-title" style="margin-top:1.5rem"><i class="bi bi-image"></i>Foto do Jogador</div>
              <div class="photo-upload-wrap">
                <?php if ($photoExists): ?>
                  <img id="photoPreview" class="photo-upload-preview" src="<?= h($photoURL) ?>" alt="Foto">
                <?php else: ?>
                  <div class="photo-placeholder-wrap" id="photoPlaceholder">
                    <i class="bi bi-person-circle"></i>
                    <span>Sem foto</span>
                  </div>
                  <img id="photoPreview" class="photo-upload-preview" style="display:none" alt="Foto">
                <?php endif; ?>
                <label class="photo-upload-btn">
                  <i class="bi bi-cloud-upload"></i> <?= $id ? 'Alterar Foto' : 'Carregar Foto' ?>
                  <input type="file" name="photo" id="photoInput" accept="image/*" onchange="previewPhoto(this)">
                </label>
                <p style="margin-top:.5rem;font-size:.8rem;color:#64748b">JPG, PNG, GIF • Máx. 5 MB</p>
              </div>
            <?php else: ?>
              <div style="text-align:center;padding:3rem 1rem;color:#64748b">
                <i class="bi bi-bar-chart" style="font-size:3rem;margin-bottom:1rem;display:block"></i>
                <p style="font-size:1rem;font-weight:600">Estatísticas disponíveis após o jogador ser criado.</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- ═══ TAB: DADOS PESSOAIS ═══ -->
          <div class="tab-panel" id="panel-info">
            <div class="section-title"><i class="bi bi-person-circle"></i>Informações Gerais</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Primeiro Nome *</label>
                <input name="primeiro_nome" class="apple-input" required value="<?= h($player['primeiro_nome']) ?>" placeholder="Ex: Cristiano">
              </div>
              <div class="col-md-6">
                <label class="form-label">Último Nome *</label>
                <input name="ultimo_nome" class="apple-input" required value="<?= h($player['ultimo_nome']) ?>" placeholder="Ex: Ronaldo">
              </div>
              <div class="col-md-4">
                <label class="form-label">Data de Nascimento *</label>
                <input type="date" name="data_nascimento" class="apple-input" required value="<?= h($player['data_nascimento']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Altura (cm)</label>
                <input type="number" name="altura_cm" class="apple-input" value="<?= h($player['altura_cm']) ?>" placeholder="185">
              </div>
              <div class="col-md-4">
                <label class="form-label">Peso (kg)</label>
                <input type="number" name="peso_kg" class="apple-input" value="<?= h($player['peso_kg']) ?>" placeholder="80">
              </div>
              <div class="col-md-6">
                <label class="form-label">Pé Dominante</label>
                <select name="pe_dominante" class="apple-select">
                  <?php foreach(['D'=>'Direito','E'=>'Esquerdo','Ambos'=>'Ambos'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $player['pe_dominante']===$k?'selected':'' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="ativo" class="apple-select">
                  <option value="1" <?= $player['ativo']?'selected':'' ?>>✅ Ativo</option>
                  <option value="0" <?= !$player['ativo']?'selected':'' ?>>⛔ Inativo</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label"><i class="bi bi-shield-fill"></i> Equipa</label>
                <select name="team_id" id="team_id_select" class="apple-select">
                  <option value="">-- Sem Equipa --</option>
                  <?php foreach($teams as $team): ?>
                    <option value="<?= (int)$team['id'] ?>"
                      <?= (($id===0 && $team['id']==$player['team_id']) || ($id>0 && $player['team_id']==$team['id'])) ? 'selected' : '' ?>>
                      <?= h($team['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <?php if (!$id): ?>
              <div class="section-title"><i class="bi bi-image"></i>Foto</div>
              <div class="photo-upload-wrap">
                <div class="photo-placeholder-wrap" id="photoPlaceholder">
                  <i class="bi bi-person-circle"></i><span>Sem foto</span>
                </div>
                <img id="photoPreview" class="photo-upload-preview" style="display:none" alt="Foto">
                <label class="photo-upload-btn">
                  <i class="bi bi-cloud-upload"></i> Carregar Foto
                  <input type="file" name="photo" id="photoInput2" accept="image/*" onchange="previewPhoto(this)">
                </label>
              </div>
            <?php endif; ?>
          </div>

          <!-- ═══ TAB: POSIÇÃO ═══ -->
          <div class="tab-panel" id="panel-position">
            <div class="section-title"><i class="bi bi-bullseye"></i>Posição em Campo</div>
            <p style="color:#64748b;font-size:.875rem;margin-bottom:1.5rem">Clique no círculo da posição do jogador.</p>
            <div class="pitch-wrap">
              <div class="pitch-col pitch-col-gk">
                <div class="pos-slot">
                  <input type="radio" name="position_radio" id="p_gk" value="GK" <?= $player['position_id']==='GK'?'checked':'' ?> onchange="updatePos('GK','Guarda-Redes')">
                  <label for="p_gk" class="pos-badge">GK</label>
                </div>
              </div>
              <div class="pitch-col">
                <?php foreach(['LB'=>'Lateral Esq.','CB'=>'Defesa Central','RB'=>'Lateral Dir.'] as $c=>$n): ?>
                  <div class="pos-slot">
                    <input type="radio" name="position_radio" id="p_<?= strtolower($c) ?>" value="<?= $c ?>" <?= $player['position_id']===$c?'checked':'' ?> onchange="updatePos('<?= $c ?>','<?= $n ?>')">
                    <label for="p_<?= strtolower($c) ?>" class="pos-badge"><?= $c ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="pitch-col">
                <div class="pos-slot">
                  <input type="radio" name="position_radio" id="p_cdm" value="CDM" <?= $player['position_id']==='CDM'?'checked':'' ?> onchange="updatePos('CDM','Médio Defensivo')">
                  <label for="p_cdm" class="pos-badge">CDM</label>
                </div>
              </div>
              <div class="pitch-col">
                <?php foreach(['LM'=>'Médio Esq.','CM'=>'Médio Centro','RM'=>'Médio Dir.'] as $c=>$n): ?>
                  <div class="pos-slot">
                    <input type="radio" name="position_radio" id="p_<?= strtolower($c) ?>" value="<?= $c ?>" <?= $player['position_id']===$c?'checked':'' ?> onchange="updatePos('<?= $c ?>','<?= $n ?>')">
                    <label for="p_<?= strtolower($c) ?>" class="pos-badge"><?= $c ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="pitch-col">
                <?php foreach(['LW'=>'Extremo Esq.','CAM'=>'Médio Of.','CF'=>'Av. Centro','ST'=>'Avançado','RW'=>'Extremo Dir.'] as $c=>$n): ?>
                  <div class="pos-slot">
                    <input type="radio" name="position_radio" id="p_<?= strtolower($c) ?>" value="<?= $c ?>" <?= $player['position_id']===$c?'checked':'' ?> onchange="updatePos('<?= $c ?>','<?= $n ?>')">
                    <label for="p_<?= strtolower($c) ?>" class="pos-badge"><?= $c ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="pos-display">
              <div class="lbl">Posição selecionada</div>
              <div class="val" id="posDisplay">
                <?php
                  $pid = $player['position_id'];
                  echo $pid ? $pid.' – '.($positionNames[$pid] ?? $pid) : 'Nenhuma selecionada';
                ?>
              </div>
            </div>
          </div>

          <!-- ═══ TAB: CONTRATO / MENSALIDADES ═══ -->
          <div class="tab-panel" id="panel-contrato">
            <div class="toggle-row <?= $hasContract?'has-data':'' ?>" style="margin-bottom:.75rem">
              <div class="toggle-label">
                <i class="bi bi-file-earmark-text-fill"></i>
                Contrato
                <?php if($hasContract): ?><span class="filled-badge">✓ Preenchido</span><?php endif; ?>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" id="contractToggle" <?= $hasContract?'checked':'' ?> onchange="toggleSection('contractArea',this)">
                <span class="slider"></span>
              </label>
            </div>
            <div class="collapsible <?= $hasContract?'open':'' ?>" id="contractArea">
              <div class="info-box blue">
                <i class="bi bi-info-circle-fill"></i>
                <div><strong>Contrato</strong><p>Preencha os dados do contrato para a época atual.</p></div>
              </div>
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label"><i class="bi bi-currency-euro"></i> Salário Mensal (€)</label>
                  <input type="number" step="0.01" name="wage_monthly" class="apple-input" value="<?= h($contractData['wage_monthly']) ?>" placeholder="Ex: 5000">
                </div>
                <div class="col-md-6">
                  <label class="form-label"><i class="bi bi-flag"></i> Época</label>
                  <select name="contract_season_id" class="apple-select">
                    <option value="">Selecione a época</option>
                    <?php foreach($seasons as $s): ?>
                      <option value="<?= (int)$s['id'] ?>"
                        <?= (isset($contractData['season_id']) && $contractData['season_id']==$s['id']) || (isset($seasonAtual['id']) && $seasonAtual['id']==$s['id']) ? 'selected' : '' ?>>
                        <?= h($s['nome']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label"><i class="bi bi-file-text"></i> Cláusulas / Observações</label>
                  <textarea name="contract_clauses" rows="3" class="apple-input" style="resize:vertical" placeholder="Cláusulas especiais, bónus..."><?= h($contractData['clauses']) ?></textarea>
                </div>
              </div>
            </div>

            <div class="toggle-row <?= $hasMensalidade?'has-data':'' ?>">
              <div class="toggle-label">
                <i class="bi bi-cash-coin"></i>
                Mensalidades
                <?php if($hasMensalidade): ?><span class="filled-badge">✓ Preenchido</span><?php endif; ?>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" id="mensalidadeToggle" <?= $hasMensalidade?'checked':'' ?> onchange="toggleSection('mensalidadeArea',this)">
                <span class="slider"></span>
              </label>
            </div>
            <div class="collapsible <?= $hasMensalidade?'open':'' ?>" id="mensalidadeArea">
              <div class="info-box amber" style="margin-top:.75rem">
                <i class="bi bi-wallet2"></i>
                <div><strong>Mensalidades</strong><p>Valor mensal que o jogador paga ao clube.</p></div>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label"><i class="bi bi-flag"></i> Época</label>
                  <select name="mensalidade_epoca" class="apple-select">
                    <?php foreach($seasons as $s): ?>
                      <option value="<?= $s['id'] ?>" <?= (isset($mensalidadeData['season_id']) && $mensalidadeData['season_id']==$s['id'])?'selected':'' ?>><?= h($s['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label"><i class="bi bi-currency-dollar"></i> Valor Mensal (€)</label>
                  <input type="number" step="0.01" name="mensalidade_valor" class="apple-input" value="<?= h($mensalidadeData['valor']) ?>" placeholder="50.00">
                </div>
                <div class="col-12">
                  <label class="form-label"><i class="bi bi-credit-card"></i> Método de Pagamento</label>
                  <input type="text" name="mensalidade_metodo_pagamento" class="apple-input" value="<?= h($mensalidadeData['metodo_pagamento']) ?>" placeholder="Transferência, MB Way, Dinheiro…">
                </div>
                <div class="col-12">
                  <label class="form-label"><i class="bi bi-file-text"></i> Observações</label>
                  <textarea name="mensalidade_observacoes" rows="3" class="apple-input" style="resize:vertical" placeholder="Descontos, isenções…"><?= h($mensalidadeData['observacoes']) ?></textarea>
                </div>
              </div>
            </div>
          </div>

        </form>
      </div>

      <!-- SAVE BAR -->
      <div class="save-bar">
        <a href="listar.php" class="btn-cancel"><i class="bi bi-x-lg"></i> Cancelar</a>
        <button type="button" class="btn-save" id="mainSaveBtn" onclick="submitForm()">
          <i class="bi bi-check-circle-fill"></i>
          <?= $id ? 'Atualizar Jogador' : 'Criar Jogador' ?>
        </button>
      </div>

    </div><!-- /player-card -->

  </div><!-- /content-wrapper -->
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('panel-' + name).classList.add('active');
}

function updatePos(code, name) {
  document.getElementById('position_id_input').value = code;
  document.getElementById('posDisplay').textContent = code + ' – ' + name;
}

function toggleSection(areaId, checkbox) {
  const area = document.getElementById(areaId);
  if (!area) return;
  checkbox.checked ? area.classList.add('open') : area.classList.remove('open');
}

function previewPhoto(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const preview     = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    const hero        = document.getElementById('heroPhoto');
    if (preview)     { preview.src = e.target.result; preview.style.display = 'block'; }
    if (placeholder)   placeholder.style.display = 'none';
    if (hero && hero.tagName === 'IMG') hero.src = e.target.result;
    else if (hero) {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.className = 'hero-photo';
      img.id = 'heroPhoto';
      hero.replaceWith(img);
    }
  };
  reader.readAsDataURL(input.files[0]);
}

function showToast(type, title, msg) {
  const icons = { success:'bi-check-circle-fill', error:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
  const el = document.createElement('div');
  el.className = 'toast-item ' + type;
  el.innerHTML = `<i class="bi ${icons[type]||icons.info}" style="font-size:1.4rem;flex-shrink:0"></i>
    <div><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;margin-left:auto"><i class="bi bi-x-lg"></i></button>`;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => el.remove(), 5000);
}



function submitForm() {
  const posInput = document.getElementById('position_id_input');
  if (!posInput.value) {
    showToast('warning','Atenção!','Por favor selecione uma posição para o jogador');
    return;
  }

  const form = document.getElementById('mainPlayerForm');
  const fd   = new FormData(form);

  const photoInput = document.getElementById('photoInput');
  if (photoInput && photoInput.files && photoInput.files[0]) {
    fd.delete('photo');
    fd.append('photo', photoInput.files[0]);
  }

  fd.set('has_contract',    document.getElementById('contractToggle')?.checked    ? '1' : '0');
  fd.set('has_mensalidade', document.getElementById('mensalidadeToggle')?.checked ? '1' : '0');
  if (!fd.has('action')) fd.append('action','save_player');

  const btn  = document.getElementById('mainSaveBtn');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> A guardar…';

  fetch('processar.php', { method:'POST', body:fd })
    .then(r => { if (!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
    .then(text => {
      let data;
      try { data = JSON.parse(text); } catch(e) { throw new Error('Resposta inválida'); }
      if (data.success) {
        showToast('success','Guardado!', data.message || 'Jogador guardado com sucesso');
        setTimeout(() => window.location.href = 'listar.php', 1400);
      } else {
        btn.disabled = false; btn.innerHTML = orig;
        showToast('error','Erro', data.message || 'Erro ao guardar');
      }
    })
    .catch(err => {
      btn.disabled = false; btn.innerHTML = orig;
      showToast('error','Erro', err.message || 'Falha na ligação');
    });
}

document.addEventListener('DOMContentLoaded', () => {
  const checked = document.querySelector('input[name="position_radio"]:checked');
  if (checked) {
    const names = {GK:'Guarda-Redes',LB:'Lateral Esq.',CB:'Defesa Central',RB:'Lateral Dir.',CDM:'Médio Def.',CM:'Médio Centro',LM:'Médio Esq.',RM:'Médio Dir.',CAM:'Médio Of.',CF:'Av. Centro',ST:'Avançado',LW:'Extremo Esq.',RW:'Extremo Dir.'};
    updatePos(checked.value, names[checked.value] || '');
  }
});
</script>
</body>
</html>