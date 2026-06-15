<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require('../../config/db.php');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Processar remoção de jogo
if (isset($_POST['remover_jogo']) && isset($_POST['jogo_id'])) {
    $jogo_id = intval($_POST['jogo_id']);
    $user_role = $_SESSION['user_role'] ?? '';
    $isAdmin = ($user_role == 1);
    
    if ($isAdmin) {
        $deleteStmt = $conn->prepare("DELETE FROM matches WHERE id = ?");
        $deleteStmt->bind_param("i", $jogo_id);
        
        if ($deleteStmt->execute()) {
            $_SESSION['sucesso'] = "Jogo removido com sucesso!";
        } else {
            $_SESSION['erro'] = "Erro ao remover o jogo.";
        }
        $deleteStmt->close();
    } else {
        $_SESSION['erro'] = "Apenas administradores podem remover jogos.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Obter o club_id, user_id e team_id da sessão
$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id      = $_SESSION['user_id'];
$user_team_id = isset($_SESSION['team_id']) ? intval($_SESSION['team_id']) : 0;

// Verificar se o utilizador tem clube associado (exceto se for o admin principal - ID 7)
if ($user_club_id <= 0 && $user_id != 7) {
    die("Erro: Utilizador sem clube associado. Contacte o administrador.");
}

// Se for o admin principal (ID 7 sem clube), mostrar TODOS os jogos
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

// Verificar role do utilizador
$user_role   = $_SESSION['user_role'] ?? '';
$isAdmin     = ($user_role == 1);
$isDiretor   = ($user_role == 2);
$isTreinador = in_array($user_role, ['3', 'Treinador']);
$isJogador   = ($user_role == 4);

// Jogadores, Diretores, Treinadores e Admins podem ver os jogos
if (!in_array($user_role, ['1', '2', '3', '4', 'Treinador'])) {
    $_SESSION['erro'] = "Acesso negado!";
    header("Location: ../../dashboard.php");
    exit;
}

// Buscar temporadas disponíveis
$temporadasQuery = $conn->query("
  SELECT id, nome, data_inicio, data_fim 
  FROM seasons 
  ORDER BY data_inicio DESC
");
$temporadas = [];
if ($temporadasQuery) {
  while ($row = $temporadasQuery->fetch_assoc()) {
    $temporadas[] = ['id' => $row['id'], 'nome' => $row['nome']];
  }
}
if (count($temporadas) == 0) {
  $temporadas[] = ['id' => 1, 'nome' => '24/25'];
}

// Filtro de temporada
$temporadaSelecionadaId = isset($_GET['temporada']) ? (int)$_GET['temporada'] : $temporadas[0]['id'];

$temporadaSelecionadaNome = '';
foreach($temporadas as $temp) {
  if ($temp['id'] == $temporadaSelecionadaId) {
    $temporadaSelecionadaNome = $temp['nome'];
    break;
  }
}

$porPagina = isset($_GET['entries']) ? max(10, min(50, (int)$_GET['entries'])) : 10;
$pagina    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset    = ($pagina - 1) * $porPagina;

// ================================================================
// LÓGICA DE FILTRO POR ROLE
// SuperAdmin  → todos os jogos
// Admin/Dir   → jogos do clube
// Treinador e Jogador → só jogos da sua equipa (team_id da sessão)
// ================================================================
if ($isAdminPrincipal) {
    $sqlCount = "SELECT COUNT(*) as total FROM matches m WHERE m.season_id = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("i", $temporadaSelecionadaId);

} elseif ($isTreinador || $isJogador) {
    $sqlCount = "
        SELECT COUNT(*) as total 
        FROM matches m 
        WHERE m.season_id = ? AND m.team_id = ?
    ";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("ii", $temporadaSelecionadaId, $user_team_id);

} else {
    $sqlCount = "
        SELECT COUNT(*) as total 
        FROM matches m 
        JOIN teams t ON t.id = m.team_id
        WHERE m.season_id = ? AND t.club_id = ?
    ";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("ii", $temporadaSelecionadaId, $user_club_id);
}

$stmtCount->execute();
$totalJogos  = $stmtCount->get_result()->fetch_assoc()['total'];
$totalPaginas = ceil($totalJogos / $porPagina);
$stmtCount->close();

// Buscar jogos
$sqlBase = "
  SELECT m.id, m.data_jogo, m.adversario, 
         CONCAT(c.nome) AS equipa, 
         t.escaloes,
         m.local, m.estado,
         m.golos_marcados, m.golos_sofridos,
         m.resultado_final,
         c.nome as clube_nome,
         t.nome as team_nome
  FROM matches m
  JOIN teams t ON t.id = m.team_id
  JOIN clubs c ON c.id = t.club_id
  WHERE m.season_id = ?
";

if ($isAdminPrincipal) {
    $sql = $sqlBase . " ORDER BY m.data_jogo DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $temporadaSelecionadaId, $porPagina, $offset);

} elseif ($isTreinador || $isJogador) {
    $sql = $sqlBase . " AND m.team_id = ? ORDER BY m.data_jogo DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $temporadaSelecionadaId, $user_team_id, $porPagina, $offset);

} else {
    $sql = $sqlBase . " AND t.club_id = ? ORDER BY m.data_jogo DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $temporadaSelecionadaId, $user_club_id, $porPagina, $offset);
}

$stmt->execute();
$res = $stmt->get_result();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function getResultado($marcados, $sofridos) {
  if ($marcados > $sofridos) return 'Win';
  if ($marcados == $sofridos) return 'Draw';
  return 'Defeat';
}

function formatarDataPorExtenso($data) {
  if (empty($data)) return '--';
  $timestamp = strtotime($data);
  if ($timestamp === false) return '--';
  $meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
            'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  $dia  = date('d', $timestamp);
  $mes  = $meses[date('n', $timestamp) - 1];
  $ano  = date('Y', $timestamp);
  $hora = date('H:i', $timestamp);
  return "$dia de $mes de $ano às $hora";
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jogos - SportGes</title>
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
      padding: 2rem;
      padding-bottom: 100px;
      width: calc(100% - 240px);
      min-height: 100vh;
      transition: margin-left 0.3s ease, width 0.3s ease;
    }

    .content-wrapper { max-width: 1600px; margin: 0 auto; }
    .page-header, .header-actions, .content-wrapper { position: relative; z-index: 10; }

    .page-header {
      background: white;
      padding: 2rem;
      border-radius: 16px;
      margin-bottom: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
    }

    .page-header h1 {
      font-size: 2rem;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .page-header h1 i { font-size: 2.5rem; color: #3b82f6; }

    .header-actions { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }

    .season-filter {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: white;
      padding: 0.5rem 1rem;
      border-radius: 10px;
      border: 2px solid #e2e8f0;
    }

    .season-filter label { font-size: 0.875rem; font-weight: 600; color: #64748b; white-space: nowrap; }

    .season-select {
      padding: 0.5rem 2.5rem 0.5rem 0.875rem;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.9375rem;
      color: #334155;
      font-weight: 600;
      cursor: pointer;
      background: white;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L2 4h8z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      transition: all 0.2s;
    }

    .search-wrapper { position: relative; min-width: 300px; }
    .search-wrapper i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem; }

    .search-input {
      width: 100%;
      padding: 0.875rem 1rem 0.875rem 3rem;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 0.9375rem;
      transition: all 0.2s;
      background: #f8fafc;
    }

    .btn-add {
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
      transition: all 0.3s;
      text-decoration: none;
      white-space: nowrap;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .team-locked-badge {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: #f1f5f9;
      border: 2px solid #e2e8f0;
      padding: 0.5rem 1rem;
      border-radius: 10px;
      font-size: 0.875rem;
      font-weight: 600;
      color: #475569;
    }

    .team-locked-badge i { color: #94a3b8; }

    .pagination-section {
      background: white;
      border-radius: 16px;
      padding: 1.5rem 2rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .pagination-footer {
      position: fixed;
      bottom: 0;
      left: 240px;
      right: 0;
      margin: 0;
      border-radius: 0;
      border-top: 1px solid #e2e8f0;
      z-index: 100;
    }

    @media (max-width: 1023px) { .pagination-footer { left: 0; } }

    .entries-select {
      padding: 0.5rem 2.5rem 0.5rem 0.875rem;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.9375rem;
      color: #334155;
      font-weight: 600;
      cursor: pointer;
      background: white;
      transition: all 0.2s;
    }

    .pagination-btn {
      min-width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      background: white;
      color: #64748b;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
    }

    .matches-grid { display: grid; gap: 1rem; margin-bottom: 2rem; }

    .match-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      transition: all 0.2s;
      border-left: 4px solid #cbd5e1;
      display: grid;
      grid-template-columns: auto 1fr auto auto;
      gap: 2rem;
      align-items: center;
    }

    .match-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-2px); }
    .match-card.estado-agendado   { border-left-color: #3b82f6; border-left-width: 5px; }
    .match-card.estado-decorrido  { border-left-color: #22c55e; border-left-width: 5px; }
    .match-card.estado-concluído,
    .match-card.estado-concluido  { border-left-color: #22c55e; border-left-width: 5px; }
    .match-card.estado-adiado     { border-left-color: #dc2626; border-left-width: 5px; }
    .match-card.estado-cancelado  { border-left-color: #ef4444; border-left-width: 5px; }

    .match-date { display: flex; flex-direction: column; align-items: center; min-width: 80px; }
    .match-date .day   { font-size: 1.75rem; font-weight: 700; color: #0f172a; line-height: 1; }
    .match-date .month { font-size: 0.8125rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.25rem; }

    .match-info { display: flex; flex-direction: column; gap: 0.5rem; flex: 1; }
    .match-teams { display: flex; align-items: center; gap: 1rem; font-weight: 600; }
    .team-name { color: #0f172a; font-size: 1.125rem; }
    .adversario-name { color: #64748b; font-size: 1.125rem; }
    .match-details { display: flex; align-items: center; gap: 1.5rem; font-size: 0.8125rem; color: #94a3b8; }

    .match-resultado { display: flex; flex-direction: column; align-items: center; gap: 0.375rem; min-width: 100px; }
    .resultado-score { font-size: 1.75rem; font-weight: 700; letter-spacing: 1px; }
    .resultado-label { font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 1px; padding: 0.25rem 0.625rem; border-radius: 4px; font-weight: 700; }

    .match-card.resultado-win   .resultado-score { color: #16a34a; }
    .match-card.resultado-win   .resultado-label { background: #dcfce7; color: #166534; }
    .match-card.resultado-draw  .resultado-score { color: #ca8a04; }
    .match-card.resultado-draw  .resultado-label { background: #fef3c7; color: #92400e; }
    .match-card.resultado-defeat .resultado-score { color: #dc2626; }
    .match-card.resultado-defeat .resultado-label { background: #fee2e2; color: #991b1b; }

    .match-actions { display: flex; align-items: center; gap: 10px; }

    .btn-edit {
      background: #2563eb; color: #fff;
      padding: 10px 18px; border-radius: 10px;
      font-weight: 600; border: none;
      display: flex; align-items: center; gap: 6px;
      box-shadow: 0 4px 12px rgba(37,99,235,0.25);
      transition: 0.2s; text-decoration: none;
    }
    .btn-edit:hover { background: #1d4ed8; color: #fff; transform: translateY(-2px); }

    .btn-delete {
      padding: 0.5rem 0.75rem;
      border: 1px solid #e5e7eb;
      border-radius: 8px; background: white;
      color: #64748b; cursor: pointer;
      transition: all 0.2s;
      display: flex; align-items: center; justify-content: center;
      min-width: 38px; height: 38px;
      text-decoration: none; font-size: 1rem;
    }
    .btn-delete:hover { background: #fef2f2; border-color: #ef4444; color: #ef4444; }

    .btn-ver {
      background: #10b981;
      color: #fff;
      padding: 10px 18px;
      border-radius: 10px;
      font-weight: 600;
      border: none;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 4px 12px rgba(16,185,129,0.25);
      transition: 0.2s;
      cursor: pointer;
    }
    .btn-ver:hover { background: #059669; transform: translateY(-2px); }

    .alert {
      padding: 1rem 1.5rem; border-radius: 12px;
      margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 0.75rem;
      font-weight: 500;
    }
    .alert.alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
    .alert.alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }

    .empty-state { text-align: center; padding: 4rem 2rem; background: white; border-radius: 16px; }
    .empty-state i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem; }

    @media (max-width: 1023px) {
      .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-bottom: 100px; }
      .match-card { grid-template-columns: auto 1fr; gap: 1rem; }
      .match-resultado, .match-actions { grid-column: 1 / 3; }
    }

    @media (max-width: 768px) {
      .main-content { padding: 1rem; padding-bottom: 100px; }
      .page-header { padding: 1.5rem; }
      .header-actions { width: 100%; flex-direction: column; }
      .search-wrapper { width: 100%; min-width: unset; }
    }

    /* ===================== MODAL VER JOGO ===================== */
    .mj-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.55);
      backdrop-filter: blur(4px);
      z-index: 3000;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .mj-overlay.active { display: flex; }

    .mj-modal {
      background: #fff;
      border-radius: 20px;
      width: 100%;
      max-width: 660px;
      max-height: 90vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 25px 60px rgba(0,0,0,0.3);
      animation: mjPop 0.25s ease-out;
    }

    @keyframes mjPop {
      from { transform: scale(0.92); opacity: 0; }
      to   { transform: scale(1);    opacity: 1; }
    }

    .mj-header {
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      padding: 1.75rem 2rem 1.5rem;
      position: relative;
      color: #fff;
      flex-shrink: 0;
    }

    .mj-header-top {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .mj-icon {
      width: 52px;
      height: 52px;
      background: rgba(255,255,255,0.15);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.75rem;
      flex-shrink: 0;
    }

    .mj-title {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0 0 0.5rem;
      line-height: 1.2;
    }

    .mj-badges {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .mj-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      background: rgba(255,255,255,0.18);
      border-radius: 20px;
      padding: 0.3rem 0.75rem;
      font-size: 0.8125rem;
      font-weight: 600;
    }

    .mj-close {
      position: absolute;
      top: 1.25rem;
      right: 1.25rem;
      width: 36px;
      height: 36px;
      background: rgba(255,255,255,0.15);
      border: none;
      border-radius: 50%;
      color: #fff;
      font-size: 1.1rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s;
    }
    .mj-close:hover { background: rgba(255,255,255,0.3); }

    .mj-tabs {
      display: flex;
      border-bottom: 2px solid #e2e8f0;
      padding: 0 2rem;
      background: #fff;
      flex-shrink: 0;
    }

    .mj-tab {
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
    }
    .mj-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; }
    .mj-tab:hover:not(.active) { color: #475569; }

    .mj-body {
      padding: 1.75rem 2rem;
      overflow-y: auto;
      flex: 1;
    }

    .mj-panel { display: none; }
    .mj-panel.active { display: block; }

    .mj-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.875rem;
      margin-bottom: 0.875rem;
    }

    .mj-field {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1rem 1.125rem;
    }

    .mj-field-label {
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

    .mj-field-label i { font-size: 0.875rem; }

    .mj-field-value {
      font-size: 1rem;
      font-weight: 600;
      color: #1e293b;
    }

    .mj-field.full { grid-column: 1 / 3; }

    .mj-estado-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.375rem 0.875rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;
    }

    .mj-resultado-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1.5rem;
      text-align: center;
      margin-top: 0.875rem;
    }

    .mj-resultado-score {
      font-size: 3rem;
      font-weight: 800;
      letter-spacing: 3px;
      line-height: 1;
      margin-bottom: 0.5rem;
    }

    .mj-resultado-label {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.375rem 1rem;
      border-radius: 20px;
      font-size: 0.8125rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .mj-footer {
      padding: 1.25rem 2rem;
      border-top: 1px solid #e2e8f0;
      display: flex;
      justify-content: flex-end;
      flex-shrink: 0;
    }

    .mj-btn-fechar {
      padding: 0.75rem 1.75rem;
      background: #f1f5f9;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.9375rem;
      font-weight: 600;
      color: #475569;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      transition: 0.2s;
    }
    .mj-btn-fechar:hover { background: #e2e8f0; }

    @media (max-width: 600px) {
      .mj-grid { grid-template-columns: 1fr; }
      .mj-field.full { grid-column: 1; }
      .mj-title { font-size: 1.2rem; }
      .mj-body { padding: 1.25rem; }
      .mj-header { padding: 1.25rem; }
    }
</style>
</head>

<?php require('../../includes/sidebar.php'); ?>

<body>
<div class="main-content">
  <div class="content-wrapper">

    <?php if (isset($_SESSION['sucesso'])): ?>
      <div class="alert alert-success">
        <i class='bx bx-check-circle'></i>
        <?= $_SESSION['sucesso'] ?>
      </div>
      <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['erro'])): ?>
      <div class="alert alert-error">
        <i class='bx bx-error-circle'></i>
        <?= $_SESSION['erro'] ?>
      </div>
      <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <div class="page-header">
      <h1>
        <i class='bx bx-football'></i>
        Jogos<?= ($isTreinador || $isJogador) ? ' - Minha Equipa' : ' - Meu Clube' ?>
      </h1>
      <div class="header-actions">

        <div class="season-filter">
          <label>Temporada:</label>
          <select class="season-select" onchange="window.location.href='?temporada='+this.value+'&entries=<?= $porPagina ?>'">
            <?php foreach($temporadas as $temp): ?>
              <option value="<?= $temp['id'] ?>" <?= $temporadaSelecionadaId == $temp['id'] ? 'selected' : '' ?>>
                <?= h($temp['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if($isTreinador || $isJogador): ?>
          <?php
          $stmtTeam = $conn->prepare("SELECT CONCAT(c.nome, ' - ', t.escaloes) AS nome FROM teams t INNER JOIN clubs c ON c.id = t.club_id WHERE t.id = ?");
          $stmtTeam->bind_param("i", $user_team_id);
          $stmtTeam->execute();
          $teamInfo = $stmtTeam->get_result()->fetch_assoc();
          $stmtTeam->close();
          ?>
          <div class="team-locked-badge">
            <i class="bi bi-lock-fill"></i>
            <?= h($teamInfo['nome'] ?? 'Sem equipa atribuída') ?>
          </div>
        <?php endif; ?>

        <div class="search-wrapper">
          <i class='bx bx-search'></i>
          <input type="text" id="searchBar" class="search-input" placeholder="Pesquisar jogos...">
        </div>

        <?php if($isAdmin || $isTreinador): ?>
        <a href="criar_jogo.php" class="btn-add">
          <i class='bx bx-plus'></i>
          Adicionar Jogo
        </a>
        <?php endif; ?>

      </div>
    </div>

    <?php if($res && $res->num_rows > 0): ?>
    <div class="matches-grid">
      <?php while($r = $res->fetch_assoc()):
        $timestamp      = strtotime($r['data_jogo']);
        $dia            = $timestamp ? date('d', $timestamp) : '--';
        $mes            = $timestamp ? date('M', $timestamp) : '---';
        $horaFormatada  = $timestamp ? date('H:i', $timestamp) : '--:--';
        $golosMarcados  = intval($r['golos_marcados']);
        $golosSofridos  = intval($r['golos_sofridos']);
        $estadoClass    = strtolower($r['estado']);
        $isConcluido    = (strtolower($r['estado']) == 'concluído' || strtolower($r['estado']) == 'concluido');
        $isDecorrido    = (strtolower($r['estado']) == 'decorrido');

        if ($isConcluido || $isDecorrido) {
          $resultado      = getResultado($golosMarcados, $golosSofridos);
          $resultadoClass = strtolower($resultado);
          $scoreDisplay   = $golosMarcados . '-' . $golosSofridos;
          $labelDisplay   = $resultado;
        } elseif (strtolower($r['estado']) == 'adiado') {
          $resultado      = 'Adiado';
          $resultadoClass = 'adiado';
          $scoreDisplay   = '--';
          $labelDisplay   = 'ADIADO';
        } else {
          $resultado      = 'Em Curso';
          $resultadoClass = 'emcurso';
          $scoreDisplay   = '0-0';
          $labelDisplay   = 'EM CURSO';
        }

        $modalData = json_encode([
          "id"             => $r["id"],
          "equipa"         => $r["equipa"],
          "adversario"     => $r["adversario"],
          "data_jogo"      => formatarDataPorExtenso($r["data_jogo"]),
          "data_curta"     => $timestamp ? date('d/m/Y', $timestamp) : '--',
          "hora"           => $timestamp ? date('H:i', $timestamp) : '--:--',
          "escaloes"       => $r["escaloes"],
          "local"          => $r["local"],
          "estado"         => $r["estado"],
          "clube_nome"     => $r["clube_nome"],
          "team_nome"      => !empty($r["team_nome"]) ? $r["team_nome"] : $r["equipa"],
          "golos_marcados" => $golosMarcados,
          "golos_sofridos" => $golosSofridos,
          "resultado"      => $resultado,
          "resultado_class"=> $resultadoClass,
          "score_display"  => $scoreDisplay
        ], JSON_HEX_APOS | JSON_HEX_QUOT);
      ?>
      <div class="match-card jogo-row estado-<?= $estadoClass ?> resultado-<?= $resultadoClass ?>">

        <div class="match-date">
          <div class="day"><?= $dia ?></div>
          <div class="month"><?= $mes ?></div>
          <div class="time"><?= $horaFormatada ?></div>
        </div>

        <div class="match-info">
          <div class="match-teams">
            <span class="team-name"><?= h($r['equipa']) ?></span>
            <span class="vs-separator">vs</span>
            <span class="adversario-name"><?= h($r['adversario']) ?></span>
          </div>
          <div class="match-details">
            <div class="match-detail">
              <i class='bx bx-universal-access'></i>
              <span><?= h($r['escaloes']) ?></span>
            </div>
            <div class="match-detail">
              <i class='bx <?= $r['local'] == 'Casa' ? 'bx-home' : 'bx-map' ?>'></i>
              <span><?= h($r['local']) ?></span>
            </div>
          </div>
        </div>

        <div class="match-resultado">
          <div class="resultado-score"><?= $scoreDisplay ?></div>
          <div class="resultado-label"><?= $labelDisplay ?></div>
        </div>

        <div class="match-actions">
          <?php if($isAdmin): ?>
            <a href="editar.php?id=<?= (int)$r['id'] ?>" class="btn-edit">
              <i class='bx bx-edit'></i> Editar
            </a>
            <button class="btn-ver" onclick='openMatchModal(<?= $modalData ?>)'>
              <i class="bi bi-eye-fill"></i> Ver
            </button>
            <button
              class="btn-delete"
              data-id="<?= (int)$r['id'] ?>"
              data-nome="<?= h($r['equipa']) ?> vs <?= h($r['adversario']) ?>"
            ><i class='bx bx-trash'></i></button>
          <?php else: ?>
            <button class="btn-ver" onclick='openMatchModal(<?= $modalData ?>)'>
              <i class="bi bi-eye-fill"></i> Ver
            </button>
          <?php endif; ?>
        </div>

      </div>
      <?php endwhile; ?>
    </div>

    <div class="pagination-section pagination-footer">
      <div class="pagination-info">
        Mostrando <?= min($offset + $porPagina, $totalJogos) ?> de <?= $totalJogos ?> jogos
      </div>
      <div class="entries-control">
        <label>Mostrar</label>
        <select class="entries-select" onchange="window.location.href='?temporada=<?= $temporadaSelecionadaId ?>&entries='+this.value">
          <option value="10" <?= $porPagina == 10 ? 'selected' : '' ?>>10</option>
          <option value="25" <?= $porPagina == 25 ? 'selected' : '' ?>>25</option>
          <option value="50" <?= $porPagina == 50 ? 'selected' : '' ?>>50</option>
        </select>
        <label>entradas</label>
      </div>
    </div>

    <?php else: ?>
    <div class="empty-state">
      <i class='bx bx-football'></i>
      <h3>Nenhum jogo encontrado</h3>
      <p>
        <?php if($isTreinador || $isJogador): ?>
          Ainda não há jogos registados para a sua equipa nesta temporada
        <?php else: ?>
          Ainda não há jogos registados para o seu clube nesta temporada
        <?php endif; ?>
      </p>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ===================== MODAL VER JOGO ===================== -->
<div class="mj-overlay" id="modalVerJogo">
  <div class="mj-modal">

    <!-- HEADER -->
    <div class="mj-header">
      <div class="mj-header-top">
        <div class="mj-icon"><i class='bx bx-football'></i></div>
        <div>
          <h2 class="mj-title" id="mjTitulo">—</h2>
          <div class="mj-badges">
            <span class="mj-badge"><i class='bx bx-calendar'></i> <span id="mjDataCurta">—</span></span>
            <span class="mj-badge"><i class='bx bx-time'></i> <span id="mjHora">—</span></span>
            <span class="mj-badge" id="mjEstadoBadgeHeader"></span>
          </div>
        </div>
      </div>
      <button class="mj-close" onclick="fecharModalJogo()">✕</button>
    </div>

    <!-- TABS -->
    <div class="mj-tabs">
      <button class="mj-tab active" onclick="mjTab(this,'info')">
        <i class='bx bx-info-circle'></i> Informações
      </button>
      <button class="mj-tab" onclick="mjTab(this,'resultado')">
        <i class='bx bx-trophy'></i> Resultado
      </button>
    </div>

    <!-- BODY -->
    <div class="mj-body">

      <!-- Painel Informações -->
      <div class="mj-panel active" id="mjPanelInfo">
        <div class="mj-grid">
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-calendar'></i> Data</div>
            <div class="mj-field-value" id="mjData">—</div>
          </div>
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-time'></i> Hora</div>
            <div class="mj-field-value" id="mjHoraInfo">—</div>
          </div>
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-group'></i> Equipa</div>
            <div class="mj-field-value" id="mjEquipa">—</div>
          </div>
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-buildings'></i> Clube</div>
            <div class="mj-field-value" id="mjClube">—</div>
          </div>
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-flag'></i> Escalão</div>
            <div class="mj-field-value" id="mjEscalao">—</div>
          </div>
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-stats'></i> Estado</div>
            <div class="mj-field-value" id="mjEstadoInfo">—</div>
          </div>
          <div class="mj-field full">
            <div class="mj-field-label"><i class='bx bx-map'></i> Local</div>
            <div class="mj-field-value" id="mjLocal">—</div>
          </div>
        </div>
      </div>

      <!-- Painel Resultado -->
      <div class="mj-panel" id="mjPanelResultado">
        <div class="mj-grid">
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-shield'></i> Equipa</div>
            <div class="mj-field-value" id="mjEquipaRes">—</div>
          </div>
          <div class="mj-field">
            <div class="mj-field-label"><i class='bx bx-shield-alt-2'></i> Adversário</div>
            <div class="mj-field-value" id="mjAdversario">—</div>
          </div>
        </div>
        <div class="mj-resultado-box">
          <div class="mj-resultado-score" id="mjScore">—</div>
          <div id="mjResultadoBadge"></div>
        </div>
      </div>

    </div>

    <!-- FOOTER -->
    <div class="mj-footer">
      <button class="mj-btn-fechar" onclick="fecharModalJogo()">
        <i class='bx bx-arrow-back'></i> Fechar
      </button>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../toast.js"></script>

<script>
// =============================
//  DELETE — toast confirm
// =============================
document.querySelectorAll('.btn-delete').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const jogoId   = this.getAttribute('data-id');
    const jogoNome = this.getAttribute('data-nome');

    toast.confirm({
      type: 'warning',
      title: 'Eliminar Jogo?',
      message: `Tem a certeza que deseja eliminar o jogo:<br><strong>${jogoNome}</strong>?`,
      confirmText: 'Eliminar',
      cancelText: 'Cancelar',
      onConfirm: () => { eliminarJogo(jogoId, jogoNome); }
    });
  });
});

function eliminarJogo(id, nome) {
  toast.info('A eliminar...', 'Aguarde um momento');
  fetch('remover_jogo.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${encodeURIComponent(id)}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      toast.success('Jogo Eliminado', `O jogo <strong>${nome}</strong> foi removido com sucesso`);
      setTimeout(() => window.location.reload(), 1500);
    } else {
      toast.error('Erro', data.message || 'Não foi possível remover o jogo.');
    }
  })
  .catch(err => {
    console.error('Erro:', err);
    toast.error('Erro', 'Problema ao contactar o servidor.');
  });
}

// =============================
//  PESQUISA
// =============================
document.getElementById('searchBar')?.addEventListener('input', function() {
  const termo = this.value.toLowerCase();
  document.querySelectorAll('.jogo-row').forEach(row => {
    const texto = row.innerText.toLowerCase();
    row.style.display = texto.includes(termo) ? '' : 'none';
  });
});

// Auto-hide alerts
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity 0.3s ease';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 300);
  });
}, 5000);

// =============================
//  MODAL VER JOGO
// =============================
function mjTab(btn, panel) {
  document.querySelectorAll('.mj-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.mj-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('mjPanel' + panel.charAt(0).toUpperCase() + panel.slice(1)).classList.add('active');
}

function openMatchModal(m) {
  document.getElementById('mjTitulo').textContent    = m.equipa + ' vs ' + m.adversario;
  document.getElementById('mjDataCurta').textContent = m.data_curta;
  document.getElementById('mjHora').textContent      = m.hora;

  var estadoCores = {
    'concluído': { bg:'rgba(255,255,255,0.2)', cor:'#fff', icone:'bx-check-circle' },
    'concluido': { bg:'rgba(255,255,255,0.2)', cor:'#fff', icone:'bx-check-circle' },
    'agendado':  { bg:'rgba(255,255,255,0.2)', cor:'#fff', icone:'bx-calendar-check' },
    'adiado':    { bg:'rgba(255,100,100,0.3)', cor:'#fff', icone:'bx-error-circle' },
    'cancelado': { bg:'rgba(255,100,100,0.3)', cor:'#fff', icone:'bx-x-circle' },
    'decorrido': { bg:'rgba(255,255,255,0.2)', cor:'#fff', icone:'bx-check-circle' },
  };
  var ec = estadoCores[m.estado.toLowerCase()] || { bg:'rgba(255,255,255,0.2)', cor:'#fff', icone:'bx-info-circle' };
  document.getElementById('mjEstadoBadgeHeader').innerHTML =
    '<i class="bx ' + ec.icone + '"></i> ' + m.estado;
  document.getElementById('mjEstadoBadgeHeader').style.background = ec.bg;
  document.getElementById('mjEstadoBadgeHeader').style.color = ec.cor;

  document.getElementById('mjData').textContent     = m.data_curta;
  document.getElementById('mjHoraInfo').textContent = m.hora;
  document.getElementById('mjEquipa').textContent   = m.team_nome || m.equipa;
  document.getElementById('mjClube').textContent    = m.clube_nome || '—';
  document.getElementById('mjEscalao').textContent  = m.escaloes;
  document.getElementById('mjLocal').textContent    = m.local;

  var estadoInfoCores = {
    'concluído': { bg:'#dcfce7', cor:'#166534', icone:'bx-check-circle' },
    'concluido': { bg:'#dcfce7', cor:'#166534', icone:'bx-check-circle' },
    'agendado':  { bg:'#dbeafe', cor:'#1e40af', icone:'bx-calendar-check' },
    'adiado':    { bg:'#fee2e2', cor:'#991b1b', icone:'bx-error-circle' },
    'cancelado': { bg:'#fee2e2', cor:'#991b1b', icone:'bx-x-circle' },
    'decorrido': { bg:'#dcfce7', cor:'#166534', icone:'bx-check-circle' },
  };
  var ei = estadoInfoCores[m.estado.toLowerCase()] || { bg:'#f1f5f9', cor:'#475569', icone:'bx-info-circle' };
  document.getElementById('mjEstadoInfo').innerHTML =
    '<span class="mj-estado-badge" style="background:' + ei.bg + ';color:' + ei.cor + '">' +
    '<i class="bx ' + ei.icone + '"></i>' + m.estado + '</span>';

  document.getElementById('mjEquipaRes').textContent  = m.equipa;
  document.getElementById('mjAdversario').textContent = m.adversario;

  var scoreEl = document.getElementById('mjScore');
  var badgeEl = document.getElementById('mjResultadoBadge');

  var resCores = {
    win:    { bg:'#dcfce7', cor:'#166534', texto:'VITÓRIA', icone:'bx-trophy' },
    draw:   { bg:'#fef3c7', cor:'#92400e', texto:'EMPATE',  icone:'bx-minus-circle' },
    defeat: { bg:'#fee2e2', cor:'#991b1b', texto:'DERROTA', icone:'bx-x-circle' },
  };

  if (resCores[m.resultado_class]) {
    var rc = resCores[m.resultado_class];
    scoreEl.textContent = m.golos_marcados + ' — ' + m.golos_sofridos;
    scoreEl.style.color = rc.cor;
    badgeEl.innerHTML   =
      '<span class="mj-resultado-label" style="background:' + rc.bg + ';color:' + rc.cor + '">' +
      '<i class="bx ' + rc.icone + '"></i>' + rc.texto + '</span>';
  } else {
    scoreEl.textContent = m.score_display || '--';
    scoreEl.style.color = '#94a3b8';
    badgeEl.innerHTML   =
      '<span class="mj-resultado-label" style="background:#f1f5f9;color:#64748b">' + (m.resultado || '—') + '</span>';
  }

  // Reset para tab Informações
  document.querySelectorAll('.mj-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.mj-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.mj-tab')[0].classList.add('active');
  document.getElementById('mjPanelInfo').classList.add('active');

  document.getElementById('modalVerJogo').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function fecharModalJogo() {
  document.getElementById('modalVerJogo').classList.remove('active');
  document.body.style.overflow = '';
}

document.getElementById('modalVerJogo').addEventListener('click', function(e) {
  if (e.target === this) fecharModalJogo();
});
</script>

</body>
</html>