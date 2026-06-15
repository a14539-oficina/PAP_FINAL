<?php
session_start();

require_once('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
  header("Location: ../../login.php");
  exit;
}

$user_role = $_SESSION['user_role'] ?? '';
$user_id   = $_SESSION['user_id'] ?? 0;

$isSuperAdmin = ($user_id == 7 || $user_role === '0');
$isAdmin      = ($user_role === '1');

if (!$isSuperAdmin && !$isAdmin) {
  echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
  exit;
}

function find_name_column($conn) {
    $candidates = ['nome', 'name', 'club_name', 'title', 'designation'];
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'clubs' 
            AND COLUMN_NAME IN ('" . implode("','", $candidates) . "') 
            LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['COLUMN_NAME'];
    }
    return 'nome';
}

$nameCol = find_name_column($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT $nameCol AS name FROM clubs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Clube não encontrado.']);
            exit;
        }

        $clube = $result->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM clubs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => "Clube \"{$clube['name']}\" eliminado com sucesso!"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao eliminar o clube.']);
        }

        $stmt->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }

    exit;
}

header("Location: listar.php");
exit;