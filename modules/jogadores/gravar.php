<?php
session_start();
require('../../config/db.php');

// Charset correto (acentos, emojis, etc.)
mysqli_set_charset($conn, "utf8mb4");

// =============================
// 🧩 Recolha de dados do formulário
// =============================
$id              = intval($_POST['id'] ?? 0);
$primeiro_nome   = trim($_POST['primeiro_nome'] ?? '');
$ultimo_nome     = trim($_POST['ultimo_nome'] ?? '');
$data_nascimento = trim($_POST['data_nascimento'] ?? '');
$altura_cm       = trim($_POST['altura_cm'] ?? '');
$peso_kg         = trim($_POST['peso_kg'] ?? '');
$pe_dominante    = trim($_POST['pe_dominante'] ?? 'D');
$foto            = trim($_POST['foto'] ?? '');
$ativo           = intval($_POST['ativo'] ?? 1);
$position_code   = trim($_POST['position_id'] ?? ''); // ⚽️ vem do input hidden (ex: GK, DF, MF, FW)

// =============================
// 🔍 Converter código da posição → ID real
// =============================
$position_id = null;
if ($position_code !== '') {
    $stmtPos = $conn->prepare("SELECT id FROM positions WHERE code = ?");
    $stmtPos->bind_param("s", $position_code);
    $stmtPos->execute();
    $resPos = $stmtPos->get_result();
    if ($rowPos = $resPos->fetch_assoc()) {
        $position_id = (int)$rowPos['id'];
    }
    $stmtPos->close();
}

// =============================
// ⚙️ Validação mínima
// =============================
if (empty($primeiro_nome) && empty($ultimo_nome)) {
    header("Location: editar.php?erro=nome_vazio");
    exit;
}

// =============================
// 💾 Inserir ou Atualizar
// =============================
try {
    if ($id > 0) {
        // Atualizar jogador existente
        $stmt = $conn->prepare("
            UPDATE players
            SET primeiro_nome=?, ultimo_nome=?, data_nascimento=?, altura_cm=?, peso_kg=?, 
                pe_dominante=?, position_id=?, foto=?, ativo=?
            WHERE id=?
        ");
        $stmt->bind_param(
            "sssssssiii",
            $primeiro_nome,
            $ultimo_nome,
            $data_nascimento,
            $altura_cm,
            $peso_kg,
            $pe_dominante,
            $position_id,
            $foto,
            $ativo,
            $id
        );
    } else {
        // Inserir novo jogador
        $stmt = $conn->prepare("
            INSERT INTO players (primeiro_nome, ultimo_nome, data_nascimento, altura_cm, peso_kg, pe_dominante, position_id, foto, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssssii",
            $primeiro_nome,
            $ultimo_nome,
            $data_nascimento,
            $altura_cm,
            $peso_kg,
            $pe_dominante,
            $position_id,
            $foto,
            $ativo
        );
    }

    // Executar query
    if ($stmt->execute()) {
        header("Location: listar.php?msg=guardado");
        exit;
    } else {
        header("Location: listar.php?erro=nao_guardado");
        exit;
    }

} catch (Exception $e) {
    error_log("Erro ao gravar jogador: " . $e->getMessage());
    header("Location: listar.php?erro=excecao");
    exit;
}
?>
