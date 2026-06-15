<?php
session_start();
header('Content-Type: application/json');

require('../../config/db.php');

// Verificar se é admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Verificar se recebeu o ID do jogo
if (!isset($_POST['match_id']) || empty($_POST['match_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do jogo não fornecido']);
    exit;
}

$match_id = intval($_POST['match_id']);

try {
    // Atualizar o estado do jogo para "Concluído"
    $stmt = $conn->prepare("UPDATE matches SET estado = 'Concluído' WHERE id = ?");
    $stmt->bind_param("i", $match_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao finalizar jogo: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Jogo não encontrado']);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Jogo finalizado com sucesso'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao finalizar jogo: ' . $e->getMessage()
    ]);
}
?>