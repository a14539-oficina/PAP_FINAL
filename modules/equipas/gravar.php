<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
  exit;
}

require('../../config/db.php');

try {
  // Verificar se as colunas necessárias existem
  $columns_query = $conn->query("SHOW COLUMNS FROM teams");
  $columns = [];
  while ($row = $columns_query->fetch_assoc()) {
    $columns[] = $row['Field'];
  }
  
  $has_nome = in_array('nome', $columns);
  $has_logo = in_array('logo', $columns);
  
  // Se não tem as colunas, criar automaticamente
  if (!$has_nome) {
    $conn->query("ALTER TABLE teams ADD COLUMN nome VARCHAR(100) NOT NULL DEFAULT '' AFTER club_id");
    $has_nome = true;
  }
  
  if (!$has_logo) {
    $conn->query("ALTER TABLE teams ADD COLUMN logo VARCHAR(255) DEFAULT NULL AFTER ativo");
    $has_logo = true;
  }
  
  // Receber dados do formulário
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $nome = trim($_POST['nome'] ?? '');
  $escaloes = trim($_POST['escaloes'] ?? '');
  $ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1;
  $current_logo = $_POST['current_logo'] ?? '';
  $remove_logo = isset($_POST['remove_logo']) ? (int)$_POST['remove_logo'] : 0;

  // Tentar receber club_id do POST, senão usar da sessão
  $club_id = isset($_POST['club_id']) ? (int)$_POST['club_id'] : 0;

  // Se não veio no POST, pegar da sessão
  if ($club_id == 0) {
    $club_id = $_SESSION['club_id'] ?? 0;
  }

  // Validações
  if ($club_id == 0) {
    throw new Exception('Selecione um clube ou faça login novamente.');
  }

  if (empty($escaloes)) {
    throw new Exception('O escalão é obrigatório');
  }

  // CORREÇÃO CRÍTICA: Só gerar nome automático se for uma NOVA equipa E o nome estiver vazio
  if ($id == 0 && empty($nome)) {
    // Tentar buscar nome do clube
    $club_name = '';
    $possible_columns = ['nome', 'name', 'club_name', 'nome_clube'];
    
    foreach ($possible_columns as $col) {
      $club_result = $conn->query("SELECT $col FROM clubs WHERE id = $club_id");
      if ($club_result && $club_result->num_rows > 0) {
        $club = $club_result->fetch_assoc();
        $club_name = $club[$col];
        break;
      }
    }
    
    // Gerar nome
    if (!empty($club_name)) {
      $nome = $club_name . ' - ' . $escaloes;
    } else {
      $nome = $escaloes;
    }
  } elseif ($id > 0 && empty($nome)) {
    // Se for edição e nome vier vazio, buscar o nome atual da BD
    $current_team = $conn->query("SELECT nome FROM teams WHERE id = $id");
    if ($current_team && $current_team->num_rows > 0) {
      $team_data = $current_team->fetch_assoc();
      $nome = $team_data['nome'];
    } else {
      throw new Exception('Equipa não encontrada');
    }
  }
  
  // Processar logo - CORREÇÃO: Manter logo atual se não houver mudanças
  $logo_filename = $current_logo; // Por padrão, mantém o logo atual
  
  // Se foi marcado para remover o logo
  if ($remove_logo == 1) {
    // Remover arquivo antigo se existir
    if (!empty($current_logo) && file_exists("logos/" . $current_logo)) {
      unlink("logos/" . $current_logo);
    }
    $logo_filename = null; // Define como NULL para remover da BD
  }
  // Se foi enviado um novo arquivo
  elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['logo'];
    
    // Validar tipo de arquivo
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
      throw new Exception('Formato de imagem inválido. Use PNG, JPG, GIF ou WEBP.');
    }
    
    // Validar tamanho (5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
      throw new Exception('A imagem não pode ter mais de 5MB');
    }
    
    // Criar diretório se não existir
    if (!is_dir('logos')) {
      mkdir('logos', 0755, true);
    }
    
    // Remover logo antigo se existir
    if (!empty($current_logo) && file_exists("logos/" . $current_logo)) {
      unlink("logos/" . $current_logo);
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $logo_filename = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
    
    // Mover arquivo
    if (!move_uploaded_file($file['tmp_name'], 'logos/' . $logo_filename)) {
      throw new Exception('Erro ao fazer upload da imagem');
    }
  }
  
  // Preparar query
  if ($id > 0) {
    // Update - Atualizar equipa existente
    $stmt = $conn->prepare("UPDATE teams SET club_id = ?, nome = ?, escaloes = ?, ativo = ?, logo = ? WHERE id = ?");
    $stmt->bind_param("issisi", $club_id, $nome, $escaloes, $ativo, $logo_filename, $id);
    $action = 'atualizada';
  } else {
    // Insert - Criar nova equipa
    $stmt = $conn->prepare("INSERT INTO teams (club_id, nome, escaloes, ativo, logo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $club_id, $nome, $escaloes, $ativo, $logo_filename);
    $action = 'criada';
  }
  
  if (!$stmt->execute()) {
    throw new Exception('Erro ao guardar equipa: ' . $stmt->error);
  }
  
  $affected_rows = $stmt->affected_rows;
  $new_id = $id > 0 ? $id : $conn->insert_id;
  $stmt->close();
  
  // Verificar se a equipa foi realmente guardada
  $verify = $conn->query("SELECT t.*, c.nome as clube_nome FROM teams t LEFT JOIN clubs c ON t.club_id = c.id WHERE t.id = $new_id");
  if ($verify && $verify->num_rows > 0) {
    $saved_team = $verify->fetch_assoc();
    error_log("✅ Equipa guardada: " . json_encode($saved_team));
  } else {
    error_log("❌ ERRO: Equipa não encontrada após guardar! ID: $new_id");
  }
  
  // Log detalhado do que foi recebido
  error_log("📝 POST recebido: " . json_encode($_POST));
  error_log("📝 Valores processados - ID: $id, Nome: $nome, Escalões: $escaloes, Club_ID: $club_id, Ativo: $ativo, Logo: $logo_filename");
  
  echo json_encode([
    'success' => true,
    'message' => "Equipa {$action} com sucesso!",
    'team_id' => $new_id,
    'affected_rows' => $affected_rows,
    'debug' => [
      'id' => $new_id,
      'nome' => $nome,
      'escaloes' => $escaloes,
      'club_id' => $club_id,
      'ativo' => $ativo,
      'logo' => $logo_filename
    ]
  ]);

} catch (Exception $e) {
  // Se houver erro e foi feito upload, remover o arquivo
  if (isset($logo_filename) && !empty($logo_filename) && $logo_filename !== $current_logo) {
    if (file_exists('logos/' . $logo_filename)) {
      unlink('logos/' . $logo_filename);
    }
  }
  
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}
?>