<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

error_log("=== DEBUG ===");
error_log("POST ID: " . ($_POST['id'] ?? 'nenhum'));
error_log("POST completo: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

header('Content-Type: application/json');
session_start();
require('../../config/db.php');
$logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload_debug.txt';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

debugLog("========== INÍCIO DEBUG ==========");
debugLog("Action: " . ($_POST['action'] ?? 'nenhuma'));
debugLog("POST ID: " . ($_POST['id'] ?? 'nenhum'));
debugLog("POST completo: " . print_r($_POST, true));
debugLog("FILES recebidos: " . print_r($_FILES, true));

if (isset($_FILES['photo'])) {
    debugLog("✅ Arquivo 'photo' existe em FILES");
    debugLog("Nome: " . $_FILES['photo']['name']);
    debugLog("Tipo: " . $_FILES['photo']['type']);
    debugLog("Tamanho: " . $_FILES['photo']['size'] . " bytes");
    debugLog("Erro: " . $_FILES['photo']['error']);
    debugLog("Temp: " . $_FILES['photo']['tmp_name']);
    
    if (file_exists($_FILES['photo']['tmp_name'])) {
        debugLog("✅ Arquivo temporário existe");
    } else {
        debugLog("❌ Arquivo temporário NÃO existe");
    }
} else {
    debugLog("❌ Arquivo 'photo' NÃO está em FILES");
    debugLog("Keys em FILES: " . implode(', ', array_keys($_FILES)));
}

debugLog("Upload max size: " . ini_get('upload_max_filesize'));
debugLog("Post max size: " . ini_get('post_max_size'));
debugLog("Memory limit: " . ini_get('memory_limit'));
debugLog("========== FIM DEBUG ==========\n");

$action = $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(["success" => false, "message" => "Ação não recebida"]);
    exit;
}

