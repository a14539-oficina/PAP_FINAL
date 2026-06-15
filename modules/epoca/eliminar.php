<?php
session_start();
require_once(__DIR__ . '/../../config/db.php');

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array((string)$user_role, ['0', '1'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão. Role: ' . $user_role]);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido. POST recebido: ' . json_encode($_POST)]);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $stmt = $conn->prepare("SELECT id FROM seasons WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Época não encontrada.']);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM seasons WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>