<?php
require('../../config/db.php');

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    // UPDATE - Editar membro existente
    $stmt = $conn->prepare("UPDATE staff SET nome=?, cargo_principal=?, telefone=?, email=?, foto=?, ativo=? WHERE id=?");
    $stmt->bind_param("sssssii", $_POST['nome'], $_POST['cargo_principal'], $_POST['telefone'], $_POST['email'], $_POST['foto'], $_POST['ativo'], $id);
} else {
    // INSERT - Criar novo membro
    $stmt = $conn->prepare("INSERT INTO staff(nome, cargo_principal, telefone, email, foto, ativo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $_POST['nome'], $_POST['cargo_principal'], $_POST['telefone'], $_POST['email'], $_POST['foto'], $_POST['ativo']);
}

if ($stmt->execute()) {
    // Sucesso
    header("Location: listar.php?msg=guardado");
    exit;
} else {
    // Erro
    header("Location: listar.php?erro=1");
    exit;
}
?>