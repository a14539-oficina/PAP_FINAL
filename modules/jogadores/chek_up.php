<?php
session_start();
require('../../config/db.php');

// Este ficheiro serve para verificar se tudo está configurado corretamente
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação do Sistema - SportGes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 40px 20px; }
        .check-item { padding: 15px; margin: 10px 0; border-radius: 8px; }
        .check-ok { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .check-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .check-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mb-4"><i class="bi bi-check-circle me-2"></i>Verificação do Sistema</h1>
    
    <?php
    $checks = [];
    
    // 1. Verificar sessão
    if (isset($_SESSION['user_id'])) {
        $checks[] = [
            'status' => 'ok',
            'title' => 'Sessão Ativa',
            'message' => 'Utilizador ID: ' . $_SESSION['user_id'] . ' | Role: ' . ($_SESSION['role'] ?? 'N/A')
        ];
    } else {
        $checks[] = [
            'status' => 'error',
            'title' => 'Sessão Inativa',
            'message' => 'Nenhum utilizador autenticado'
        ];
    }
    
    // 2. Verificar conexão à BD
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM players");
        $total = $result->fetch_assoc()['total'];
        $checks[] = [
            'status' => 'ok',
            'title' => 'Base de Dados',
            'message' => "Conexão OK | Total de jogadores: $total"
        ];
    } catch (Exception $e) {
        $checks[] = [
            'status' => 'error',
            'title' => 'Erro na Base de Dados',
            'message' => $e->getMessage()
        ];
    }
    
    // 3. Verificar ficheiros
    $files = [
        'index.php' => 'Lista de jogadores',
        'add_player.php' => 'Adicionar jogador',
        'edit_player.php' => 'Editar jogador',
        'delete_player.php' => 'Eliminar jogador'
    ];
    
    foreach ($files as $file => $desc) {
        if (file_exists($file)) {
            $checks[] = [
                'status' => 'ok',
                'title' => "Ficheiro: $file",
                'message' => "$desc - Existe"
            ];
        } else {
            $checks[] = [
                'status' => 'error',
                'title' => "Ficheiro: $file",
                'message' => "$desc - NÃO ENCONTRADO"
            ];
        }
    }
    
    // 4. Verificar diretório de uploads
    $upload_dir = "../../uploads/players/";
    if (is_dir($upload_dir)) {
        if (is_writable($upload_dir)) {
            $checks[] = [
                'status' => 'ok',
                'title' => 'Diretório de Uploads',
                'message' => "Existe e tem permissões de escrita"
            ];
        } else {
            $checks[] = [
                'status' => 'warning',
                'title' => 'Diretório de Uploads',
                'message' => "Existe mas SEM permissões de escrita"
            ];
        }
    } else {
        $checks[] = [
            'status' => 'warning',
            'title' => 'Diretório de Uploads',
            'message' => "NÃO existe (será criado automaticamente)"
        ];
    }
    
    // 5. Verificar tabela positions
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM positions");
        $total = $result->fetch_assoc()['total'];
        if ($total > 0) {
            $checks[] = [
                'status' => 'ok',
                'title' => 'Tabela Positions',
                'message' => "OK | Total de posições: $total"
            ];
        } else {
            $checks[] = [
                'status' => 'warning',
                'title' => 'Tabela Positions',
                'message' => "Existe mas está vazia"
            ];
        }
    } catch (Exception $e) {
        $checks[] = [
            'status' => 'error',
            'title' => 'Tabela Positions',
            'message' => "Erro: " . $e->getMessage()
        ];
    }
    
    // 6. Verificar PHP Extensions
    $extensions = ['mysqli', 'gd', 'fileinfo'];
    foreach ($extensions as $ext) {
        if (extension_loaded($ext)) {
            $checks[] = [
                'status' => 'ok',
                'title' => "Extensão PHP: $ext",
                'message' => "Instalada"
            ];
        } else {
            $checks[] = [
                'status' => 'error',
                'title' => "Extensão PHP: $ext",
                'message' => "NÃO instalada"
            ];
        }
    }
    
    // Mostrar resultados
    foreach ($checks as $check) {
        $class = 'check-' . $check['status'];
        $icon = $check['status'] === 'ok' ? 'check-circle-fill' : 
                ($check['status'] === 'warning' ? 'exclamation-triangle-fill' : 'x-circle-fill');
        
        echo "<div class='check-item $class'>";
        echo "<strong><i class='bi bi-$icon me-2'></i>{$check['title']}</strong><br>";
        echo "<small>{$check['message']}</small>";
        echo "</div>";
    }
    ?>
    
    <div class="mt-4">
        <a href="index.php" class="btn btn-primary">
            <i class="bi bi-arrow-left me-2"></i>Voltar aos Jogadores
        </a>
    </div>
</div>
</body>
</html>