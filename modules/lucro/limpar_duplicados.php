<?php
session_start();
require('../../config/db.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    table { width: 100%; border-collapse: collapse; background: white; margin: 20px 0; }
    th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
    th { background: #4CAF50; color: white; }
    .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
    .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
    .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
    .btn { padding: 10px 20px; background: #dc3545; color: white; border: none; cursor: pointer; font-size: 16px; border-radius: 8px; }
    .btn:hover { background: #c82333; }
</style>";

echo "<h1>🧹 Limpeza de Mensalidades Duplicadas</h1>";
echo "<hr>";

// 1️⃣ BUSCAR DUPLICADOS PELO JOGADOR_ID E MÊS
echo "<h2>📊 Analisando duplicados...</h2>";

$query = "
    SELECT 
        m.jogador_id,
        CONCAT(p.primeiro_nome, ' ', p.ultimo_nome) as nome_jogador,
        DATE_FORMAT(m.data_vencimento, '%Y-%m') as mes,
        MIN(DATE_FORMAT(m.data_vencimento, '%m/%Y')) as mes_exibicao,
        COUNT(*) as total,
        GROUP_CONCAT(m.id ORDER BY m.id DESC) as ids,
        GROUP_CONCAT(DATE_FORMAT(m.data_vencimento, '%d/%m/%Y') ORDER BY m.id DESC SEPARATOR ' | ') as datas,
        GROUP_CONCAT(m.status ORDER BY m.id DESC SEPARATOR ' | ') as status_lista,
        GROUP_CONCAT(m.valor ORDER BY m.id DESC SEPARATOR ' | ') as valores
    FROM mensalidades m
    INNER JOIN players p ON m.jogador_id = p.id
    GROUP BY 
        m.jogador_id, 
        DATE_FORMAT(m.data_vencimento, '%Y-%m'),
        p.primeiro_nome,
        p.ultimo_nome
    HAVING COUNT(*) > 1
    ORDER BY total DESC, nome_jogador
";


$result = $conn->query($query);

if (!$result) {
    echo "<div class='error'>❌ Erro na query: " . $conn->error . "</div>";
    exit;
}

$duplicados = $result->fetch_all(MYSQLI_ASSOC);

if (empty($duplicados)) {
    echo "<div class='success'>✅ <strong>Não existem duplicados!</strong> Tudo limpo.</div>";
    echo "<p><a href='listar.php'>← Voltar para Receitas</a></p>";
    exit;
}

// Mostrar duplicados encontrados
echo "<div class='warning'>";
echo "⚠️ Encontrados <strong>" . count($duplicados) . "</strong> jogadores com mensalidades duplicadas:";
echo "</div>";

echo "<table>";
echo "<tr>
        <th>Jogador ID</th>
        <th>Nome do Jogador</th>
        <th>Mês/Ano</th>
        <th>Qtd Duplicados</th>
        <th>IDs (mais recente → mais antigo)</th>
        <th>Datas Vencimento</th>
        <th>Status</th>
        <th>Valores</th>
      </tr>";

$totalRemover = 0;
foreach ($duplicados as $dup) {
    echo "<tr>";
    echo "<td>{$dup['jogador_id']}</td>";
    echo "<td><strong>{$dup['nome_jogador']}</strong></td>";
    echo "<td>{$dup['mes_exibicao']}</td>";
    echo "<td style='text-align: center; color: red;'><strong>{$dup['total']}</strong></td>";
    echo "<td style='font-size: 11px;'>{$dup['ids']}</td>";
    echo "<td style='font-size: 11px;'>{$dup['datas']}</td>";
    echo "<td style='font-size: 11px;'>{$dup['status_lista']}</td>";
    echo "<td style='font-size: 11px;'>{$dup['valores']}</td>";
    echo "</tr>";
    $totalRemover += ($dup['total'] - 1);
}
echo "</table>";

echo "<div class='warning'>";
echo "<strong>📌 Total de registros que serão removidos: {$totalRemover}</strong><br>";
echo "<small>⚠️ Será mantido apenas o registro mais recente (ID maior) de cada jogador por mês</small>";
echo "</div>";

// 2️⃣ FORMULÁRIO DE CONFIRMAÇÃO
if (!isset($_POST['confirmar_limpeza'])) {
    echo "<form method='POST' onsubmit='return confirm(\"⚠️ ATENÇÃO!\\n\\nVocê está prestes a ELIMINAR {$totalRemover} mensalidades duplicadas.\\n\\nApenas a mensalidade mais recente de cada jogador será mantida.\\n\\nDeseja continuar?\");'>";
    echo "<button type='submit' name='confirmar_limpeza' class='btn'>🗑️ ELIMINAR {$totalRemover} DUPLICADOS AGORA</button>";
    echo "</form>";
}

// 3️⃣ EXECUTAR LIMPEZA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_limpeza'])) {
    echo "<hr>";
    echo "<h2>🗑️ Removendo duplicados...</h2>";
    
    // Remove mantendo o ID maior (mais recente)
    $queryLimpar = "
        DELETE m1 FROM mensalidades m1
        INNER JOIN mensalidades m2 
        WHERE m1.id < m2.id 
        AND m1.jogador_id = m2.jogador_id
        AND DATE_FORMAT(m1.data_vencimento, '%Y-%m') = DATE_FORMAT(m2.data_vencimento, '%Y-%m')
    ";
    
    if ($conn->query($queryLimpar)) {
        $removidos = $conn->affected_rows;
        echo "<div class='success'>";
        echo "✅ <strong>SUCESSO!</strong><br>";
        echo "🗑️ <strong>{$removidos}</strong> mensalidades duplicadas foram removidas!<br>";
        echo "✨ Cada jogador agora tem apenas 1 mensalidade por mês (a mais recente).";
        echo "</div>";
        
        // Mostrar o que restou (limitado a 50 registros)
        echo "<h3>📋 Últimas 50 mensalidades mantidas:</h3>";
        $queryMantidas = "
            SELECT 
                m.id,
                CONCAT(p.primeiro_nome, ' ', p.ultimo_nome) as nome_jogador,
                DATE_FORMAT(m.data_vencimento, '%d/%m/%Y') as data_vencimento,
                m.status,
                m.valor
            FROM mensalidades m
            INNER JOIN players p ON m.jogador_id = p.id
            ORDER BY m.data_vencimento DESC, p.primeiro_nome
            LIMIT 50
        ";
        
        $resultMantidas = $conn->query($queryMantidas);
        if ($resultMantidas && $resultMantidas->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Jogador</th><th>Data Vencimento</th><th>Status</th><th>Valor</th></tr>";
            while ($row = $resultMantidas->fetch_assoc()) {
                $statusColor = $row['status'] == 'pago' ? 'green' : ($row['status'] == 'atrasado' ? 'red' : 'orange');
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['nome_jogador']}</td>";
                echo "<td>{$row['data_vencimento']}</td>";
                echo "<td style='color: {$statusColor}; font-weight: bold;'>{$row['status']}</td>";
                echo "<td>{$row['valor']} € </td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<div class='error'>❌ Erro ao remover: " . $conn->error . "</div>";
    }
    
    // 4️⃣ CRIAR PROTEÇÃO CONTRA FUTUROS DUPLICADOS
    echo "<hr>";
    echo "<h2>🔒 Criando proteção contra duplicados futuros...</h2>";
    
    try {
        // Verificar se constraint já existe
        $checkConstraint = $conn->query("
            SELECT COUNT(*) as total 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'mensalidades' 
            AND index_name = 'unique_jogador_mes'
        ");
        
        if ($checkConstraint) {
            $constraintExists = $checkConstraint->fetch_assoc()['total'] > 0;
            
            if (!$constraintExists) {
                // Tentar criar constraint
                $alterResult = $conn->query("
                    ALTER TABLE mensalidades 
                    ADD UNIQUE KEY unique_jogador_mes (jogador_id, mes_referencia)
                ");
                
                if ($alterResult) {
                    echo "<div class='success'>✅ Constraint criada! Agora é impossível criar duplicados.</div>";
                } else {
                    echo "<div class='warning'>⚠️ Não foi possível criar constraint: " . $conn->error . "</div>";
                }
            } else {
                echo "<div class='success'>✅ Constraint já existe. Sistema protegido!</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div class='warning'>⚠️ Aviso ao criar constraint: " . $e->getMessage() . "</div>";
    }
    
    echo "<hr>";
    echo "<div class='success'>";
    echo "<h2>✅ Limpeza Concluída com Sucesso!</h2>";
    echo "<p><strong>Resultado:</strong></p>";
    echo "<ul>";
    echo "<li>✅ {$removidos} mensalidades duplicadas eliminadas</li>";
    echo "<li>✅ Cada jogador tem apenas 1 mensalidade por mês</li>";
    echo "<li>✅ Mantido apenas o registro mais recente de cada jogador</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<p><a href='receitas.php' style='font-size: 18px; background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-block; margin-top: 20px;'>✅ Voltar para Receitas</a></p>";
    exit;
}

echo "<hr>";
echo "<p><a href='receitas.php'>← Voltar sem fazer alterações</a></p>";
?>