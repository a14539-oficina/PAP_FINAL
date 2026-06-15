<?php
session_start();
header('Content-Type: application/json');

require('../../config/db.php');

// Verificar autenticação
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
$equipa = isset($_POST['equipa']) ? $_POST['equipa'] : '';
$jogador = isset($_POST['jogador']) ? trim($_POST['jogador']) : '';
$minuto = isset($_POST['minuto']) ? intval($_POST['minuto']) : 0;

// Validações
if ($match_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do jogo inválido']);
    exit;
}

if (!in_array($equipa, ['casa', 'fora'])) {
    echo json_encode(['success' => false, 'message' => 'Equipa inválida']);
    exit;
}

if (empty($jogador)) {
    echo json_encode(['success' => false, 'message' => 'Nome do jogador é obrigatório']);
    exit;
}

if ($minuto <= 0 || $minuto > 120) {
    echo json_encode(['success' => false, 'message' => 'Minuto inválido (1-120)']);
    exit;
}

try {
    // Iniciar transação
    $conn->begin_transaction();
    
    // PRIMEIRO: Verificar qual é o nome correto da coluna
    $checkColumns = $conn->query("SHOW COLUMNS FROM match_goals LIKE '%jogador%'");
    $columnName = 'jogador'; // padrão
    
    if ($checkColumns && $checkColumns->num_rows > 0) {
        $col = $checkColumns->fetch_assoc();
        $columnName = $col['Field']; // pode ser 'jogador' ou 'jogador_nome'
    }
    
    // Inserir golo usando o nome correto da coluna
    $query = "INSERT INTO match_goals (match_id, equipa, $columnName, minuto, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issi", $match_id, $equipa, $jogador, $minuto);
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao inserir golo: " . $stmt->error);
    }
    
    $golo_id = $conn->insert_id;
    
    // Atualizar resultado do jogo
    if ($equipa === 'casa') {
        $updateStmt = $conn->prepare("
            UPDATE matches 
            SET golos_marcados = golos_marcados + 1 
            WHERE id = ?
        ");
    } else {
        $updateStmt = $conn->prepare("
            UPDATE matches 
            SET golos_sofridos = golos_sofridos + 1 
            WHERE id = ?
        ");
    }
    
    $updateStmt->bind_param("i", $match_id);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Erro ao atualizar resultado: " . $updateStmt->error);
    }
    
    // Commit da transação
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Golo registado com sucesso',
        'golo_id' => $golo_id,
        'column_used' => $columnName
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao registar golo: ' . $e->getMessage()
    ]);
}
?>