/*
===========================================================
  SAVE PLAYER
===========================================================
*/
if ($action === "save_player") {

    // 🔥 RECOLHER TODOS OS DADOS DO POST
    $id = intval($_POST['id'] ?? 0);
    $primeiro = trim($_POST['primeiro_nome'] ?? '');
    $ultimo = trim($_POST['ultimo_nome'] ?? '');
    $nascimento = $_POST['data_nascimento'] ?? null;
    $altura = floatval($_POST['altura_cm'] ?? 0);
    $peso = floatval($_POST['peso_kg'] ?? 0);
    $pe = $_POST['pe_dominante'] ?? 'D';
    $ativo = intval($_POST['ativo'] ?? 1);
    $team_id = intval($_POST['team_id'] ?? 0) ?: null;

// 🔥 Clube vem SEMPRE da sessão
$club_id = $_SESSION['club_id'] ?? null;
if (!$club_id) {
    echo json_encode(['success' => false, 'message' => 'Clube não associado à sessão']);
    exit;
}

// Se o jogador é novo e não foi escolhida equipa, aplicar automaticamente a primeira equipa do clube
if ($id === 0 && !$team_id) {
    $stmtTeam = $conn->prepare("SELECT id FROM teams WHERE club_id = ? ORDER BY id ASC LIMIT 1");
    $stmtTeam->bind_param("i", $club_id);
    $stmtTeam->execute();
    $teamRow = $stmtTeam->get_result()->fetch_assoc();

    if ($teamRow) {
        $team_id = (int)$teamRow['id'];
    }
}

$position_id = $_POST['position_id'] ?? null;

    
    debugLog("📋 Dados recebidos:");
    debugLog("ID: $id");
    debugLog("Nome: $primeiro $ultimo");
    debugLog("Posição: $position_id");
    debugLog("Equipa: $team_id");

    // Validações básicas
    if (empty($primeiro) || empty($ultimo)) {
        echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório']);
        exit;
    }

    if (empty($position_id)) {
        echo json_encode(['success' => false, 'message' => 'Posição é obrigatória']);
        exit;
    }

    // Buscar foto antiga se for edição
    $fotoAntiga = null;
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT foto FROM players WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $player = $result->fetch_assoc();
            $fotoAntiga = $player['foto'] ?? null;
        }
        debugLog("Foto antiga: " . ($fotoAntiga ?? 'nenhuma'));
    }

    // 🔥 PROCESSAR UPLOAD DA FOTO
    $photoPath = null;
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        debugLog("🔄 Iniciando upload da foto...");
        
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/logos/';
        
        // Criar diretório se não existir
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            debugLog("📁 Diretório criado: $uploadDir");
        }
        
        $fileInfo = pathinfo($_FILES['photo']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Validar extensão
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExts)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagem inválido']);
            exit;
        }
        
        // Validar tamanho (5MB)
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Imagem muito grande (máx: 5MB)']);
            exit;
        }
        
        // Nome único para o arquivo
        $newFileName = 'player_' . time() . '_' . uniqid() . '.' . $extension;
        $uploadPath = $uploadDir . $newFileName;
        
        debugLog("Tentando mover de: " . $_FILES['photo']['tmp_name']);
        debugLog("Para: $uploadPath");
        
        // Mover arquivo
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            $photoPath = '/logos/' . $newFileName;
            debugLog("✅ Foto guardada com sucesso: $photoPath");
            
            // Apagar foto antiga se existir e não for a default
            if ($fotoAntiga && strpos($fotoAntiga, 'default-player.png') === false) {
                $oldFile = $_SERVER['DOCUMENT_ROOT'] . $fotoAntiga;
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                    debugLog("🗑️ Foto antiga apagada: $oldFile");
                }
            }
        } else {
            debugLog("❌ ERRO ao mover ficheiro!");
            debugLog("Permissões do diretório: " . substr(sprintf('%o', fileperms($uploadDir)), -4));
            echo json_encode(['success' => false, 'message' => 'Erro ao fazer upload da foto']);
            exit;
        }
    } else {
        if (isset($_FILES['photo'])) {
            debugLog("⚠️ Foto com erro: " . $_FILES['photo']['error']);
        } else {
            debugLog("ℹ️ Nenhuma foto enviada");
        }
    }

    /*
    -------------------------------------------------------
      NOVO JOGADOR (INSERT)
    -------------------------------------------------------
    */
    if ($id === 0) {
        debugLog("➕ Criando novo jogador...");

        // Se não há foto nova, usar default
        $fotoFinal = $photoPath ?? '/logos/default-player.png';

        $stmt = $conn->prepare("
            INSERT INTO players
(primeiro_nome, ultimo_nome, data_nascimento, altura_cm, peso_kg, pe_dominante, ativo, team_id, position_id, foto, club_id)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

        ");

       $stmt->bind_param(
    "sssddsiissi", 
    $primeiro,
    $ultimo,
    $nascimento,
    $altura,
    $peso,
    $pe,
    $ativo,
    $team_id,
    $position_id,
    $fotoFinal,
    $club_id
);


        if ($stmt->execute()) {
            $newPlayerId = $conn->insert_id;
            debugLog("✅ Jogador criado com ID: $newPlayerId");
            
            // 🔥 PROCESSAR DADOS MÉDICOS
            if (!empty($_POST['exam_date'])) {
                $exam_date = $_POST['exam_date'];
                $health_status = $_POST['health_status'] ?? 'Apto';
                $health_notes = $_POST['health_notes'] ?? '';
                
                $stmtHealth = $conn->prepare("INSERT INTO player_health (player_id, exam_date, health_status, health_notes) VALUES (?, ?, ?, ?)");
                $stmtHealth->bind_param("isss", $newPlayerId, $exam_date, $health_status, $health_notes);
                $stmtHealth->execute();
                debugLog("✅ Dados médicos inseridos");
            }
            
            // 🔥 PROCESSAR CONTRATO
            if (isset($_POST['has_contract']) && $_POST['has_contract'] === '1') {
                $wage = floatval($_POST['wage_monthly'] ?? 0);
                $contract_season = intval($_POST['contract_season_id'] ?? 0);
                $clauses = $_POST['contract_clauses'] ?? '';
                
                if ($contract_season > 0) {
                    $stmtContract = $conn->prepare("INSERT INTO contracts (player_id, season_id, wage_monthly, clauses, start_date, end_date) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))");
                    $stmtContract->bind_param("iids", $newPlayerId, $contract_season, $wage, $clauses);
                    $stmtContract->execute();
                    debugLog("✅ Contrato inserido");
                }
            }
            
            // 🔥 PROCESSAR MENSALIDADES - CRIAR PARA TODOS OS MESES DA ÉPOCA
            if (isset($_POST['has_mensalidade']) && $_POST['has_mensalidade'] === '1') {
                $mensalidade_valor = floatval($_POST['mensalidade_valor'] ?? 0);
                $mensalidade_epoca = intval($_POST['mensalidade_epoca'] ?? 0);
                $mensalidade_metodo = $_POST['mensalidade_metodo_pagamento'] ?? '';
                $mensalidade_obs = $_POST['mensalidade_observacoes'] ?? '';
                
                debugLog("💰 Processando mensalidades...");
                debugLog("Valor: $mensalidade_valor");
                debugLog("Época ID: $mensalidade_epoca");
                
                if ($mensalidade_epoca > 0 && $mensalidade_valor > 0) {
                    // Buscar datas da época
                    $stmtSeason = $conn->prepare("SELECT data_inicio, data_fim FROM seasons WHERE id = ?");
                    $stmtSeason->bind_param("i", $mensalidade_epoca);
                    $stmtSeason->execute();
                    $resultSeason = $stmtSeason->get_result();
                    
                    if ($resultSeason->num_rows > 0) {
                        $season = $resultSeason->fetch_assoc();
                        $dataInicio = new DateTime($season['data_inicio']);
                        $dataFim = new DateTime($season['data_fim']);
                        
                        debugLog("📅 Época: " . $dataInicio->format('Y-m-d') . " até " . $dataFim->format('Y-m-d'));
                        
                        // Criar mensalidade para cada mês da época
                        $dataAtual = clone $dataInicio;
                        
                        while ($dataAtual <= $dataFim) {
                            $ano = (int)$dataAtual->format('Y');
                            $mes = (int)$dataAtual->format('m');
                            $mesReferencia = $dataAtual->format('Y-m-01'); // Primeiro dia do mês
                            $dataVencimento = $dataAtual->format('Y-m-05'); // Vencimento dia 5 de cada mês
                            
                            debugLog("📝 Criando mensalidade: $mesReferencia (Ano: $ano, Mês: $mes)");
                            
                            // Verificar se já existe mensalidade para este mês/ano
                            $checkMensal = $conn->prepare("SELECT id FROM mensalidades WHERE jogador_id = ? AND ano = ? AND mes = ? AND season_id = ?");
                            $checkMensal->bind_param("iiii", $newPlayerId, $ano, $mes, $mensalidade_epoca);
                            $checkMensal->execute();
                            $resultCheck = $checkMensal->get_result();
                            
                            if ($resultCheck->num_rows === 0) {
                                $stmtMensal = $conn->prepare("
                                    INSERT INTO mensalidades 
                                    (jogador_id, season_id, mes_referencia, ano, mes, valor, data_vencimento, status, metodo_pagamento, observacoes) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?)
                                ");
                                $stmtMensal->bind_param(
                                    "iisiidsss", 
                                    $newPlayerId, 
                                    $mensalidade_epoca, 
                                    $mesReferencia,
                                    $ano,
                                    $mes,
                                    $mensalidade_valor, 
                                    $dataVencimento, 
                                    $mensalidade_metodo, 
                                    $mensalidade_obs
                                );
                                $stmtMensal->execute();
                                debugLog("✅ Mensalidade criada para $mesReferencia");
                            } else {
                                debugLog("⚠️ Mensalidade já existe para $mesReferencia");
                            }
                            
                            // Avançar para o próximo mês
                            $dataAtual->modify('+1 month');
                        }
                        
                        debugLog("✅ Todas as mensalidades criadas");
                    } else {
                        debugLog("❌ Época não encontrada");
                    }
                }
            }
            
            echo json_encode(["success" => true, "message" => "Jogador criado com sucesso"]);
        } else {
            debugLog("❌ Erro SQL: " . $stmt->error);
            echo json_encode(["success" => false, "message" => "Erro ao criar jogador: " . $stmt->error]);
        }
        exit;
    }

    /*
    -------------------------------------------------------
      ATUALIZAR JOGADOR (UPDATE)
    -------------------------------------------------------
    */
    debugLog("🔄 Atualizando jogador ID: $id");

    // Se há foto nova, atualiza com ela
    // Se há foto nova, atualiza com ela
if ($photoPath) {
    $stmt = $conn->prepare("
        UPDATE players SET 
            primeiro_nome = ?, 
            ultimo_nome = ?, 
            data_nascimento = ?, 
            altura_cm = ?, 
            peso_kg = ?, 
            pe_dominante = ?, 
            ativo = ?, 
            team_id = ?, 
            position_id = ?,
            foto = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssddsiissi",
        $primeiro,
        $ultimo,
        $nascimento,
        $altura,
        $peso,
        $pe,
        $ativo,
        $team_id,
        $position_id,
        $photoPath,
        $id
    );
} else {
    // Sem foto nova, não atualiza o campo foto (nem clube)
    $stmt = $conn->prepare("
        UPDATE players SET 
            primeiro_nome = ?, 
            ultimo_nome = ?, 
            data_nascimento = ?, 
            altura_cm = ?, 
            peso_kg = ?, 
            pe_dominante = ?, 
            ativo = ?, 
            team_id = ?, 
            position_id = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssddsiisi",
        $primeiro,
        $ultimo,
        $nascimento,
        $altura,
        $peso,
        $pe,
        $ativo,
        $team_id,
        $position_id,
        $id
    );
}


    if ($stmt->execute()) {
        debugLog("✅ Jogador atualizado");
        
        // 🔥 ATUALIZAR/INSERIR DADOS MÉDICOS
        if (!empty($_POST['exam_date'])) {
            $exam_date = $_POST['exam_date'];
            $health_status = $_POST['health_status'] ?? 'Apto';
            $health_notes = $_POST['health_notes'] ?? '';
            
            // Verificar se já existe
            $checkHealth = $conn->prepare("SELECT id FROM player_health WHERE player_id = ? ORDER BY exam_date DESC LIMIT 1");
            $checkHealth->bind_param("i", $id);
            $checkHealth->execute();
            $resultHealth = $checkHealth->get_result();
            
            if ($resultHealth->num_rows > 0) {
                $healthRow = $resultHealth->fetch_assoc();
                $stmtHealth = $conn->prepare("UPDATE player_health SET exam_date = ?, health_status = ?, health_notes = ? WHERE id = ?");
                $stmtHealth->bind_param("sssi", $exam_date, $health_status, $health_notes, $healthRow['id']);
            } else {
                $stmtHealth = $conn->prepare("INSERT INTO player_health (player_id, exam_date, health_status, health_notes) VALUES (?, ?, ?, ?)");
                $stmtHealth->bind_param("isss", $id, $exam_date, $health_status, $health_notes);
            }
            $stmtHealth->execute();
            debugLog("✅ Dados médicos atualizados");
        }
        
        // 🔥 ATUALIZAR/INSERIR CONTRATO
        if (isset($_POST['has_contract']) && $_POST['has_contract'] === '1') {
            $wage = floatval($_POST['wage_monthly'] ?? 0);
            $contract_season = intval($_POST['contract_season_id'] ?? 0);
            $clauses = $_POST['contract_clauses'] ?? '';
            
            if ($contract_season > 0) {
                // Verificar se já existe contrato para esta época
                $checkContract = $conn->prepare("SELECT id FROM contracts WHERE player_id = ? AND season_id = ?");
                $checkContract->bind_param("ii", $id, $contract_season);
                $checkContract->execute();
                $resultContract = $checkContract->get_result();
                
                if ($resultContract->num_rows > 0) {
                    $contractRow = $resultContract->fetch_assoc();
                    $stmtContract = $conn->prepare("UPDATE contracts SET wage_monthly = ?, clauses = ? WHERE id = ?");
                    $stmtContract->bind_param("dsi", $wage, $clauses, $contractRow['id']);
                } else {
                    $stmtContract = $conn->prepare("INSERT INTO contracts (player_id, season_id, wage_monthly, clauses, start_date, end_date) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))");
                    $stmtContract->bind_param("iids", $id, $contract_season, $wage, $clauses);
                }
                $stmtContract->execute();
                debugLog("✅ Contrato atualizado");
            }
        }
        
        // 🔥 ATUALIZAR/CRIAR MENSALIDADES
        if (isset($_POST['has_mensalidade']) && $_POST['has_mensalidade'] === '1') {
            $mensalidade_valor = floatval($_POST['mensalidade_valor'] ?? 0);
            $mensalidade_epoca = intval($_POST['mensalidade_epoca'] ?? 0);
            $mensalidade_metodo = $_POST['mensalidade_metodo_pagamento'] ?? '';
            $mensalidade_obs = $_POST['mensalidade_observacoes'] ?? '';
            
            debugLog("💰 Atualizando mensalidades...");
            
            if ($mensalidade_epoca > 0 && $mensalidade_valor > 0) {
                // Buscar datas da época
                $stmtSeason = $conn->prepare("SELECT data_inicio, data_fim FROM seasons WHERE id = ?");
                $stmtSeason->bind_param("i", $mensalidade_epoca);
                $stmtSeason->execute();
                $resultSeason = $stmtSeason->get_result();
                
                if ($resultSeason->num_rows > 0) {
                    $season = $resultSeason->fetch_assoc();
                    $dataInicio = new DateTime($season['data_inicio']);
                    $dataFim = new DateTime($season['data_fim']);
                    
                    debugLog("📅 Época: " . $dataInicio->format('Y-m-d') . " até " . $dataFim->format('Y-m-d'));
                    
                    // Criar/atualizar mensalidade para cada mês da época
                    $dataAtual = clone $dataInicio;
                    
                    while ($dataAtual <= $dataFim) {
                        $ano = (int)$dataAtual->format('Y');
                        $mes = (int)$dataAtual->format('m');
                        $mesReferencia = $dataAtual->format('Y-m-01');
                        $dataVencimento = $dataAtual->format('Y-m-05');
                        
                        // Verificar se já existe
                        $checkMensal = $conn->prepare("SELECT id FROM mensalidades WHERE jogador_id = ? AND ano = ? AND mes = ? AND season_id = ?");
                        $checkMensal->bind_param("iiii", $id, $ano, $mes, $mensalidade_epoca);
                        $checkMensal->execute();
                        $resultCheck = $checkMensal->get_result();
                        
                        if ($resultCheck->num_rows > 0) {
                            // Atualizar existente
                            $mensalRow = $resultCheck->fetch_assoc();
                            $stmtMensal = $conn->prepare("
                                UPDATE mensalidades 
                                SET valor = ?, metodo_pagamento = ?, observacoes = ? 
                                WHERE id = ?
                            ");
                            $stmtMensal->bind_param("dssi", $mensalidade_valor, $mensalidade_metodo, $mensalidade_obs, $mensalRow['id']);
                            $stmtMensal->execute();
                            debugLog("✅ Mensalidade atualizada: $mesReferencia");
                        } else {
                            // Criar nova
                            $stmtMensal = $conn->prepare("
                                INSERT INTO mensalidades 
                                (jogador_id, season_id, mes_referencia, ano, mes, valor, data_vencimento, status, metodo_pagamento, observacoes) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?)
                            ");
                            $stmtMensal->bind_param(
                                "iisiidsss", 
                                $id, 
                                $mensalidade_epoca, 
                                $mesReferencia,
                                $ano,
                                $mes,
                                $mensalidade_valor, 
                                $dataVencimento, 
                                $mensalidade_metodo, 
                                $mensalidade_obs
                            );
                            $stmtMensal->execute();
                            debugLog("✅ Mensalidade criada: $mesReferencia");
                        }
                        
                        $dataAtual->modify('+1 month');
                    }
                    
                    debugLog("✅ Mensalidades processadas");
                }
            }
        }
        
        echo json_encode(["success" => true, "message" => "Jogador atualizado com sucesso"]);
    } else {
        debugLog("❌ Erro SQL: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Erro ao atualizar jogador: " . $stmt->error]);
    }

    exit;
}

/*
===========================================================
  DEFAULT
===========================================================
*/
echo json_encode(["success" => false, "message" => "Ação inválida"]);
exit;

?>