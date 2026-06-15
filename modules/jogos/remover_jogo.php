<?php
session_start();
require('../../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Não autenticado']);
  exit;
}

$user_role = $_SESSION['user_role'] ?? '';
if ($user_role != '1') {
  echo json_encode(['success' => false, 'message' => 'Sem permissões']);
  exit;
}

if (!isset($_POST['id'])) {
  echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
  exit;
}

$id = intval($_POST['id']);

try {
  $stmt = $conn->prepare("DELETE FROM matches WHERE id = ?");
  $stmt->bind_param("i", $id);
  
  if ($stmt->execute()) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Erro ao eliminar']);
  }
  $stmt->close();
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>