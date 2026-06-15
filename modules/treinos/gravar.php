<?php
session_start();
require('../../config/db.php');

// Verificações de acesso...

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: listar.php");
    exit;
}

$acao = isset($_POST['acao']) ? $_POST['acao'] : '';

switch ($acao) {
    case 'criar_treino':
        criarTreino($conn);
        break;
    
    case 'editar_treino':
        editarTreino($conn);
        break;
    
    case 'eliminar_treino':
        eliminarTreino($conn);
        break;
    
    default:
        header("Location: listar.php");
        exit;
}

function criarTreino($conn) {
    $season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : 0;
    $team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    $data_treino = isset($_POST['data_treino']) ? $_POST['data_treino'] : '';
    $foco = isset($_POST['foco']) ? $_POST['foco'] : 'Tático';
    $notas = isset($_POST['notas']) ? $_POST['notas'] : '';
    
    if ($season_id <= 0 || $team_id <= 0 || empty($data_treino)) {
        $_SESSION['erro'] = 'Por favor, preencha todos os campos obrigatórios.';
        header("Location: editar.php");
        exit;
    }
    
    $data_treino_mysql = date('Y-m-d H:i:s', strtotime($data_treino));
    
    $stmt = $conn->prepare("INSERT INTO treinos (season_id, team_id, data_treino, foco, notas, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $season_id, $team_id, $data_treino, $foco, $notas);
    
    if ($stmt->execute()) {
        $_SESSION['sucesso'] = 'Treino criado com sucesso!';
        header("Location: listar.php");
    } else {
        $_SESSION['erro'] = 'Erro ao criar treino: ' . $conn->error;
        header("Location: editar.php");
    }
    
    $stmt->close();
    exit;
}

function editarTreino($conn) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : 0;
    $team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
    $data_treino = isset($_POST['data_treino']) ? $_POST['data_treino'] : '';
    $foco = isset($_POST['foco']) ? $_POST['foco'] : 'Tático';
    $notas = isset($_POST['notas']) ? $_POST['notas'] : '';
    
    if ($id <= 0 || $season_id <= 0 || $team_id <= 0 || empty($data_treino)) {
        $_SESSION['erro'] = 'Por favor, preencha todos os campos obrigatórios.';
        header("Location: editar.php?id=" . $id);
        exit;
    }
    
    $data_treino_mysql = date('Y-m-d H:i:s', strtotime($data_treino));
    
    $stmt = $conn->prepare("UPDATE treinos SET season_id = ?, team_id = ?, data_treino = ?, foco = ?, notas = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("iisssi", $season_id, $team_id, $data_treino, $foco, $notas, $id);
    
    if ($stmt->execute()) {
        $_SESSION['sucesso'] = 'Treino atualizado com sucesso!';
        header("Location: listar.php");
    } else {
        $_SESSION['erro'] = 'Erro ao atualizar treino: ' . $conn->error;
        header("Location: editar.php?id=" . $id);
    }
    
    $stmt->close();
    exit;
}

function eliminarTreino($conn) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        $_SESSION['erro'] = 'ID inválido.';
        header("Location: listar.php");
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id FROM treinos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['erro'] = 'Treino não encontrado.';
        header("Location: listar.php");
        exit;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM treinos WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['sucesso'] = 'Treino eliminado com sucesso!';
    } else {
        $_SESSION['erro'] = 'Erro ao eliminar treino: ' . $conn->error;
    }
    
    $stmt->close();
    header("Location: listar.php");
    exit;
}
?>