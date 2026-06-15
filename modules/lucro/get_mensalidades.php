<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

session_start();
require('../../config/db.php');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Parâmetros da requisição
$mes = intval($_GET['mes'] ?? 0);
$ano = intval($_GET['ano'] ?? 0);
$club_id = intval($_GET['club_id'] ?? 0);

if ($mes < 1 || $mes > 12 || $ano < 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

$user_club_id = intval($_SESSION['club_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);
$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

// 🔥 SEMPRE USAR 'status' (coluna padronizada)
$colunaEstado = "status";

// Formato mês/ano
$mesAnoFormat = sprintf('%04d-%02d', $ano, $mes);
    $debug['mes_ano_format'] = $mesAnoFormat;
    
    // ✅ CONSTRUIR WHERE – usa COALESCE(data_pagamento, data_vencimento) como em listar.php
    $condicoes = ["DATE_FORMAT(COALESCE(m.data_pagamento, m.data_vencimento), '%Y-%m') = ?"];
    $params = [$mesAnoFormat];
    $types = "s";
    
    // Filtrar sempre pelo clube recebido (através de teams -> club_id), tal como em listar.php
    if ($club_id > 0) {
        $condicoes[] = "t.club_id = ?";
        $params[] = $club_id;
        $types .= "i";
    }

try {
    // 🔥 Query para buscar mensalidades SEMPRE FRESCAS
    if ($isAdminPrincipal) {
        if ($club_id > 0) {
            $sql = "
                SELECT 
                    m.id,
                    p.primeiro_nome as nome,
                    p.ultimo_nome as apelido,
                    CONCAT(p.primeiro_nome, ' ', p.ultimo_nome) as jogador_nome,
                    m.valor,
                    m.$colunaEstado AS estado,
                    m.data_pagamento,
                    m.data_vencimento,
                    m.mes_referencia,
                    m.jogador_id
                FROM mensalidades m
                INNER JOIN players p ON p.id = m.jogador_id
                INNER JOIN teams t ON p.team_id = t.id
                WHERE $whereClause
                AND t.club_id = ?
                ORDER BY 
                    CASE 
                        WHEN LOWER(m.$colunaEstado) = 'atrasado' THEN 1
                        WHEN LOWER(m.$colunaEstado) = 'pendente' THEN 2
                        WHEN LOWER(m.$colunaEstado) = 'pago' THEN 3
                    END,
                    p.primeiro_nome
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $mesAnoFormat, $club_id);
        } else {
            $sql = "
                SELECT 
                    m.id,
                    p.primeiro_nome as nome,
                    p.ultimo_nome as apelido,
                    CONCAT(p.primeiro_nome, ' ', p.ultimo_nome) as jogador_nome,
                    m.valor,
                    m.$colunaEstado AS estado,
                    m.data_pagamento,
                    m.data_vencimento,
                    m.mes_referencia,
                    m.jogador_id
                FROM mensalidades m
                INNER JOIN players p ON p.id = m.jogador_id
                WHERE DATE_FORMAT(m.data_vencimento, '%Y-%m') = ?
                ORDER BY 
                    CASE 
                        WHEN LOWER(m.$colunaEstado) = 'atrasado' THEN 1
                        WHEN LOWER(m.$colunaEstado) = 'pendente' THEN 2
                        WHEN LOWER(m.$colunaEstado) = 'pago' THEN 3
                    END,
                    p.primeiro_nome
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $mesAnoFormat);
        }
    } else {
        $sql = "
            SELECT 
                m.id,
                p.primeiro_nome as nome,
                p.ultimo_nome as apelido,
                CONCAT(p.primeiro_nome, ' ', p.ultimo_nome) as jogador_nome,
                m.valor,
                m.$colunaEstado AS estado,
                m.data_pagamento,
                m.data_vencimento,
                m.mes_referencia,
                m.jogador_id
            FROM mensalidades m
            INNER JOIN players p ON p.id = m.jogador_id
            INNER JOIN teams t ON p.team_id = t.id
            WHERE DATE_FORMAT(m.data_vencimento, '%Y-%m') = ?
            AND t.club_id = ?
            ORDER BY 
                CASE 
                    WHEN LOWER(m.$colunaEstado) = 'atrasado' THEN 1
                    WHEN LOWER(m.$colunaEstado) = 'pendente' THEN 2
                    WHEN LOWER(m.$colunaEstado) = 'pago' THEN 3
                END,
                p.primeiro_nome
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $mesAnoFormat, $user_club_id);
    }
    
    if (!$stmt) {
        throw new Exception("Erro na query: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mensalidades = [];
    while ($row = $result->fetch_assoc()) {
        // Normalizar estado para lowercase
        $row['estado'] = strtolower($row['estado']);
        $mensalidades[] = $row;
    }
    
    $stmt->close();
    
    // Retornar JSON
    echo json_encode([
        'success' => true,
        'data' => $mensalidades,
        'total' => count($mensalidades),
        'mes' => $mes,
        'ano' => $ano
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>