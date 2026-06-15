<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== '0') {
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Apenas administradores podem realizar esta ação.'
    ]);
    exit;
}

require('../../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método de requisição inválido.'
    ]);
    exit;
}

try {
    $id     = (int)($_POST['id'] ?? 0);
    $nome   = trim($_POST['nome'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $email  = trim($_POST['email_contacto'] ?? '');
    $tel    = trim($_POST['telefone'] ?? '');
    $ativo  = (int)($_POST['ativo'] ?? 1);
    $removeLogo = (int)($_POST['remove_logo'] ?? 0);
    $currentLogo = trim($_POST['current_logo'] ?? '');
    
    if (empty($nome) || strlen($nome) < 3) {
        throw new Exception('O nome do clube deve ter pelo menos 3 caracteres.');
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido.');
    }
    
    $uploadDir = __DIR__ . '/../../uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    
    $logoName = $currentLogo;
    
    if ($removeLogo === 1) {
        if (!empty($currentLogo) && file_exists($uploadDir . $currentLogo)) {
            unlink($uploadDir . $currentLogo);
        }
        $logoName = '';
    }
    
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['logo']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Formato de imagem inválido. Use PNG, JPG, GIF ou WEBP.');
        }
        
        if ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
            throw new Exception('A imagem não pode ter mais de 5MB.');
        }
        
        if (!empty($currentLogo) && file_exists($uploadDir . $currentLogo)) {
            unlink($uploadDir . $currentLogo);
        }
        
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $logoName = 'club_' . uniqid() . '_' . time() . '.' . $ext;
        
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $logoName)) {
            throw new Exception('Erro ao fazer upload da imagem.');
        }
    }
    
    if ($id > 0) {
        $sql = "UPDATE clubs 
                SET nome = ?, cidade = ?, email_contacto = ?, telefone = ?, logo = ?, ativo = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $stmt->bind_param("sssssii", $nome, $cidade, $email, $tel, $logoName, $ativo, $id);
        $mensagem = 'Clube atualizado com sucesso!';
    } else {
        $sql = "INSERT INTO clubs (nome, cidade, email_contacto, telefone, logo, ativo)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $stmt->bind_param("sssssi", $nome, $cidade, $email, $tel, $logoName, $ativo);
        $mensagem = 'Clube criado com sucesso!';
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    
    $newId = $id > 0 ? $id : $conn->insert_id;
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => $mensagem,
        'club_id' => $newId
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>