<?php
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Não autorizado']);
  exit;
}

$user_id = $_SESSION['user_id'];
$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

$user_role = $_SESSION['user_role'] ?? '4';
if (!$isAdminPrincipal && !in_array((string)$user_role, ['0','1','2','3'])) {
  echo json_encode(['success' => false, 'message' => 'Sem permissão']);
  exit;
}

$player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
$team_id   = isset($_POST['team_id'])   ? intval($_POST['team_id'])   : 0;

if ($player_id <= 0 || $team_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
  exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $stmt = $conn->prepare("UPDATE players SET team_id = ? WHERE id = ?");
  $stmt->bind_param("ii", $team_id, $player_id);
  $stmt->execute();
  $stmt->close();
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Erro na base de dados']);
}