<?php
// C:\xampp\htdocs\SportGes\modules\jogos\gravar.php
session_start();
require('../../config/db.php');

// Verificar se é uma requisição AJAX (opcional mas bom)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Se for POST, processar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar campos obrigatórios
    $erros = [];
    
    if (empty($_POST['team_id'])) $erros[] = "Equipa é obrigatória";
    if (empty($_POST['adversario'])) $erros[] = "Adversário é obrigatório";
    if (empty($_POST['data_jogo'])) $erros[] = "Data do jogo é obrigatória";
    if (empty($_POST['local'])) $erros[] = "Local é obrigatório";
    if (empty($_POST['estado'])) $erros[] = "Estado é obrigatório";
    
    if (!empty($erros)) {
        $_SESSION['erro'] = implode("<br>", $erros);
        header("Location: criar_jogo.php");
        exit;
    }
    
    // Receber os dados
    $team_id = intval($_POST['team_id']);
    $adversario = $conn->real_escape_string($_POST['adversario']);
    $data_jogo = $conn->real_escape_string(str_replace('T', ' ', $_POST['data_jogo']));
    $local = $conn->real_escape_string($_POST['local']);
    $estado = $conn->real_escape_string($_POST['estado']);
    $golos_marcados = isset($_POST['golos_marcados']) && $_POST['golos_marcados'] !== '' ? intval($_POST['golos_marcados']) : 0;
    $golos_sofridos = isset($_POST['golos_sofridos']) && $_POST['golos_sofridos'] !== '' ? intval($_POST['golos_sofridos']) : 0;
    
    // Verificar se é edição ou criação
    $jogo_id = isset($_POST['jogo_id']) && !empty($_POST['jogo_id']) ? intval($_POST['jogo_id']) : null;
    
    try {
        if ($jogo_id) {
            // EDITAR
            $sql = "UPDATE matches SET 
                    team_id = $team_id, 
                    adversario = '$adversario', 
                    data_jogo = '$data_jogo', 
                    local = '$local', 
                    estado = '$estado', 
                    golos_marcados = $golos_marcados, 
                    golos_sofridos = $golos_sofridos 
                    WHERE id = $jogo_id";
            
            if ($conn->query($sql)) {
                $_SESSION['sucesso'] = "Jogo atualizado com sucesso!";
            } else {
                throw new Exception("Erro ao atualizar: " . $conn->error);
            }
            
        } else {
            // CRIAR
            $sql = "INSERT INTO matches (team_id, adversario, data_jogo, local, estado, golos_marcados, golos_sofridos) 
                    VALUES ($team_id, '$adversario', '$data_jogo', '$local', '$estado', $golos_marcados, $golos_sofridos)";
            
            if ($conn->query($sql)) {
                $_SESSION['sucesso'] = "Jogo criado com sucesso!";
            } else {
                throw new Exception("Erro ao criar: " . $conn->error);
            }
        }
        
        header("Location: listar.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['erro'] = $e->getMessage();
        error_log("Erro ao processar jogo: " . $e->getMessage());
        header("Location: " . ($jogo_id ? "criar_jogo.php?id=$jogo_id" : "criar_jogo.php"));
        exit;
    }
}

// Se não for POST, redirecionar
header("Location: listar.php");
exit;
?>