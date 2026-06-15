<?php
/**
 * CRON JOB - Geração Automática de Relatórios Mensais
 * 
 * Este script deve ser executado automaticamente no último dia de cada mês
 * para gerar relatórios de despesas de todos os clubes.
 * 
 * Configuração do CRON (executar às 23:50 do último dia do mês):
 * 50 23 28-31 * * /usr/bin/php /caminho/para/cron_gerar_relatorios.php
 * 
 * OU executar manualmente via browser (protegido por chave):
 * https://seusite.com/cron_gerar_relatorios.php?key=SUA_CHAVE_SECRETA
 */

// Segurança - definir uma chave secreta
define('CRON_KEY', 'sportges_cron_2024'); // MUDE ESTA CHAVE!

// Verificar se é execução via CRON ou browser
$isCronExecution = (php_sapi_name() === 'cli');
$isBrowserExecution = isset($_GET['key']) && $_GET['key'] === CRON_KEY;

if (!$isCronExecution && !$isBrowserExecution) {
    die("Acesso negado! Este script só pode ser executado via CRON ou com chave válida.");
}

require('../../config/db.php');

// Verificar se é o último dia do mês
$hoje = date('Y-m-d');
$ultimoDiaMes = date('Y-m-t');

if ($hoje !== $ultimoDiaMes) {
    logMessage("AVISO: Hoje ($hoje) não é o último dia do mês ($ultimoDiaMes). Relatórios não serão gerados.");
    exit;
}

logMessage("=== INÍCIO DA GERAÇÃO AUTOMÁTICA DE RELATÓRIOS ===");
logMessage("Data: " . date('d/m/Y H:i:s'));

// Verificar se a coluna club_id existe
$checkColumn = $conn->query("SHOW COLUMNS FROM despesas LIKE 'club_id'");
$hasClubColumn = ($checkColumn && $checkColumn->num_rows > 0);

// Criar tabela de relatórios se não existir
$createTable = "CREATE TABLE IF NOT EXISTS relatorios_despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes INT NOT NULL,
    ano INT NOT NULL,
    club_id INT DEFAULT NULL,
    total_despesas DECIMAL(10,2) DEFAULT 0,
    total_pessoal DECIMAL(10,2) DEFAULT 0,
    total_infraestrutura DECIMAL(10,2) DEFAULT 0,
    total_equipamento DECIMAL(10,2) DEFAULT 0,
    total_utilidades DECIMAL(10,2) DEFAULT 0,
    total_transporte DECIMAL(10,2) DEFAULT 0,
    total_outras DECIMAL(10,2) DEFAULT 0,
    despesas_mensais DECIMAL(10,2) DEFAULT 0,
    despesas_unicas DECIMAL(10,2) DEFAULT 0,
    num_despesas INT DEFAULT 0,
    observacoes TEXT,
    gerado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    gerado_por INT DEFAULT NULL,
    tipo_geracao ENUM('automatico', 'manual') DEFAULT 'automatico',
    UNIQUE KEY unique_relatorio (mes, ano, club_id)
)";

if (!$conn->query($createTable)) {
    logMessage("ERRO ao criar tabela: " . $conn->error);
    exit;
}

// Determinar mês e ano a processar
$mesAtual = (int)date('n');
$anoAtual = (int)date('Y');

logMessage("Gerando relatórios para: " . date('F/Y', strtotime($hoje)));

// Buscar todos os clubes
$clubes = [];
if ($hasClubColumn) {
    $clubesQuery = $conn->query("SELECT id, nome FROM clubs ORDER BY id ASC");
    if ($clubesQuery) {
        $clubes = $clubesQuery->fetch_all(MYSQLI_ASSOC);
        logMessage("Total de clubes encontrados: " . count($clubes));
    }
} else {
    // Se não há coluna club_id, gerar relatório geral
    $clubes = [['id' => 0, 'nome' => 'Sistema Geral']];
}

$totalRelatoriosGerados = 0;
$totalErros = 0;

