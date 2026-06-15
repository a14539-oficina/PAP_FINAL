<?php
/**
 * 🔥 CRON JOB - Gerar mensalidades automaticamente
 * Execute este ficheiro uma vez por mês via cron job do servidor
 * Ou execute manualmente acessando: https://seudominio.com/cron_mensalidades.php
 */

require_once('config/db.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

function gerarMensalidadesAutomaticas($conn) {
    $hoje = date('Y-m-d');
    $mesAtual = date('Y-m');
    $diaVencimento = 5;
    $dataVencimento = $mesAtual . '-' . str_pad($diaVencimento, 2, '0', STR_PAD_LEFT);
    
    // Buscar época ativa
    $stmtSeason = $conn->prepare("
        SELECT id, nome 
        FROM seasons 
        WHERE ? BETWEEN start_date AND end_date 
        ORDER BY start_date DESC 
        LIMIT 1
    ");
    $stmtSeason->bind_param("s", $hoje);
    $stmtSeason->execute();
    $resultSeason = $stmtSeason->get_result();
    
    if ($resultSeason->num_rows === 0) {
        $stmtSeason->close();
        return ['criadas' => 0, 'mensagem' => 'Nenhuma época ativa'];
    }
    
    $seasonData = $resultSeason->fetch_assoc();
    $season_id = $seasonData['id'];
    $stmtSeason->close();
    
    // Buscar jogadores ativos sem mensalidade no mês atual
    $query = "
        SELECT DISTINCT p.id as jogador_id, p.primeiro_nome, p.ultimo_nome
        FROM players p
        INNER JOIN teams t ON p.team_id = t.id
        WHERE p.ativo = 1
        AND p.id NOT IN (
            SELECT jogador_id 
            FROM mensalidades 
            WHERE season_id = ? 
            AND DATE_FORMAT(data_vencimento, '%Y-%m') = ?
        )
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $season_id, $mesAtual);
    $stmt->execute();
    $result = $stmt->get_result();
    $jogadores = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $valorMensalidade = 5000.00;
    $mensalidadesCriadas = 0;
    $mesNumero = (int)date('m');
    $anoAtual = (int)date('Y');
    
    foreach ($jogadores as $jogador) {
        $queryInsert = "
            INSERT INTO mensalidades 
            (jogador_id, valor, data_vencimento, mes_referencia, status, season_id, mes, ano) 
            VALUES (?, ?, ?, ?, 'pendente', ?, ?, ?)
        ";
        $stmtInsert = $conn->prepare($queryInsert);
        $stmtInsert->bind_param(
            "idsssii", 
            $jogador['jogador_id'], 
            $valorMensalidade, 
            $dataVencimento, 
            $mesAtual,
            $season_id,
            $mesNumero,
            $anoAtual
        );
        
        if ($stmtInsert->execute()) {
            $mensalidadesCriadas++;
        }
        $stmtInsert->close();
    }
    
    return [
        'criadas' => $mensalidadesCriadas,
        'season' => $seasonData['nome'],
        'mensagem' => "Geradas {$mensalidadesCriadas} mensalidades para {$seasonData['nome']}"
    ];
}

// Executar
$resultado = gerarMensalidadesAutomaticas($conn);

// Log
$logFile = __DIR__ . '/logs/cron_mensalidades.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logMessage = date('Y-m-d H:i:s') . " - " . json_encode($resultado, JSON_UNESCAPED_UNICODE) . PHP_EOL;
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Output
header('Content-Type: application/json');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);