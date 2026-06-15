<?php
session_start();
header('Content-Type: application/json');

require('../../config/db.php');

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$golo_id = isset($_POST['golo_id']) ? intval($_POST['golo_id']) : 0;

if (!$golo_id) {
    echo json_encode(['success' => false, 'message' => 'ID de golo inválido']);
    exit;
}

// Iniciar transação para garantir consistência
$conn->begin_transaction();

try {
    // Buscar informações do golo antes de remover
    $stmtInfo = $conn->prepare("SELECT match_id, equipa FROM match_goals WHERE id = ?");
    $stmtInfo->bind_param("i", $golo_id);
    $stmtInfo->execute();
    $goloInfo = $stmtInfo->get_result()->fetch_assoc();

    if (!$goloInfo) {
        throw new Exception('Golo não encontrado');
    }

    $match_id = $goloInfo['match_id'];
    $equipa = $goloInfo['equipa'];

    // Remover o golo
    $stmtDelete = $conn->prepare("DELETE FROM match_goals WHERE id = ?");
    $stmtDelete->bind_param("i", $golo_id);
    
    if (!$stmtDelete->execute()) {
        throw new Exception('Erro ao remover o golo');
    }

    // Atualizar o placar do jogo
    if ($equipa === 'casa') {
        // Diminuir golos marcados
        $stmtUpdate = $conn->prepare("UPDATE matches SET golos_marcados = GREATEST(0, golos_marcados - 1) WHERE id = ?");
    } else {
        // Diminuir golos sofridos
        $stmtUpdate = $conn->prepare("UPDATE matches SET golos_sofridos = GREATEST(0, golos_sofridos - 1) WHERE id = ?");
    }
    
    $stmtUpdate->bind_param("i", $match_id);
    
    if (!$stmtUpdate->execute()) {
        throw new Exception('Erro ao atualizar placar');
    }
    
    // Commit da transação
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Golo removido com sucesso'
    ]);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>