<?php
session_start();
require('../../config/db.php');

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$id = (int)$_POST['id'];
$novoEstado = ($_POST['ativo'] == '1') ? 0 : 1;

$stmt = $conn->prepare("UPDATE teams SET ativo = ? WHERE id = ?");
$stmt->bind_param("ii", $novoEstado, $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'ativo' => $novoEstado
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar estado']);
}
