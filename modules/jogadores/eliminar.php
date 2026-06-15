<?php
session_start();
require('../../config/db.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== '1') {
    echo json_encode(['success' => false, 'message' => 'Não tem permissão para eliminar jogadores.']);
    exit;
}

$player_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($player_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do jogador inválido.']);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();
    
    $stmt = $conn->prepare("SELECT foto FROM players WHERE id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $player = $result->fetch_assoc();
    
    if (!$player) {
        throw new Exception("Jogador não encontrado.");
    }
    
    $stmt = $conn->prepare("DELETE FROM player_health WHERE player_id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    $stmt = $conn->prepare("DELETE FROM contracts WHERE player_id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    $stmt = $conn->prepare("DELETE FROM player_match_stats WHERE player_id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    $stmt = $conn->prepare("DELETE FROM players WHERE id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    
    if (!empty($player['foto'])) {
        $foto_path = "../../uploads/players/" . $player['foto'];
        if (file_exists($foto_path)) {
            @unlink($foto_path);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Jogador eliminado com sucesso!'
    ]);
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao eliminar jogador ID {$player_id}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao eliminar jogador: ' . $e->getMessage()
    ]);
    exit;
}