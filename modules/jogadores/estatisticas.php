<?php
// modules/jogadores/gravar.php
session_start();
require('../../config/db.php');

$action = $_POST['action'] ?? '';

function n($v){ return $v === '' ? null : $v; }

switch ($action) {

  case 'save_player': {
    $id = (int)($_POST['id'] ?? 0);
    $sql = $id>0
      ? "UPDATE players SET primeiro_nome=?, ultimo_nome=?, data_nascimento=?, altura_cm=?, peso_kg=?, pe_dominante=?, position_id=?, foto=?, ativo=? WHERE id=?"
      : "INSERT INTO players (primeiro_nome, ultimo_nome, data_nascimento, altura_cm, peso_kg, pe_dominante, position_id, foto, ativo) VALUES (?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $altura = n($_POST['altura_cm'] ?? null);
    $peso   = n($_POST['peso_kg'] ?? null);
    $posId  = n($_POST['position_id'] ?? null);
    $ativo  = (int)($_POST['ativo'] ?? 1);
    if ($id>0) {
      $stmt->bind_param(
        "sssddssiii",
        $_POST['primeiro_nome'], $_POST['ultimo_nome'], $_POST['data_nascimento'],
        $altura, $peso, $_POST['pe_dominante'], $posId, $_POST['foto'], $ativo, $id
      );
    } else {
      $stmt->bind_param(
        "sssddssii",
        $_POST['primeiro_nome'], $_POST['ultimo_nome'], $_POST['data_nascimento'],
        $altura, $peso, $_POST['pe_dominante'], $posId, $_POST['foto'], $ativo
      );
    }
    $stmt->execute();
    $pid = $id ?: $conn->insert_id;
    header("Location: editar.php?id=".$pid);
    exit;
  }

  case 'save_contract': {
    $player_id = (int)$_POST['player_id'];
    $season_id = (int)$_POST['season_id'];
    // upsert
    $exists = $conn->prepare("SELECT id FROM player_contracts WHERE player_id=? AND season_id=?");
    $exists->bind_param("ii", $player_id, $season_id);
    $exists->execute();
    $row = $exists->get_result()->fetch_assoc();

    if ($row) {
      $stmt = $conn->prepare("UPDATE player_contracts SET start_date=?, end_date=?, wage_monthly=?, clauses=? WHERE id=?");
      $wage = n($_POST['wage_monthly'] ?? null);
      $stmt->bind_param("ssdsi", $_POST['start_date'], $_POST['end_date'], $wage, $_POST['clauses'], $row['id']);
    } else {
      $stmt = $conn->prepare("INSERT INTO player_contracts (player_id, season_id, start_date, end_date, wage_monthly, clauses) VALUES (?,?,?,?,?,?)");
      $wage = n($_POST['wage_monthly'] ?? null);
      $stmt->bind_param("iissds", $player_id, $season_id, $_POST['start_date'], $_POST['end_date'], $wage, $_POST['clauses']);
    }
    $stmt->execute();
    header("Location: editar.php?id=".$player_id);
    exit;
  }

  case 'save_medical': {
    $player_id = (int)$_POST['player_id'];
    $season_id = (int)$_POST['season_id'];
    $exists = $conn->prepare("SELECT id FROM player_medicals WHERE player_id=? AND season_id=?");
    $exists->bind_param("ii", $player_id, $season_id);
    $exists->execute();
    $row = $exists->get_result()->fetch_assoc();

    if ($row) {
      $stmt = $conn->prepare("UPDATE player_medicals SET exam_date=?, status=?, notes=? WHERE id=?");
      $stmt->bind_param("sssi", $_POST['exam_date'], $_POST['status'], $_POST['notes'], $row['id']);
    } else {
      $stmt = $conn->prepare("INSERT INTO player_medicals (player_id, season_id, exam_date, status, notes) VALUES (?,?,?,?,?)");
      $stmt->bind_param("iisss", $player_id, $season_id, $_POST['exam_date'], $_POST['status'], $_POST['notes']);
    }
    $stmt->execute();
    header("Location: editar.php?id=".$player_id);
    exit;
  }

  case 'add_injury': {
    $player_id = (int)$_POST['player_id'];
    $stmt = $conn->prepare("INSERT INTO injuries (player_id, injury_date, type, severity, expected_return_date, notes) VALUES (?,?,?,?,?,?)");
    $ret = n($_POST['expected_return_date'] ?? null);
    $stmt->bind_param("isssss", $player_id, $_POST['injury_date'], $_POST['type'], $_POST['severity'], $ret, $_POST['notes']);
    $stmt->execute();
    header("Location: editar.php?id=".$player_id);
    exit;
  }

  case 'mark_attendance': {
    $player_id = (int)$_POST['player_id'];
    $training_id = (int)$_POST['training_id'];
    // upsert presença
    $sql = "INSERT INTO training_attendance (training_id, player_id, presenca)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE presenca=VALUES(presenca)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $training_id, $player_id, $_POST['presenca']);
    $stmt->execute();
    header("Location: editar.php?id=".$player_id);
    exit;
  }

  default:
    http_response_code(400);
    echo "Ação inválida.";
}
