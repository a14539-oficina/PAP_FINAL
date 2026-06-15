<?php
// FICHEIRO DE DEBUG - APAGA DEPOIS DE RESOLVER
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Mensalidades</h2>";

// 1. Verificar sessão
session_start();
echo "<p>✅ Sessão iniciada</p>";
echo "<pre>SESSION: " . print_r($_SESSION, true) . "</pre>";

// 2. Verificar conexão
if (file_exists('../../config/db.php')) {
    require('../../config/db.php');
    echo "<p>✅ Config carregado</p>";
} else {
    die("❌ Config não encontrado em ../../config/db.php");
}

if (isset($conn) && !$conn->connect_error) {
    echo "<p>✅ Conexão à BD OK</p>";
} else {
    die("❌ Erro de conexão: " . ($conn->connect_error ?? 'Conexão não existe'));
}

// 3. Verificar tabela mensalidades
$checkTable = $conn->query("SHOW TABLES LIKE 'mensalidades'");
if ($checkTable->num_rows > 0) {
    echo "<p>✅ Tabela 'mensalidades' existe</p>";
} else {
    die("❌ Tabela 'mensalidades' não existe");
}

// 4. Verificar colunas
$columns = $conn->query("SHOW COLUMNS FROM mensalidades");
echo "<p>✅ Colunas da tabela:</p><ul>";
while ($col = $columns->fetch_assoc()) {
    echo "<li>{$col['Field']} ({$col['Type']})</li>";
}
echo "</ul>";

// 5. Verificar dados
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : 11;
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : 2025;
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;

echo "<p>📊 Parâmetros: Mês={$mes}, Ano={$ano}, Club={$club_id}</p>";

$mesAno = "$ano-" . str_pad($mes, 2, '0', STR_PAD_LEFT);

// Query simples
$sql = "SELECT 
    m.*, 
    p.primeiro_nome,
    p.apelido
FROM mensalidades m
LEFT JOIN players p ON p.id = m.jogador_id
WHERE DATE_FORMAT(m.mes_referencia, '%Y-%m') = ?
LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $mesAno);
$stmt->execute();
$result = $stmt->get_result();

echo "<p>✅ Query executada: " . $result->num_rows . " resultados</p>";

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Jogador</th><th>Valor</th><th>Estado</th><th>Data Pagamento</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['primeiro_nome']} {$row['apelido']}</td>";
        echo "<td>{$row['valor']}</td>";
        echo "<td>{$row['estado']}</td>";
        echo "<td>{$row['data_pagamento']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>⚠️ Nenhuma mensalidade encontrada para $mesAno</p>";
    
    // Verificar se há mensalidades em geral
    $total = $conn->query("SELECT COUNT(*) as total FROM mensalidades")->fetch_assoc();
    echo "<p>ℹ️ Total de mensalidades na BD: {$total['total']}</p>";
}

echo "<hr><p>✅ Debug completo!</p>";