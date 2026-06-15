<?php
session_start();
header('Content-Type: application/json');

// ✅ Verificar login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require('../../config/db.php');

// ✅ Garantir tipo correto (INT)
$user_role = (int) ($_SESSION['user_role'] ?? 4);

// ✅ Permissões (superadmin=0, admin=1, etc.)
if (!in_array($user_role, [0, 1, 2])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissões']);
    exit;
}

// ✅ Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

// ✅ ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // ✅ Começar transação (IMPORTANTE)
    $conn->begin_transaction();

    // ✅ Verificar se equipa existe
    $stmt = $conn->prepare("SELECT id FROM teams WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Equipa não encontrada']);
        exit;
    }

    // 🔥 🔥 🔥 APAGAR DEPENDÊNCIAS (FIX DO TEU ERRO)
    
    // jogadores
    $stmt = $conn->prepare("DELETE FROM players WHERE team_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // jogos
    $stmt = $conn->prepare("DELETE FROM matches WHERE team_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // estatísticas dos jogos dessa equipa (extra segurança)
    $stmt = $conn->prepare("
        DELETE s FROM player_match_stats s
        JOIN matches m ON s.match_id = m.id
        WHERE m.team_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // ✅ Agora apagar a equipa
    $stmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Equipa eliminada com sucesso'
        ]);
    } else {
        $conn->rollback();

        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível eliminar'
        ]);
    }

} catch (Exception $e) {

    // ❌ erro → desfaz tudo
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Erro real: ' . $e->getMessage()
    ]);
}

$conn->close();
?>