<?php
session_start();
require('../../config/db.php');
function h($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }

// 🔒 ===== CONTROLO DE ACESSO =====
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$user_role = $_SESSION['user_role'] ?? 4;
$is_admin = ($user_role == 1);

// Se for jogador, buscar o team_id dele
$user_team_id = null;
if (!$is_admin) {
    $stmt = $conn->prepare("
        SELECT p.team_id 
        FROM players p 
        JOIN users u ON p.email = u.email 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if ($user_data) {
        $user_team_id = $user_data['team_id'];
    } else {
        die('❌ Erro: Jogador não encontrado no sistema.');
    }
}

$positions = $conn->query("SELECT id, code, name FROM positions ORDER BY FIELD(code,'GK','DF','MF','FW'), name")->fetch_all(MYSQLI_ASSOC);
$seasons   = $conn->query("SELECT id, nome FROM seasons ORDER BY data_inicio DESC")->fetch_all(MYSQLI_ASSOC);

// 🔒 Carregar equipas conforme permissão
if ($is_admin) {
    $teams = $conn->query("SELECT id, nome FROM teams ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
} else {
    // Jogador só vê a própria equipa
    $stmt = $conn->prepare("SELECT id, nome FROM teams WHERE id = ?");
    $stmt->bind_param("i", $user_team_id);
    $stmt->execute();
    $teams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$player = [
  'primeiro_nome'=>'','ultimo_nome'=>'','data_nascimento'=>'',
  'altura_cm'=>'','peso_kg'=>'','pe_dominante'=>'D','position_id'=>null,'foto'=>'','ativo'=>1,'team_id'=>null
];

// ✅ CARREGAR JOGADOR COM VERIFICAÇÃO DE SEGURANÇA
if ($id > 0) {
    if ($is_admin) {
        // Admin pode ver qualquer jogador
        $stmt = $conn->prepare("SELECT * FROM players WHERE id=?");
        $stmt->bind_param("i", $id);
    } else {
        // Jogador só pode ver da própria equipa
        $stmt = $conn->prepare("SELECT * FROM players WHERE id=? AND team_id=?");
        $stmt->bind_param("ii", $id, $user_team_id);
    }
    
    $stmt->execute();
    if ($res = $stmt->get_result()) {
        $playerData = $res->fetch_assoc();
        if ($playerData) {
            $player = $playerData;
        } else {
            die('❌ Acesso negado: Não pode visualizar jogadores de outras equipas.');
        }
    }
}

$seasonAtual = $conn->query("SELECT id,nome FROM seasons WHERE data_inicio<=CURDATE() AND data_fim>=CURDATE() ORDER BY data_inicio DESC LIMIT 1")->fetch_assoc()
  ?? $conn->query("SELECT id,nome FROM seasons ORDER BY data_inicio DESC LIMIT 1")->fetch_assoc();

$stats = ['jogos'=>0,'titular'=>0,'minutos'=>0,'golos'=>0,'assist'=>0,'amarelos'=>0,'vermelhos'=>0];
if ($id > 0 && $seasonAtual) {
  $sql = "SELECT COUNT(pms.id) jogos,SUM(pms.started) titular,COALESCE(SUM(pms.minutes_played),0) minutos,
               SUM(pms.goals) golos,SUM(pms.assists) assist,SUM(pms.yellow_cards) amarelos,SUM(pms.red_cards) vermelhos
        FROM player_match_stats pms JOIN matches m ON m.id=pms.match_id
        WHERE pms.player_id=? AND m.season_id=?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $id, $seasonAtual['id']);
  $stmt->execute();
  $stats = $stmt->get_result()->fetch_assoc();
}

$healthData = ['exam_date'=>'','health_status'=>'Apto','health_notes'=>''];
if ($id > 0) {
  $columnsCheck = $conn->query("SHOW COLUMNS FROM player_health LIKE 'health_notes'")->num_rows;
  
  if ($columnsCheck > 0) {
    $stmt = $conn->prepare("SELECT exam_date, health_status, health_notes FROM player_health WHERE player_id=? ORDER BY exam_date DESC LIMIT 1");
  } else {
    $stmt = $conn->prepare("SELECT exam_date, health_status FROM player_health WHERE player_id=? ORDER BY exam_date DESC LIMIT 1");
  }
  
  $stmt->bind_param("i", $id);
  $stmt->execute();
  if ($res = $stmt->get_result()) {
    $health = $res->fetch_assoc();
    if ($health) {
      $healthData = array_merge($healthData, $health);
    }
  }
}

$contractData = ['start_date'=>'','end_date'=>'','wage_monthly'=>'','clauses'=>''];
$hasContract = false;
if ($id > 0 && $seasonAtual) {
  $columnsCheck = $conn->query("SHOW COLUMNS FROM contracts LIKE 'clauses'")->num_rows;
  
  if ($columnsCheck > 0) {
    $stmt = $conn->prepare("SELECT start_date, end_date, wage_monthly, clauses FROM contracts WHERE player_id=? AND season_id=? ORDER BY start_date DESC LIMIT 1");
  } else {
    $stmt = $conn->prepare("SELECT start_date, end_date, wage_monthly FROM contracts WHERE player_id=? AND season_id=? ORDER BY start_date DESC LIMIT 1");
  }
  
  $stmt->bind_param("ii", $id, $seasonAtual['id']);
  $stmt->execute();
  if ($res = $stmt->get_result()) {
    $contract = $res->fetch_assoc();
    if ($contract) {
      $contractData = array_merge($contractData, $contract);
      $hasContract = true;
    }
  }
}

$mensalidadeData = [
    'mes_referencia' => '', 
    'valor' => '', 
    'data_vencimento' => '',
    'status' => 'pendente', 
    'metodo_pagamento' => '',
    'observacoes' => ''
];
$hasMensalidade = false;

if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT mes_referencia, valor, data_vencimento, status, metodo_pagamento, observacoes 
        FROM mensalidades 
        WHERE jogador_id = ? 
        ORDER BY mes_referencia DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if ($res = $stmt->get_result()) {
        $mensalidade = $res->fetch_assoc();
        if ($mensalidade) {
            $mensalidadeData = $mensalidade;
            $hasMensalidade = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id ? 'Editar Jogador' : 'Novo Jogador' ?> - SportGes</title>
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

.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.toast {
  background: white;
  border-radius: 12px;
  padding: 1rem 1.25rem;
  box-shadow: 0 10px 30px rgba(0,0,0,0.15);
  display: flex;
  align-items: center;
  gap: 0.875rem;
  min-width: 320px;
  max-width: 450px;
  animation: slideIn 0.3s ease;
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

.btn-add-team {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.btn-add-team:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
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

.photo-section {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 3rem 2rem;
  text-align: center;
  border-bottom: 1px solid #e2e8f0;
}

.photo-preview-wrapper {
  width: 160px;
  height: 160px;
  margin: 0 auto 1.5rem;
  border-radius: 50%;
  overflow: hidden;
  border: 4px solid white;
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
  position: relative;
}

.photo-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: white;
  color: #cbd5e1;
}

.photo-placeholder i {
  font-size: 4rem;
  margin-bottom: 0.5rem;
}

.photo-placeholder p {
  margin: 0;
  font-size: 0.875rem;
  color: #94a3b8;
}

.photo-upload-label {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
  padding: 1rem 2rem;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.photo-upload-label:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
}

.photo-upload-label input {
  display: none;
}

.photo-info {
  margin-top: 1rem;
  font-size: 0.875rem;
  color: #64748b;
  line-height: 1.6;
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

.section-badge {
  background: #dbeafe;
  color: #1e40af;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
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

.apple-input:disabled,
.apple-select:disabled {
  background: #f1f5f9;
  cursor: not-allowed;
  opacity: 0.6;
}

.football-pitch-container {
  margin: 1.5rem 0;
}

.pitch-label-header {
  margin-bottom: 2rem;
}

.pitch-label-header .form-label {
  font-size: 1.125rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pitch-horizontal {
  background: linear-gradient(180deg, 
    rgba(34, 139, 34, 0.95) 0%, 
    rgba(50, 168, 82, 0.95) 25%,
    rgba(34, 139, 34, 0.95) 50%,
    rgba(50, 168, 82, 0.95) 75%,
    rgba(34, 139, 34, 0.95) 100%
  );
  padding: 80px 40px;
  border-radius: 16px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  display: flex;
  flex-direction: row;
  gap: 50px;
  width: 100%;
  min-height: 600px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  position: relative;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-wrap: nowrap;
}

.pitch-horizontal::before {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 2px;
  height: 100%;
  background: rgba(255, 255, 255, 0.4);
  z-index: 0;
}

.pitch-horizontal::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 140px;
  height: 140px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-radius: 50%;
  z-index: 0;
}

.pitch-row {
  display: flex;
  flex-direction: column;
  gap: 50px;
  align-items: center;
  justify-content: center;
  z-index: 1;
  position: relative;
  margin-left: auto;
  flex-shrink: 0;
}

.pitch-row:nth-child(2) {
  margin-right: auto;
  margin-left: 0;
}

.pitch-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
  align-items: center;
  z-index: 1;
  position: relative;
}

.position-slot {
  position: relative;
}

.position-slot input {
  display: none;
}

.position-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 75px;
  height: 75px;
  background: #ef4444;
  border: 4px solid white;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
  position: relative;
}

.pitch-column.gk .position-badge {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.3),
              0 4px 15px rgba(0, 0, 0, 0.4);
}

.position-code {
  font-weight: 900;
  font-size: 0.9375rem;
  color: white;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  letter-spacing: 0.5px;
}

.position-slot input:checked + .position-badge {
  background: #3b82f6;
  transform: scale(1.2);
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.4),
              0 6px 20px rgba(59, 130, 246, 0.6);
  animation: pulse 0.3s ease;
}

.pitch-column.gk .position-slot input:checked + .position-badge {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  box-shadow: 0 0 0 6px rgba(251, 191, 36, 0.5),
              0 6px 20px rgba(251, 191, 36, 0.6);
}

@keyframes pulse {
  0%, 100% { transform: scale(1.2); }
  50% { transform: scale(1.25); }
}

.position-badge:hover {
  transform: scale(1.15);
  box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.3),
              0 6px 20px rgba(0, 0, 0, 0.5);
}

.selected-position-display {
  margin-top: 2rem;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  text-align: center;
}

.selected-position-display .label {
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.75rem;
}

.selected-position-display .value {
  font-size: 1.25rem;
  color: #0f172a;
  font-weight: 700;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1.5rem;
  margin-bottom: 1rem;
}

.stat-card {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 1.5rem;
  border-radius: 12px;
  text-align: center;
  border: 2px solid #e2e8f0;
  transition: all 0.3s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.stat-label {
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.75rem;
}

.stat-value {
  font-size: 2rem;
  color: #0f172a;
  font-weight: 700;
}

.contract-toggle-section {
  margin-top: 1.5rem;
}

.contract-toggle-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.contract-toggle-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.contract-toggle-title i {
  font-size: 1.5rem;
  color: #3b82f6;
}

.contract-toggle-title h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.contract-switch-wrapper {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.contract-switch-label {
  font-size: 0.9375rem;
  color: #64748b;
  font-weight: 600;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 56px;
  height: 32px;
}

.toggle-switch input {
  display: none;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: #cbd5e1;
  border-radius: 32px;
  transition: all 0.3s;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 26px;
  width: 26px;
  left: 3px;
  bottom: 3px;
  background: white;
  border-radius: 50%;
  transition: all 0.3s;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

input:checked + .toggle-slider {
  background: #3b82f6;
}

input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

.contract-form-area {
  max-height: 0;
  overflow: hidden;
  opacity: 0;
  transition: all 0.4s ease;
}

.contract-form-area.active {
  max-height: 1000px;
  opacity: 1;
  margin-top: 1rem;
}

.contract-info-box {
  background: #eff6ff;
  border-left: 4px solid #3b82f6;
  padding: 1.25rem;
  border-radius: 12px;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.contract-info-box i {
  color: #3b82f6;
  font-size: 1.5rem;
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.contract-info-text {
  font-size: 0.9375rem;
  color: #1e40af;
  line-height: 1.6;
}

.contract-info-text strong {
  display: block;
  margin-bottom: 0.25rem;
  font-weight: 700;
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

.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 10000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(4px);
}

.modal-overlay.active {
  display: flex;
}

.modal-container {
  background: white;
  border-radius: 20px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    transform: translateY(-50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  padding: 2rem;
  border-bottom: 2px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.modal-header h2 i {
  color: #10b981;
  font-size: 1.75rem;
}

.modal-close {
  background: #f1f5f9;
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s;
  color: #64748b;
  font-size: 1.25rem;
}

.modal-close:hover {
  background: #e2e8f0;
  color: #334155;
  transform: rotate(90deg);
}

.modal-body {
  padding: 2rem;
}

.modal-footer {
  padding: 1.5rem 2rem;
  border-top: 2px solid #f1f5f9;
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
}

.btn-modal {
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
}

.btn-modal-cancel {
  background: #f1f5f9;
  color: #475569;
}

.btn-modal-cancel:hover {
  background: #e2e8f0;
  color: #334155;
}

.btn-modal-save {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.btn-modal-save:hover:not(:disabled) {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
}

.btn-modal-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 1023px) {
  .main-content {
    margin-left: 0;
    width: 100%;
    padding: 1.5rem;
  }
}

@media (max-width: 599px) {
  .main-content {
    padding: 1rem;
  }
  
  .page-header {
    padding: 1rem;
  }
  
  .form-section {
    padding: 1rem;
  }
  
  .modal-container {
    width: 95%;
    margin: 1rem;
  }
}
  </style>
</head>

<?php require('../../includes/sidebar.php'); ?>

<body>
  <div class="toast-container" id="toastContainer"></div>

  <!-- Modal para Adicionar Equipa -->
  <?php if($is_admin): ?>
  <div class="modal-overlay" id="addTeamModal">
    <div class="modal-container">
      <div class="modal-header">
        <h2>
          <i class="bi bi-shield-plus"></i>
          Nova Equipa
        </h2>
        <button class="modal-close" onclick="closeAddTeamModal()">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <form id="addTeamForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nome da Equipa *</label>
              <input type="text" name="team_name" class="apple-input" required placeholder="Ex: Equipa Principal">
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea name="team_description" rows="3" class="apple-input" style="resize: vertical;" placeholder="Adicione uma descrição para a equipa..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal btn-modal-cancel" onclick="closeAddTeamModal()">
            <i class="bi bi-x-circle"></i>
            Cancelar
          </button>
          <button type="submit" class="btn-modal btn-modal-save">
            <i class="bi bi-check-circle"></i>
            Guardar Equipa
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="main-content">
    <div class="content-wrapper">
      
      <div class="page-header">
        <div class="page-header-left">
          <h1>
            <i class="bi bi-person-<?= $id ? 'lines-fill' : 'plus-fill' ?>"></i>
            <?= $id ? 'Editar Jogador' : 'Novo Jogador' ?>
          </h1>
          <p><?= $id ? 'Atualize as informações do jogador' : 'Preencha todos os dados do novo jogador' ?></p>
        </div>
        <div class="page-header-actions">
          <a href="listar.php" class="btn-header btn-back">
            <i class="bi bi-arrow-left"></i>
            Voltar
          </a>
          
          <?php if($is_admin): ?>
            <button type="button" class="btn-header btn-add-team" onclick="openAddTeamModal()">
              <i class="bi bi-shield-plus"></i>
              Nova Equipa
            </button>
          <?php endif; ?>
          
          <?php if($id): ?>
            <button type="button" class="btn-header btn-delete" onclick="confirmDeletePlayer(<?= (int)$id ?>)">
              <i class="bi bi-trash-fill"></i>
              Eliminar
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-container">
        <form id="mainPlayerForm" enctype="multipart/form-data">
          
          <input type="hidden" name="action" value="save_player">
          <input type="hidden" name="id" value="<?= (int)$id ?>">
          <input type="hidden" name="foto" id="fotoInput" value="<?= h($player['foto']) ?>">
          <input type="hidden" name="position_id" id="position_id_input" value="<?= h($player['position_id']) ?>">

          <div class="photo-section">
            <div class="photo-preview-wrapper">
              <?php if (!empty($player['foto'])): ?>
                <img id="photoPreview" class="photo-preview" src="logos/<?= h($player['foto']) ?>" alt="Foto do Jogador">
                <div id="photoPlaceholder" class="photo-placeholder" style="display: none;">
                  <i class="bi bi-person-circle"></i>
                  <p>Sem foto</p>
                </div>
              <?php else: ?>
                <img id="photoPreview" class="photo-preview" style="display: none;" src="" alt="Foto do Jogador">
                <div id="photoPlaceholder" class="photo-placeholder">
                  <i class="bi bi-person-circle"></i>
                  <p>Sem foto</p>
                </div>
              <?php endif; ?>
            </div>
            
            <label class="photo-upload-label">
              <i class="bi bi-cloud-upload"></i>
              Carregar Foto
              <input type="file" id="photoInput" accept="image/*" onchange="previewPhoto(this)">
            </label>
            
            <div class="photo-info">
              📸 JPG, PNG, GIF • 💾 Máximo: 5MB
            </div>
          </div>

          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-person-circle"></i>
              <h2>Dados Pessoais</h2>
            </div>

            <div class="row g-3">
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
              
              <div class="col-md-12">
                <label class="form-label">
                  <i class="bi bi-shield-fill"></i> Equipa
                </label>
                <select name="team_id" id="team_id_select" class="apple-select" <?= !$is_admin ? 'disabled' : '' ?>>
                  <?php if($is_admin): ?>
                    <option value="">-- Sem Equipa --</option>
                  <?php endif; ?>
                  <?php foreach($teams as $team): ?>
                    <option value="<?= (int)$team['id'] ?>" <?= $player['team_id']==(int)$team['id']?'selected':'' ?>>
                      <?= h($team['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                
                <?php if(!$is_admin): ?>
                  <input type="hidden" name="team_id" value="<?= (int)$user_team_id ?>">
                  <small style="color: #64748b; font-size: 0.875rem; margin-top: 0.5rem; display: block;">
                    🔒 Apenas pode adicionar jogadores à sua equipa
                  </small>
                <?php endif; ?>
              </div>
            </div>
          </div>
           
          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-bullseye"></i>
              <h2>Posição em Campo</h2>
            </div>

            <div class="football-pitch-container">
              <div class="pitch-label-header">
                <label class="form-label">
                  <i class="bi bi-geo-alt-fill"></i> Selecione a Posição do Jogador
                </label>
              </div>

              <div class="pitch-horizontal">
                <div class="pitch-row">
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_gk" value="GK" <?= $player['position_id']=='GK'?'checked':'' ?> onchange="updatePosition('GK', 'Guarda-Redes')">
                    <label for="pos_gk" class="position-badge"><div class="position-code">GK</div></label>
                  </div>
                </div>

                <div class="pitch-row">
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_lb" value="LB" <?= $player['position_id']=='LB'?'checked':'' ?> onchange="updatePosition('LB', 'Lateral Esquerdo')">
                    <label for="pos_lb" class="position-badge"><div class="position-code">LB</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_cb2" value="CB" onchange="updatePosition('CB', 'Defesa Central')">
                    <label for="pos_cb2" class="position-badge"><div class="position-code">CB</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_cb1" value="CB" <?= $player['position_id']=='CB'?'checked':'' ?> onchange="updatePosition('CB', 'Defesa Central')">
                    <label for="pos_cb1" class="position-badge"><div class="position-code">CB</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_rb" value="RB" <?= $player['position_id']=='RB'?'checked':'' ?> onchange="updatePosition('RB', 'Lateral Direito')">
                    <label for="pos_rb" class="position-badge"><div class="position-code">RB</div></label>
                  </div>
                </div>

                <div class="pitch-row">
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_cdm" value="CDM" <?= $player['position_id']=='CDM'?'checked':'' ?> onchange="updatePosition('CDM', 'Médio Defensivo')">
                    <label for="pos_cdm" class="position-badge"><div class="position-code">CDM</div></label>
                  </div>
                </div>

                <div class="pitch-row">
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_lm" value="LM" <?= $player['position_id']=='LM'?'checked':'' ?> onchange="updatePosition('LM', 'Médio Esquerdo')">
                    <label for="pos_lm" class="position-badge"><div class="position-code">LM</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_cm" value="CM" <?= $player['position_id']=='CM'?'checked':'' ?> onchange="updatePosition('CM', 'Médio Centro')">
                    <label for="pos_cm" class="position-badge"><div class="position-code">CM</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_rm" value="RM" <?= $player['position_id']=='RM'?'checked':'' ?> onchange="updatePosition('RM', 'Médio Direito')">
                    <label for="pos_rm" class="position-badge"><div class="position-code">RM</div></label>
                  </div>
                </div>

                <div class="pitch-row">
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_cam" value="CAM" <?= $player['position_id']=='CAM'?'checked':'' ?> onchange="updatePosition('CAM', 'Médio Ofensivo')">
                    <label for="pos_cam" class="position-badge"><div class="position-code">CAM</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_cf" value="CF" <?= $player['position_id']=='CF'?'checked':'' ?> onchange="updatePosition('CF', 'Avançado Centro')">
                    <label for="pos_cf" class="position-badge"><div class="position-code">CF</div></label>
                  </div>
                </div>

                <div class="pitch-row">
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_lw" value="LW" <?= $player['position_id']=='LW'?'checked':'' ?> onchange="updatePosition('LW', 'Extremo Esquerdo')">
                    <label for="pos_lw" class="position-badge"><div class="position-code">LW</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_st" value="ST" <?= $player['position_id']=='ST'?'checked':'' ?> onchange="updatePosition('ST', 'Avançado')">
                    <label for="pos_st" class="position-badge"><div class="position-code">ST</div></label>
                  </div>
                  <div class="position-slot">
                    <input type="radio" name="position_radio" id="pos_rw" value="RW" <?= $player['position_id']=='RW'?'checked':'' ?> onchange="updatePosition('RW', 'Extremo Direito')">
                    <label for="pos_rw" class="position-badge"><div class="position-code">RW</div></label>
                  </div>
                </div>
              </div>

              <div class="selected-position-display">
                <div class="label">Posição Selecionada</div>
                <div class="value" id="selectedPositionDisplay">
                  <?php 
                    if ($player['position_id']) {
                      $positionNames = [
                        'GK' => 'GK - Guarda-Redes',
                        'LB' => 'LB - Lateral Esquerdo',
                        'CB' => 'CB - Defesa Central',
                        'RB' => 'RB - Lateral Direito',
                        'CDM' => 'CDM - Médio Defensivo',
                        'CM' => 'CM - Médio Centro',
                        'CAM' => 'CAM - Médio Ofensivo',
                        'CF' => 'CF - Avançado Centro',
                        'LW' => 'LW - Extremo Esquerdo',
                        'ST' => 'ST - Avançado',
                        'RW' => 'RW - Extremo Direito'
                      ];
                      echo h($positionNames[$player['position_id']] ?? 'Posição desconhecida');
                    } else {
                      echo 'Nenhuma posição selecionada';
                    }
                  ?>
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-graph-up-arrow"></i>
              <h2>Estatísticas — <?= h($seasonAtual['nome'] ?? 'Época Atual') ?></h2>
              <span class="section-badge">Automático</span>
            </div>

            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-label">Jogos</div>
                <div class="stat-value"><?= (int)$stats['jogos'] ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Titular</div>
                <div class="stat-value"><?= (int)$stats['titular'] ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Minutos</div>
                <div class="stat-value"><?= (int)$stats['minutos'] ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Golos</div>
                <div class="stat-value"><?= (int)$stats['golos'] ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Assists</div>
                <div class="stat-value"><?= (int)$stats['assist'] ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Amarelos</div>
                <div class="stat-value"><?= (int)$stats['amarelos'] ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Vermelhos</div>
                <div class="stat-value"><?= (int)$stats['vermelhos'] ?></div>
              </div>
            </div>

            <p style="color: #64748b; font-size: 0.9375rem; margin-top: 1rem; text-align: center;">
              ⚡ Estatísticas calculadas automaticamente com base nos jogos registados
            </p>
          </div>

          <div class="form-section">
            <div class="section-header">
              <i class="bi bi-heart-pulse"></i>
              <h2>Registo Médico</h2>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Data do Exame</label>
                <input type="date" name="exam_date" class="apple-input" value="<?= h($healthData['exam_date']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Estado de Saúde</label>
                <select name="health_status" class="apple-select">
                  <option value="Apto" <?= $healthData['health_status']=='Apto'?'selected':'' ?>>✅ Apto</option>
                  <option value="Condicionado" <?= $healthData['health_status']=='Condicionado'?'selected':'' ?>>⚠️ Condicionado</option>
                  <option value="Inapto" <?= $healthData['health_status']=='Inapto'?'selected':'' ?>>❌ Inapto</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Notas e Observações Médicas</label>
                <textarea name="health_notes" rows="3" class="apple-input" style="resize: vertical;" placeholder="Adicione observações médicas relevantes..."><?= h($healthData['health_notes']) ?></textarea>
              </div>
            </div>
          </div>  
            
          <div class="form-section">
            <div class="contract-toggle-section">
              <div class="contract-toggle-header">
                <div class="contract-toggle-title">
                  <i class="bi bi-file-earmark-text-fill"></i>
                  <h3>Informações de Contrato</h3>
                </div>
                
                <div class="contract-switch-wrapper">
                  <span class="contract-switch-label">Adicionar Contrato</span>
                  <label class="toggle-switch">
                    <input type="checkbox" id="contractToggle" onchange="toggleContractForm()">
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>

              <div class="contract-form-area" id="contractFormArea">
                <div class="contract-info-box">
                  <i class="bi bi-info-circle-fill"></i>
                  <div class="contract-info-text">
                    <strong>💼 Informações do Contrato</strong>
                    Preencha os dados do contrato do jogador. Todos os campos são opcionais, mas recomendamos preencher as datas de início e fim para melhor controlo.
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-6">
                    <label class="form-label">Data de Início</label>
                    <input type="date" name="contract_start_date" class="apple-input" value="">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Data de Fim</label>
                    <input type="date" name="contract_end_date" class="apple-input" value="">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Salário Mensal (€)</label>
                    <input type="number" step="0.01" name="wage_monthly" class="apple-input" value="" placeholder="Ex: 5000.00">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Época do Contrato</label>
                    <select name="contract_season_id" class="apple-select">
                      <option value="">Selecione a época</option>
                      <?php foreach($seasons as $season): ?>
                        <option value="<?= (int)$season['id'] ?>" <?= $seasonAtual && $seasonAtual['id']==$season['id']?'selected':'' ?>>
                          <?= h($season['nome']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Cláusulas e Observações</label>
                    <textarea name="contract_clauses" rows="3" class="apple-input" style="resize: vertical;" placeholder="Adicione cláusulas especiais, bónus, ou outras observações sobre o contrato..."></textarea>
                  </div>
                </div>
              </div>
            </div>

            <div class="contract-toggle-section">
              <div class="contract-toggle-header">
                <div class="contract-toggle-title">
                  <i class="bi bi-cash-coin"></i>
                  <h3>Mensalidades do Jogador</h3>
                </div>
                
                <div class="contract-switch-wrapper">
                  <span class="contract-switch-label">Adicionar Mensalidades</span>
                  <label class="toggle-switch">
                    <input type="checkbox" id="mensalidadeToggle" onchange="toggleMensalidadeForm()">
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>

              <div class="contract-form-area" id="mensalidadeFormArea">
                <div class="contract-info-box" style="background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-left: 4px solid #ffc107;">
                  <i class="bi bi-wallet2" style="color: #f59e0b;"></i>
                  <div class="contract-info-text" style="color: #92400e;">
                    <strong>💰 Gestão de Mensalidades</strong>
                    Configure o valor mensal que o jogador tem de pagar para treinar no clube.
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-6">
                    <label class="form-label">
                      <i class="bi bi-calendar-check"></i> Mês de Referência
                    </label>
                    <input type="date" name="mensalidade_mes_referencia" class="apple-input" value="<?= h($mensalidadeData['mes_referencia']) ?>">
                  </div>
                  <div class="col-6">
                    <label class="form-label">
                      <i class="bi bi-currency-euro"></i> Valor (€)
                    </label>
                    <input type="number" step="0.01" name="mensalidade_valor" class="apple-input" value="<?= h($mensalidadeData['valor']) ?>" placeholder="Ex: 50.00">
                  </div>
                  <div class="col-6">
                    <label class="form-label">
                      <i class="bi bi-calendar-event"></i> Data de Vencimento
                    </label>
                    <input type="date" name="mensalidade_data_vencimento" class="apple-input" value="<?= h($mensalidadeData['data_vencimento']) ?>">
                  </div>
                  <div class="col-6">
                    <label class="form-label">
                      <i class="bi bi-check-circle"></i> Estado de Pagamento
                    </label>
                    <select name="mensalidade_status" class="apple-select">
                      <option value="pendente" <?= $mensalidadeData['status']=='pendente'?'selected':'' ?>>⏳ Pendente</option>
                      <option value="pago" <?= $mensalidadeData['status']=='pago'?'selected':'' ?>>✅ Pago</option>
                      <option value="atrasado" <?= $mensalidadeData['status']=='atrasado'?'selected':'' ?>>⚠️ Atrasado</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label">
                      <i class="bi bi-credit-card"></i> Método de Pagamento
                    </label>
                    <input type="text" name="mensalidade_metodo_pagamento" class="apple-input" value="<?= h($mensalidadeData['metodo_pagamento']) ?>" placeholder="Ex: Transferência, Dinheiro, MB Way, etc">
                  </div>
                  <div class="col-12">
                    <label class="form-label">
                      <i class="bi bi-file-text"></i> Observações
                    </label>
                    <textarea name="mensalidade_observacoes" rows="3" class="apple-input" style="resize: vertical;" placeholder="Ex: Desconto de 20%, Isenção até dezembro, etc..."><?= h($mensalidadeData['observacoes']) ?></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="submit-section">
            <button type="submit" class="main-save-btn">
              <i class="bi bi-check-circle-fill"></i>
              <?= $id ? 'Atualizar Jogador' : 'Criar Jogador' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  <?php if($is_admin): ?>
  function openAddTeamModal() {
    document.getElementById('addTeamModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeAddTeamModal() {
    document.getElementById('addTeamModal').classList.remove('active');
    document.body.style.overflow = 'auto';
    document.getElementById('addTeamForm').reset();
  }

  document.getElementById('addTeamModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeAddTeamModal();
    }
  });

  document.getElementById('addTeamForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_team');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> A processar...';
    
    fetch('processar.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showToast('success', 'Sucesso!', 'Equipa adicionada com sucesso');
        closeAddTeamModal();
        
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Guardar Equipa';
        showToast('error', 'Erro!', data.message || 'Erro ao adicionar equipa');
      }
    })
    .catch(error => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Guardar Equipa';
      showToast('error', 'Erro!', 'Erro ao processar a solicitação');
    });
  });
  <?php endif; ?>

  function previewPhoto(input) {
    const preview = document.getElementById('photoPreview');
    const placeholder = document.getElementById('photoPlaceholder');
    
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        document.getElementById('fotoInput').value = e.target.result;
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  function updatePosition(code, name) {
    document.getElementById('position_id_input').value = code;
    document.getElementById('selectedPositionDisplay').textContent = code + ' - ' + name;
  }

  function toggleContractForm() {
    const checkbox = document.getElementById('contractToggle');
    const formArea = document.getElementById('contractFormArea');
    
    if (checkbox.checked) {
      formArea.classList.add('active');
    } else {
      formArea.classList.remove('active');
    }
  }

  function toggleMensalidadeForm() {
    const checkbox = document.getElementById('mensalidadeToggle');
    const formArea = document.getElementById('mensalidadeFormArea');
    
    if (checkbox.checked) {
      formArea.classList.add('active');
    } else {
      formArea.classList.remove('active');
    }
  }

  function showToast(type, title, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
      success: 'bi-check-circle-fill',
      error: 'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      info: 'bi-info-circle-fill'
    };
    
    toast.innerHTML = `
      <i class="toast-icon bi ${icons[type]}"></i>
      <div class="toast-content">
        <div class="toast-title">${title}</div>
        <div class="toast-message">${message}</div>
      </div>
      <button class="toast-close" onclick="this.parentElement.remove()">
        <i class="bi bi-x-lg"></i>
      </button>
    `;
    
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
  }

  function confirmDeletePlayer(playerId) {
    if (confirm('⚠️ Tem a certeza que deseja eliminar este jogador?\n\nEsta ação é irreversível e todos os dados associados serão permanentemente removidos.')) {
      const formData = new FormData();
      formData.append('action', 'delete_player');
      formData.append('id', playerId);
      
      fetch('processar.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('success', 'Sucesso!', 'Jogador eliminado com sucesso');
          setTimeout(() => window.location.href = 'listar.php', 1500);
        } else {
          showToast('error', 'Erro!', data.message || 'Erro ao eliminar jogador');
        }
      })
      .catch(error => {
        showToast('error', 'Erro!', 'Erro ao processar a solicitação');
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mainPlayerForm');
    if (!form) return;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const positionInput = document.getElementById('position_id_input');
      if (!positionInput || !positionInput.value) {
        showToast('warning', 'Atenção!', 'Por favor, selecione uma posição para o jogador');
        return;
      }
      
      const formData = new FormData(this);
      const contractToggle = document.getElementById('contractToggle');
      formData.append('has_contract', contractToggle && contractToggle.checked ? '1' : '0');
      
      const mensalidadeToggle = document.getElementById('mensalidadeToggle');
      formData.append('has_mensalidade', mensalidadeToggle && mensalidadeToggle.checked ? '1' : '0');
      
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> A processar...';
      }
      
      fetch('processar.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('success', 'Sucesso!', data.message);
          setTimeout(() => {
            window.location.href = 'listar.php';
          }, 1500);
        } else {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> <?= $id ? 'Atualizar Jogador' : 'Criar Jogador' ?>';
          }
          showToast('error', 'Erro!', data.message || 'Erro ao guardar jogador');
        }
      })
      .catch(error => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> <?= $id ? 'Atualizar Jogador' : 'Criar Jogador' ?>';
        }
        showToast('error', 'Erro!', 'Erro: ' + error.message);
      });
    });
    
    const selectedRadio = document.querySelector('input[name="position_radio"]:checked');
    if (selectedRadio) {
      selectedRadio.dispatchEvent(new Event('change'));
    }
  });
  </script>
</body>
</html>