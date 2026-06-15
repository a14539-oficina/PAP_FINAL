<?php
require('../../config/db.php');
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Receber dados JSON
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) throw new Exception("Sem dados recebidos.");

    // Extrair valores
    $id             = isset($data['id']) ? (int)$data['id'] : 0;
    $golosMarcados  = isset($data['golos_marcados']) ? (int)$data['golos_marcados'] : 0;
    $golosSofridos  = isset($data['golos_sofridos']) ? (int)$data['golos_sofridos'] : 0;
    $estado         = isset($data['estado']) ? trim($data['estado']) : '';

    if ($id <= 0) throw new Exception("ID inválido ($id).");

    // Debug — confirma nome das colunas e ID
    $debugQuery = $conn->query("SELECT id, estado FROM matches WHERE id = $id");
    if ($debugQuery->num_rows === 0) throw new Exception("Nenhum jogo encontrado com ID $id.");

    // Executar UPDATE
    $stmt = $conn->prepare("
        UPDATE matches
        SET golos_marcados = ?, golos_sofridos = ?, estado = ?
        WHERE id = ?
    ");
    $stmt->bind_param('iisi', $golosMarcados, $golosSofridos, $estado, $id);
    $stmt->execute();

    echo json_encode([
        'sucesso' => true,
        'debug' => [
            'id' => $id,
            'estado' => $estado,
            'golos_marcados' => $golosMarcados,
            'golos_sofridos' => $golosSofridos,
            'linhas_afetadas' => $stmt->affected_rows
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'trace' => $e->getFile() . ':' . $e->getLine()
    ]);
}
?>
