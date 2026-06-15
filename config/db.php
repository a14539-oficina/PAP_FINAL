<?php
$host   = "sql300.infinityfree.com";
$user   = "if0_42155522";
$pass   = "9ZWjHqmsS880KkX";
$dbname = "if0_42155522_sportges";
$port   = 3306;

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    http_response_code(500);
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Erro na ligação à base de dados.']));
    }
    die("Erro na ligação à base de dados: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>