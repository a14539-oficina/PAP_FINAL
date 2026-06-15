<?php
// Log de debug
error_log("=== ELIMINAR DESPESA INICIADO ===");
error_log("POST data: " . print_r($_POST, true));

// Define o cabeçalho para JSON ANTES de tudo
header('Content-Type: application/json');

session_start();

// Verifica se o arquivo de config existe
if (!file_exists('../../config/db.php')) {
    error_log("ERRO: Arquivo db.php não encontrado!");
    echo json_encode(['success' => false, 'message' => 'Erro de configuração']);
    exit;
}

require('../../config/db.php');

// Verifica conexão com BD
if (!isset($conn) || $conn->connect_error) {
    error_log("ERRO: Conexão com BD falhou!");
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com base de dados']);
    exit;
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    error_log("ERRO: Usuário não autenticado");
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id = $_SESSION['user_id'];
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

error_log("User ID: $user_id, Club ID: $user_club_id, Is Admin: " . ($isAdminPrincipal ? 'sim' : 'não'));

// Verifica permissões
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['1', '2'])) {
    error_log("ERRO: Sem permissão - Role: $user_role");
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERRO: Método não é POST");
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

// Pega o ID da despesa
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

error_log("ID recebido: $id");

if ($id <= 0) {
    error_log("ERRO: ID inválido ou zero");
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verifica se a tabela tem coluna club_id
    $checkColumn = $conn->query("SHOW COLUMNS FROM despesas LIKE 'club_id'");
    $hasClubColumn = ($checkColumn && $checkColumn->num_rows > 0);
    
    error_log("Tem coluna club_id: " . ($hasClubColumn ? 'sim' : 'não'));
    
    // Verifica se tem coluna foto_recibo
    $checkFotoColumn = $conn->query("SHOW COLUMNS FROM despesas LIKE 'foto_recibo'");
    $hasFotoColumn = ($checkFotoColumn && $checkFotoColumn->num_rows > 0);
    
    error_log("Tem coluna foto_recibo: " . ($hasFotoColumn ? 'sim' : 'não'));
    
    // Se tem foto, busca e deleta o arquivo
    if ($hasFotoColumn) {
        $uploadDir = '../../uploads/despesas/';
        $stmtFoto = $conn->prepare("SELECT foto_recibo FROM despesas WHERE id = ?");
        $stmtFoto->bind_param("i", $id);
        $stmtFoto->execute();
        $resultFoto = $stmtFoto->get_result();
        
        if ($rowFoto = $resultFoto->fetch_assoc()) {
            if ($rowFoto['foto_recibo'] && file_exists($uploadDir . $rowFoto['foto_recibo'])) {
                unlink($uploadDir . $rowFoto['foto_recibo']);
                error_log("Foto deletada: " . $rowFoto['foto_recibo']);
            }
        }
        $stmtFoto->close();
    }
    
    // Deleta a despesa
    if ($hasClubColumn && !$isAdminPrincipal) {
        error_log("Deletando com restrição de clube");
        $stmt = $conn->prepare("DELETE FROM despesas WHERE id = ? AND club_id = ?");
        $stmt->bind_param("ii", $id, $user_club_id);
    } else {
        error_log("Deletando sem restrição (admin principal)");
        $stmt = $conn->prepare("DELETE FROM despesas WHERE id = ?");
        $stmt->bind_param("i", $id);
    }
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        error_log("Query executada. Linhas afetadas: $affected");
        
        if ($affected > 0) {
            error_log("SUCESSO: Despesa eliminada");
            echo json_encode(['success' => true, 'message' => 'Despesa eliminada com sucesso']);
        } else {
            error_log("ERRO: Nenhuma linha afetada");
            echo json_encode(['success' => false, 'message' => 'Despesa não encontrada ou sem permissão']);
        }
    } else {
        error_log("ERRO SQL: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Erro ao executar: ' . $stmt->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("EXCEÇÃO: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}

$conn->close();
error_log("=== ELIMINAR DESPESA FINALIZADO ===");
?>