// Gerar relatório para cada clube
foreach ($clubes as $clube) {
    $clubId = $clube['id'];
    $clubeNome = $clube['nome'];
    
    logMessage("Processando clube: $clubeNome (ID: $clubId)");
    
    try {
        // Calcular datas do mês
        $dataInicio = "$anoAtual-" . str_pad($mesAtual, 2, '0', STR_PAD_LEFT) . "-01";
        $dataFim = date("Y-m-t", strtotime($dataInicio));
        
        logMessage("Período: $dataInicio até $dataFim");
        
        // Buscar dados do mês
        if ($hasClubColumn && $clubId > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as num_despesas,
                    COALESCE(SUM(valor), 0) as total,
                    COALESCE(SUM(CASE WHEN categoria='Pessoal' THEN valor ELSE 0 END), 0) as pessoal,
                    COALESCE(SUM(CASE WHEN categoria='Infraestrutura' THEN valor ELSE 0 END), 0) as infraestrutura,
                    COALESCE(SUM(CASE WHEN categoria='Equipamento' THEN valor ELSE 0 END), 0) as equipamento,
                    COALESCE(SUM(CASE WHEN categoria='Utilidades' THEN valor ELSE 0 END), 0) as utilidades,
                    COALESCE(SUM(CASE WHEN categoria='Transporte' THEN valor ELSE 0 END), 0) as transporte,
                    COALESCE(SUM(CASE WHEN categoria='Outras' THEN valor ELSE 0 END), 0) as outras,
                    COALESCE(SUM(CASE WHEN tipo='Mensal' THEN valor ELSE 0 END), 0) as mensais,
                    COALESCE(SUM(CASE WHEN tipo='Única' THEN valor ELSE 0 END), 0) as unicas
                FROM despesas 
                WHERE data BETWEEN ? AND ? AND club_id = ?
            ");
            $stmt->bind_param("ssi", $dataInicio, $dataFim, $clubId);
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as num_despesas,
                    COALESCE(SUM(valor), 0) as total,
                    COALESCE(SUM(CASE WHEN categoria='Pessoal' THEN valor ELSE 0 END), 0) as pessoal,
                    COALESCE(SUM(CASE WHEN categoria='Infraestrutura' THEN valor ELSE 0 END), 0) as infraestrutura,
                    COALESCE(SUM(CASE WHEN categoria='Equipamento' THEN valor ELSE 0 END), 0) as equipamento,
                    COALESCE(SUM(CASE WHEN categoria='Utilidades' THEN valor ELSE 0 END), 0) as utilidades,
                    COALESCE(SUM(CASE WHEN categoria='Transporte' THEN valor ELSE 0 END), 0) as transporte,
                    COALESCE(SUM(CASE WHEN categoria='Outras' THEN valor ELSE 0 END), 0) as outras,
                    COALESCE(SUM(CASE WHEN tipo='Mensal' THEN valor ELSE 0 END), 0) as mensais,
                    COALESCE(SUM(CASE WHEN tipo='Única' THEN valor ELSE 0 END), 0) as unicas
                FROM despesas 
                WHERE data BETWEEN ? AND ?
            ");
            $stmt->bind_param("ss", $dataInicio, $dataFim);
        }
        
        $stmt->execute();
        $dados = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        logMessage("Despesas encontradas: {$dados['num_despesas']} | Total: {$dados['total']} €");
        
        // Verificar se há despesas para reportar
        if ($dados['num_despesas'] == 0) {
            logMessage("AVISO: Nenhuma despesa registada para este clube no mês. Relatório não será gerado.");
            continue;
        }
        
        // Gerar observação automática
        $observacoes = "Relatório gerado automaticamente pelo sistema.\n";
        $observacoes .= "Total de despesas: {$dados['num_despesas']}\n";
        $observacoes .= "Maior categoria: " . identificarMaiorCategoria($dados);
        
        // Inserir ou atualizar relatório
        $clubIdValue = ($clubId > 0) ? $clubId : null;
        
        $stmtInsert = $conn->prepare("
            INSERT INTO relatorios_despesas 
            (mes, ano, club_id, total_despesas, total_pessoal, total_infraestrutura, 
             total_equipamento, total_utilidades, total_transporte, total_outras, 
             despesas_mensais, despesas_unicas, num_despesas, observacoes, tipo_geracao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'automatico')
            ON DUPLICATE KEY UPDATE
                total_despesas = VALUES(total_despesas),
                total_pessoal = VALUES(total_pessoal),
                total_infraestrutura = VALUES(total_infraestrutura),
                total_equipamento = VALUES(total_equipamento),
                total_utilidades = VALUES(total_utilidades),
                total_transporte = VALUES(total_transporte),
                total_outras = VALUES(total_outras),
                despesas_mensais = VALUES(despesas_mensais),
                despesas_unicas = VALUES(despesas_unicas),
                num_despesas = VALUES(num_despesas),
                observacoes = VALUES(observacoes),
                gerado_em = CURRENT_TIMESTAMP,
                tipo_geracao = 'automatico'
        ");
        
        $stmtInsert->bind_param("iiiddddddddddis", 
            $mesAtual, $anoAtual, $clubIdValue,
            $dados['total'], $dados['pessoal'], $dados['infraestrutura'],
            $dados['equipamento'], $dados['utilidades'], $dados['transporte'], 
            $dados['outras'], $dados['mensais'], $dados['unicas'],
            $dados['num_despesas'], $observacoes
        );
        
        if ($stmtInsert->execute()) {
            logMessage("✓ Relatório gerado com SUCESSO para $clubeNome");
            $totalRelatoriosGerados++;
        } else {
            logMessage("✗ ERRO ao gerar relatório para $clubeNome: " . $conn->error);
            $totalErros++;
        }
        $stmtInsert->close();
        
    } catch (Exception $e) {
        logMessage("✗ EXCEÇÃO ao processar $clubeNome: " . $e->getMessage());
        $totalErros++;
    }
    
    logMessage("---");
}

// Resumo final
logMessage("=== RESUMO DA EXECUÇÃO ===");
logMessage("Relatórios gerados: $totalRelatoriosGerados");
logMessage("Erros encontrados: $totalErros");
logMessage("=== FIM ===");

$conn->close();

// ================= FUNÇÕES AUXILIARES =================

function logMessage($message) {
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp $message\n";
    
    // Log em ficheiro
    $logDir = __DIR__ . '/../../logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'relatorios_' . date('Y-m') . '.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Mostrar também no output (útil para testes)
    echo $logMessage;
}

function identificarMaiorCategoria($dados) {
    $categorias = [
        'Pessoal' => $dados['pessoal'],
        'Infraestrutura' => $dados['infraestrutura'],
        'Equipamento' => $dados['equipamento'],
        'Utilidades' => $dados['utilidades'],
        'Transporte' => $dados['transporte'],
        'Outras' => $dados['outras']
    ];
    
    arsort($categorias);
    $maior = array_key_first($categorias);
    $valor = number_format($categorias[$maior], 2, ',', '.');
    
    return "$maior ({$valor} €)";
}
?>