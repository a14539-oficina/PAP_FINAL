<?php
session_start();
require('../../config/db.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

$user_club_id = $_SESSION['club_id'] ?? 0;

// ============================================
// FUNÇÃO AUXILIAR: Criar/Atualizar Despesa de Contrato
// ============================================
function sincronizarDespesaContrato($conn, $player_id, $contract_data, $user_club_id) {
    if (empty($contract_data['wage_monthly']) || $contract_data['wage_monthly'] <= 0) {
        return true; // Sem salário, não cria despesa
    }

    // Buscar info do jogador
    $stmt = $conn->prepare("SELECT primeiro_nome, ultimo_nome FROM players WHERE id = ?");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$player) return false;

    $descricao = "Salário - " . $player['primeiro_nome'] . " " . $player['ultimo_nome'];
    $valor = floatval($contract_data['wage_monthly']);
    $data_inicio = $contract_data['start_date'] ?? date('Y-m-d');
    
    // Verifica se já existe despesa para este contrato
    $checkStmt = $conn->prepare("SELECT id FROM despesas WHERE player_id = ? AND categoria = 'Pessoal' AND tipo = 'Mensal'");
    $checkStmt->bind_param("i", $player_id);
    $checkStmt->execute();
    $existente = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existente) {
        // Atualiza despesa existente
        $stmt = $conn->prepare("UPDATE despesas SET descricao = ?, valor = ?, data = ? WHERE id = ?");
        $stmt->bind_param("sdsi", $descricao, $valor, $data_inicio, $existente['id']);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    } else {
        // Cria nova despesa
        $stmt = $conn->prepare("INSERT INTO despesas (descricao, valor, categoria, data, tipo, club_id, player_id, estado) VALUES (?, ?, 'Pessoal', ?, 'Mensal', ?, ?, 'Pendente')");
        $stmt->bind_param("sdsii", $descricao, $valor, $data_inicio, $user_club_id, $player_id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
}

// ============================================
// SALVAR JOGADOR (COM CONTRATO)
// ============================================
if ($_POST['action'] === 'save_player') {
    try {
        $conn->begin_transaction();

        $id = (int)($_POST['id'] ?? 0);
        $primeiro_nome = trim($_POST['primeiro_nome']);
        $ultimo_nome = trim($_POST['ultimo_nome']);
        $data_nascimento = $_POST['data_nascimento'];
        $altura_cm = !empty($_POST['altura_cm']) ? (int)$_POST['altura_cm'] : null;
        $peso_kg = !empty($_POST['peso_kg']) ? (float)$_POST['peso_kg'] : null;
        $pe_dominante = $_POST['pe_dominante'];
        $position_id = $_POST['position_id'] ?? null;
        $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
        $team_id = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;

        // Upload da foto
        $foto = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../logos/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

            $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExt, $allowedExts)) {
                $newFileName = uniqid('player_', true) . '.' . $fileExt;
                $filePath = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $filePath)) {
                    $foto = $newFileName;
                }
            }
        }

        // Inserir ou atualizar jogador
        if ($id > 0) {
            if ($foto) {
                $stmt = $conn->prepare("UPDATE players SET primeiro_nome=?, ultimo_nome=?, data_nascimento=?, altura_cm=?, peso_kg=?, pe_dominante=?, position_id=?, foto=?, ativo=?, team_id=? WHERE id=?");
                $stmt->bind_param("sssidsssiii", $primeiro_nome, $ultimo_nome, $data_nascimento, $altura_cm, $peso_kg, $pe_dominante, $position_id, $foto, $ativo, $team_id, $id);
            } else {
                $stmt = $conn->prepare("UPDATE players SET primeiro_nome=?, ultimo_nome=?, data_nascimento=?, altura_cm=?, peso_kg=?, pe_dominante=?, position_id=?, ativo=?, team_id=? WHERE id=?");
                $stmt->bind_param("sssidssiii", $primeiro_nome, $ultimo_nome, $data_nascimento, $altura_cm, $peso_kg, $pe_dominante, $position_id, $ativo, $team_id, $id);
            }
            $stmt->execute();
            $stmt->close();
            $player_id = $id;
        } else {
            $stmt = $conn->prepare("INSERT INTO players (primeiro_nome, ultimo_nome, data_nascimento, altura_cm, peso_kg, pe_dominante, position_id, foto, ativo, team_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssidsssii", $primeiro_nome, $ultimo_nome, $data_nascimento, $altura_cm, $peso_kg, $pe_dominante, $position_id, $foto, $ativo, $team_id);
            $stmt->execute();
            $player_id = $conn->insert_id;
            $stmt->close();
        }

        // Saúde
        if (!empty($_POST['exam_date']) || !empty($_POST['health_status']) || !empty($_POST['health_notes'])) {
            $exam_date = $_POST['exam_date'];
            $health_status = $_POST['health_status'] ?? 'Apto';
            $health_notes = $_POST['health_notes'] ?? '';

            $stmt = $conn->prepare("INSERT INTO player_health (player_id, exam_date, health_status, health_notes) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $player_id, $exam_date, $health_status, $health_notes);
            $stmt->execute();
            $stmt->close();
        }

        // 🔥 CONTRATO - COM SINCRONIZAÇÃO DE DESPESAS
        if (isset($_POST['has_contract']) && $_POST['has_contract'] == '1') {
            $wage_monthly = !empty($_POST['wage_monthly']) ? (float)$_POST['wage_monthly'] : null;
            $contract_season_id = !empty($_POST['contract_season_id']) ? (int)$_POST['contract_season_id'] : null;
            $contract_clauses = $_POST['contract_clauses'] ?? '';

            if ($wage_monthly && $contract_season_id) {
                // Buscar datas da época
                $stmt = $conn->prepare("SELECT data_inicio, data_fim FROM seasons WHERE id = ?");
                $stmt->bind_param("i", $contract_season_id);
                $stmt->execute();
                $season = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $start_date = $season['data_inicio'] ?? date('Y-m-d');
                $end_date = $season['data_fim'] ?? date('Y-m-d', strtotime('+1 year'));

                // Verifica se já existe contrato
                $checkStmt = $conn->prepare("SELECT id FROM contracts WHERE player_id = ? AND season_id = ?");
                $checkStmt->bind_param("ii", $player_id, $contract_season_id);
                $checkStmt->execute();
                $existente = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if ($existente) {
                    // Atualiza contrato
                    $stmt = $conn->prepare("UPDATE contracts SET start_date=?, end_date=?, wage_monthly=?, clauses=? WHERE id=?");
                    $stmt->bind_param("ssdsi", $start_date, $end_date, $wage_monthly, $contract_clauses, $existente['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Cria novo contrato
                    $stmt = $conn->prepare("INSERT INTO contracts (player_id, season_id, start_date, end_date, wage_monthly, clauses) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissds", $player_id, $contract_season_id, $start_date, $end_date, $wage_monthly, $contract_clauses);
                    $stmt->execute();
                    $stmt->close();
                }

                // 🚀 SINCRONIZAR DESPESA DO CONTRATO
                $contract_data = [
                    'wage_monthly' => $wage_monthly,
                    'start_date' => $start_date
                ];
                sincronizarDespesaContrato($conn, $player_id, $contract_data, $user_club_id);
            }
        }

        // Mensalidades
        if (isset($_POST['has_mensalidade']) && $_POST['has_mensalidade'] == '1') {
            $mensalidade_epoca = !empty($_POST['mensalidade_epoca']) ? (int)$_POST['mensalidade_epoca'] : null;
            $mensalidade_valor = !empty($_POST['mensalidade_valor']) ? (float)$_POST['mensalidade_valor'] : null;
            $mensalidade_metodo = $_POST['mensalidade_metodo_pagamento'] ?? '';
            $mensalidade_obs = $_POST['mensalidade_observacoes'] ?? '';

            if ($mensalidade_epoca && $mensalidade_valor) {
                // Buscar ano da época
                $stmt = $conn->prepare("SELECT YEAR(data_inicio) as ano FROM seasons WHERE id = ?");
                $stmt->bind_param("i", $mensalidade_epoca);
                $stmt->execute();
                $season = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $ano = $season['ano'] ?? date('Y');
                $mes_ref = date('Y-m-01');
                $data_venc = date('Y-m-05');

                $checkStmt = $conn->prepare("SELECT id FROM mensalidades WHERE jogador_id = ? AND season_id = ?");
                $checkStmt->bind_param("ii", $player_id, $mensalidade_epoca);
                $checkStmt->execute();
                $existente = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if ($existente) {
                    $stmt = $conn->prepare("UPDATE mensalidades SET valor=?, metodo_pagamento=?, observacoes=? WHERE id=?");
                    $stmt->bind_param("dssi", $mensalidade_valor, $mensalidade_metodo, $mensalidade_obs, $existente['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("INSERT INTO mensalidades (jogador_id, season_id, mes_referencia, valor, data_vencimento, metodo_pagamento, observacoes, ano, mes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $mes_num = (int)date('m');
                    $stmt->bind_param("iisdsssii", $player_id, $mensalidade_epoca, $mes_ref, $mensalidade_valor, $data_venc, $mensalidade_metodo, $mensalidade_obs, $ano, $mes_num);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Jogador guardado com sucesso!']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ELIMINAR JOGADOR
// ============================================
if ($_POST['action'] === 'delete_player') {
    try {
        $id = (int)$_POST['id'];
        
        $conn->begin_transaction();

        // Eliminar foto
        $stmt = $conn->prepare("SELECT foto FROM players WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $player = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($player && $player['foto']) {
            $filePath = '../../logos/' . $player['foto'];
            if (file_exists($filePath)) unlink($filePath);
        }

        // Eliminar despesas associadas ao jogador
        $stmt = $conn->prepare("DELETE FROM despesas WHERE player_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Eliminar contratos
        $stmt = $conn->prepare("DELETE FROM contracts WHERE player_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Eliminar saúde
        $stmt = $conn->prepare("DELETE FROM player_health WHERE player_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Eliminar mensalidades
        $stmt = $conn->prepare("DELETE FROM mensalidades WHERE jogador_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Eliminar jogador
        $stmt = $conn->prepare("DELETE FROM players WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Jogador eliminado com sucesso!']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// ADICIONAR EQUIPA
// ============================================
if ($_POST['action'] === 'add_team') {
    try {
        $team_name = trim($_POST['team_name']);
        $team_description = trim($_POST['team_description'] ?? '');

        if (empty($team_name)) {
            echo json_encode(['success' => false, 'message' => 'Nome da equipa é obrigatório']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO teams (nome, descricao, club_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $team_name, $team_description, $user_club_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Equipa adicionada com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao adicionar equipa']);
        }
        $stmt->close();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida']);
exit;
?>