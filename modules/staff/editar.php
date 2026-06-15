<?php
session_start();
require('../../config/db.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$user_role = $_SESSION['user_role'] ?? '';
$user_club_id = $_SESSION['club_id'] ?? 0;
$tem_permissao = in_array($user_role, ['0', '1', '3', 'Administrador', 'Treinador']);

if (!$tem_permissao) {
    header('Location: listar.php?erro=sem_permissao');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$staff = null;
$contratos = [];
$erro = '';
$sucesso = '';

// Data atual para comparação
$hoje = date('Y-m-d');

// Buscar épocas disponíveis - ordenadas por data de início (mais recente primeiro)
// E marcar qual é a época atual baseada na DATA
$epocas_query = "SELECT *, 
                 CASE 
                     WHEN ? BETWEEN data_inicio AND data_fim THEN 1 
                     ELSE 0 
                 END as epoca_atual
                 FROM epocas 
                 ORDER BY data_inicio DESC";
$stmt_epocas = $conn->prepare($epocas_query);
$stmt_epocas->bind_param("s", $hoje);
$stmt_epocas->execute();
$epocas_result = $stmt_epocas->get_result();
$epocas = $epocas_result->fetch_all(MYSQLI_ASSOC);
$stmt_epocas->close();

// Identificar a época atual (baseada na data)
$epoca_atual_id = null;
foreach ($epocas as $ep) {
    if ($ep['epoca_atual'] == 1) {
        $epoca_atual_id = $ep['id'];
        break;
    }
}

// Se tem ID, busca o membro e seus contratos
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
    $stmt->close();
    
    if (!$staff) {
        header('Location: listar.php?erro=nao_encontrado');
        exit;
    }
    
    // Buscar contratos existentes - com verificação de época atual por DATA
    $stmt = $conn->prepare("
        SELECT c.*, e.nome as epoca_nome, e.data_inicio, e.data_fim,
               CASE 
                   WHEN ? BETWEEN e.data_inicio AND e.data_fim THEN 1 
                   ELSE 0 
               END as epoca_atual
        FROM contratos_staff c
        JOIN epocas e ON c.epoca_id = e.id
        WHERE c.staff_id = ?
        ORDER BY e.data_inicio DESC
    ");
    $stmt->bind_param("si", $hoje, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $contratos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Processar formulário - Informações Básicas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'salvar_info') {
    $nome = trim($_POST['nome'] ?? '');
    $cargo = trim($_POST['cargo_principal'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    if (empty($nome) || empty($cargo)) {
        $erro = 'Nome e cargo são obrigatórios!';
    } else {
        try {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE staff SET nome=?, cargo_principal=?, telefone=?, email=?, ativo=? WHERE id=?");
                $stmt->bind_param("ssssii", $nome, $cargo, $telefone, $email, $ativo, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO staff (nome, cargo_principal, telefone, email, ativo) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $nome, $cargo, $telefone, $email, $ativo);
            }
            
            if ($stmt->execute()) {
                if ($id == 0) {
                    $id = $conn->insert_id;
                    header('Location: editar.php?id=' . $id . '&msg=criado');
                } else {
                    $sucesso = 'Informações atualizadas com sucesso!';
                    $stmt2 = $conn->prepare("SELECT * FROM staff WHERE id = ?");
                    $stmt2->bind_param("i", $id);
                    $stmt2->execute();
                    $result = $stmt2->get_result();
                    $staff = $result->fetch_assoc();
                    $stmt2->close();
                }
            } else {
                $erro = 'Erro ao guardar: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $erro = 'Erro: ' . $e->getMessage();
        }
    }
}

// Processar formulário - Adicionar/Editar Contrato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'salvar_contrato') {
    $epoca_id = intval($_POST['epoca_id']);
    $salario = floatval($_POST['salario_mensal']);
    $bonus = !empty($_POST['bonus_anual']) ? floatval($_POST['bonus_anual']) : null;
    $notas = trim($_POST['notas'] ?? '');
    
    if ($epoca_id <= 0 || $salario <= 0) {
        $erro = 'Época e salário são obrigatórios!';
    } else {
        try {
            $stmt = $conn->prepare("SELECT id FROM contratos_staff WHERE staff_id = ? AND epoca_id = ?");
            $stmt->bind_param("ii", $id, $epoca_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $contrato_existente = $result->fetch_assoc();
            $stmt->close();
            
            if ($contrato_existente) {
                $stmt = $conn->prepare("UPDATE contratos_staff SET salario_mensal=?, bonus_anual=?, notas=? WHERE id=?");
                $stmt->bind_param("ddsi", $salario, $bonus, $notas, $contrato_existente['id']);
                $contrato_id = $contrato_existente['id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO contratos_staff (staff_id, epoca_id, salario_mensal, bonus_anual, notas) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iidds", $id, $epoca_id, $salario, $bonus, $notas);
            }
            
            if ($stmt->execute()) {
                if (!$contrato_existente) {
                    $contrato_id = $conn->insert_id;
                }
                
                gerarDespesasMensais($conn, $contrato_id, $id, $epoca_id, $salario, $staff['nome'], $user_club_id);
                
                $sucesso = 'Contrato guardado e despesas geradas com sucesso!';
                
                $stmt2 = $conn->prepare("
                    SELECT c.*, e.nome as epoca_nome, e.data_inicio, e.data_fim,
                           CASE 
                               WHEN ? BETWEEN e.data_inicio AND e.data_fim THEN 1 
                               ELSE 0 
                           END as epoca_atual
                    FROM contratos_staff c
                    JOIN epocas e ON c.epoca_id = e.id
                    WHERE c.staff_id = ?
                    ORDER BY e.data_inicio DESC
                ");
                $stmt2->bind_param("si", $hoje, $id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $contratos = $result->fetch_all(MYSQLI_ASSOC);
                $stmt2->close();
            } else {
                $erro = 'Erro ao guardar contrato: ' . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $erro = 'Erro: ' . $e->getMessage();
        }
    }
}

// Função para gerar despesas mensais
function gerarDespesasMensais($conn, $contrato_id, $staff_id, $epoca_id, $salario, $nome_staff, $club_id) {
    $stmt = $conn->prepare("SELECT data_inicio, data_fim FROM epocas WHERE id = ?");
    $stmt->bind_param("i", $epoca_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $epoca = $result->fetch_assoc();
    $stmt->close();
    
    $data_inicio = new DateTime($epoca['data_inicio']);
    $data_fim = new DateTime($epoca['data_fim']);
    
    $stmt = $conn->prepare("DELETE FROM despesas WHERE contrato_id = ?");
    $stmt->bind_param("i", $contrato_id);
    $stmt->execute();
    $stmt->close();
    
    $data_atual = clone $data_inicio;
    while ($data_atual <= $data_fim) {
        $mes = (int)$data_atual->format('n');
        $ano = (int)$data_atual->format('Y');
        $data_pagamento = $data_atual->format('Y-m-d');
        
        $descricao = "Salário - " . $nome_staff;
        $categoria = "Pessoal";
        $tipo = "Mensal";
        $estado = "Pendente";
        
        $stmt = $conn->prepare("INSERT INTO despesas (descricao, valor, categoria, data, tipo, club_id, staff_id, contrato_id, estado, mes_referencia, ano_referencia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsssiiisii", $descricao, $salario, $categoria, $data_pagamento, $tipo, $club_id, $staff_id, $contrato_id, $estado, $mes, $ano);
        $stmt->execute();
        $stmt->close();
        
        $data_atual->modify('+1 month');
    }
    
    $stmt = $conn->prepare("DELETE FROM pagamentos_staff WHERE contrato_id = ?");
    $stmt->bind_param("i", $contrato_id);
    $stmt->execute();
    $stmt->close();
}

// Função auxiliar para determinar status da época
function getEpocaStatus($data_inicio, $data_fim, $hoje) {
    if ($hoje < $data_inicio) {
        return ['status' => 'futura', 'label' => 'FUTURA', 'class' => 'status-futura'];
    } elseif ($hoje > $data_fim) {
        return ['status' => 'passada', 'label' => 'TERMINADA', 'class' => 'status-passada'];
    } else {
        return ['status' => 'atual', 'label' => 'ATUAL', 'class' => 'status-atual'];
    }
}

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id > 0 ? 'Editar' : 'Novo' ?> Membro - SportGes</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }
        .main-content {
            margin-left: 240px;
            padding: 1rem 2rem 2rem 2rem;
            width: calc(100% - 240px);
            box-sizing: border-box;
            min-height: 100vh;
        }
        .content-wrapper { max-width: 900px; margin: 0 auto; width: 100%; }
        .import-list-header {
            background: #fff;
            padding: 1.5rem 2rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .import-list-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .import-list-header h1 i { font-size: 2rem; color: #3b82f6; }
        .header-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-push-all {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .btn-push-all:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
        }
        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: 2px solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert i { font-size: 1.25rem; }
        .alert-success { background: #dcfce7; border-color: #86efac; color: #166534; }
        .alert-danger { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
        .alert-info { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
        .form-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            animation: fadeInUp 0.5s ease;
            margin-bottom: 1.5rem;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-title i { color: #3b82f6; }
        .form-group { margin-bottom: 1.5rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        label {
            display: block;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }
        input[type="text"], input[type="email"], input[type="tel"],
        input[type="number"], input[type="date"], select, textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9375rem;
            transition: all 0.2s ease;
            background: white;
            color: #334155;
            font-family: inherit;
        }
        textarea { resize: vertical; min-height: 100px; }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2364748b' d='M7 9L3 5h8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 3rem;
        }
        .currency-input { position: relative; }
        .currency-input::before {
            content: '€';
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-weight: 600;
            pointer-events: none;
        }
        .currency-input input { padding-left: 2.5rem; }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .checkbox-group:hover { border-color: #3b82f6; background: #eff6ff; }
        input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #3b82f6; }
        .checkbox-group label { margin: 0; cursor: pointer; user-select: none; font-weight: 500; }
        .help-text { font-size: 0.8125rem; color: #64748b; margin-top: 0.375rem; }
        .form-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e2e8f0;
        }
        .btn-submit {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
        }
        .btn-cancel {
            background: #e2e8f0;
            color: #475569;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-cancel:hover { background: #cbd5e1; color: #334155; }
        .btn-delete {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);
        }
        .contratos-list { display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem; }
        .contrato-item {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            transition: all 0.2s ease;
        }
        .contrato-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        .contrato-item.epoca-atual {
            border-color: #22c55e;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }
        .contrato-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .contrato-epoca {
            font-size: 1.125rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .contrato-epoca i { color: #3b82f6; }
        .contrato-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }
        .contrato-detail { display: flex; flex-direction: column; gap: 0.25rem; }
        .contrato-detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .contrato-detail-value { font-size: 1rem; font-weight: 600; color: #0f172a; }
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        /* Status badges para épocas */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-atual {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        }
        .status-futura {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        .status-passada {
            background: #94a3b8;
            color: white;
        }
        .empty-state { text-align: center; padding: 3rem 1.5rem; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
        .empty-state p { font-size: 1rem; margin-bottom: 0.5rem; }
        .empty-state small { font-size: 0.875rem; }
        /* Select option styling */
        .epoca-option-atual { background-color: #dcfce7 !important; font-weight: 700 !important; }
        .epoca-option-futura { background-color: #dbeafe !important; }
        .epoca-option-passada { background-color: #f1f5f9 !important; color: #64748b !important; }
        /* Info box para época atual */
        .epoca-info-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #22c55e;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .epoca-info-box i { font-size: 2rem; color: #16a34a; }
        .epoca-info-box .info-content h4 { font-size: 1rem; font-weight: 700; color: #166534; margin-bottom: 0.25rem; }
        .epoca-info-box .info-content p { font-size: 0.875rem; color: #15803d; margin: 0; }
        @media (max-width: 1200px) {
            .main-content { margin-left: 0; width: 100%; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .import-list-header { padding: 1.25rem; flex-direction: column; align-items: stretch; }
            .import-list-header h1 { font-size: 1.5rem; }
            .header-actions { width: 100%; flex-direction: column; }
            .btn-push-all { width: 100%; justify-content: center; }
            .form-card { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .form-actions { grid-template-columns: 1fr; }
            .btn-delete { grid-column: 1; }
            .contrato-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
    <?php include('../../includes/role_helper.php'); ?>

    <div class="content-wrapper">
        <div class="import-list-header">
            <h1>
                <i class="bi bi-<?= $id > 0 ? 'pencil-square' : 'person-plus-fill' ?>"></i>
                <?= $id > 0 ? 'Editar Membro' : 'Novo Membro' ?>
            </h1>
            <div class="header-actions">
                <a href="listar.php" class="btn-push-all">
                    <i class="bi bi-arrow-left"></i>
                    Voltar
                </a>
            </div>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php 
        // Mostrar info da época atual
        $epoca_atual_info = null;
        foreach ($epocas as $ep) {
            if ($ep['epoca_atual'] == 1) {
                $epoca_atual_info = $ep;
                break;
            }
        }
        if ($epoca_atual_info): 
        ?>
            <div class="epoca-info-box">
                <i class="bi bi-calendar-check-fill"></i>
                <div class="info-content">
                    <h4>Época Atual: <?= htmlspecialchars($epoca_atual_info['nome']) ?></h4>
                    <p><?= date('d/m/Y', strtotime($epoca_atual_info['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($epoca_atual_info['data_fim'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Informações Básicas -->
        <form method="POST" action="">
            <input type="hidden" name="action" value="salvar_info">
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-person-badge"></i>
                    Informações Básicas
                </div>

                <div class="form-group">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" 
                           value="<?= htmlspecialchars($staff['nome'] ?? '') ?>" 
                           placeholder="Digite o nome completo"
                           required>
                </div>

                <div class="form-group">
                    <label for="cargo_principal">Cargo Principal *</label>
                    <select id="cargo_principal" name="cargo_principal" required>
                        <option value="">Selecione um cargo</option>
                        <option value="Treinador" <?= ($staff['cargo_principal'] ?? '') === 'Treinador' ? 'selected' : '' ?>>Treinador</option>
                        <option value="Adjunto" <?= ($staff['cargo_principal'] ?? '') === 'Adjunto' ? 'selected' : '' ?>>Adjunto</option>
                        <option value="Fisioterapeuta" <?= ($staff['cargo_principal'] ?? '') === 'Fisioterapeuta' ? 'selected' : '' ?>>Fisioterapeuta</option>
                        <option value="Diretor" <?= ($staff['cargo_principal'] ?? '') === 'Diretor' ? 'selected' : '' ?>>Diretor</option>
                        <option value="Outro" <?= ($staff['cargo_principal'] ?? '') === 'Outro' ? 'selected' : '' ?>>Outro</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" 
                               value="<?= htmlspecialchars($staff['telefone'] ?? '') ?>" 
                               placeholder="+351 912 345 678">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($staff['email'] ?? '') ?>" 
                               placeholder="exemplo@email.com">
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="ativo" name="ativo" 
                               <?= ($staff['ativo'] ?? 1) ? 'checked' : '' ?>>
                        <label for="ativo">Membro Ativo</label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-check-circle-fill"></i>
                        <?= $id > 0 ? 'Atualizar' : 'Criar Membro' ?>
                    </button>
                    <a href="listar.php" class="btn-cancel">
                        <i class="bi bi-x-circle-fill"></i>
                        Cancelar
                    </a>
                    <?php if ($id > 0): ?>
                        <button type="button" class="btn-delete" 
                                onclick="confirmarEliminacao(<?= $id ?>, '<?= htmlspecialchars($staff['nome'], ENT_QUOTES) ?>')">
                            <i class="bi bi-trash-fill"></i>
                            Eliminar Membro
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if ($id > 0): ?>
        <!-- Contratos por Época -->
        <div class="form-card">
            <div class="section-title">
                <i class="bi bi-file-earmark-text"></i>
                Contratos por Época
            </div>

            <!-- Lista de Contratos Existentes -->
            <?php if (count($contratos) > 0): ?>
                <div class="contratos-list">
                    <?php foreach ($contratos as $contrato): 
                        $statusEpoca = getEpocaStatus($contrato['data_inicio'], $contrato['data_fim'], $hoje);
                    ?>
                        <div class="contrato-item <?= $contrato['epoca_atual'] == 1 ? 'epoca-atual' : '' ?>">
                            <div class="contrato-header">
                                <div class="contrato-epoca">
                                    <i class="bi bi-calendar-check"></i>
                                    <?= htmlspecialchars($contrato['epoca_nome']) ?>
                                    <span class="status-badge <?= $statusEpoca['class'] ?>">
                                        <?= $statusEpoca['label'] ?>
                                    </span>
                                </div>
                                <span class="badge-info">
                                    <i class="bi bi-calendar-range"></i>
                                    <?= date('d/m/Y', strtotime($contrato['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($contrato['data_fim'])) ?>
                                </span>
                            </div>
                            <div class="contrato-details">
                                <div class="contrato-detail">
                                    <span class="contrato-detail-label">Salário Mensal</span>
                                    <span class="contrato-detail-value">€ <?= number_format($contrato['salario_mensal'], 2, ',', '.') ?></span>
                                </div>
                                <?php if ($contrato['bonus_anual']): ?>
                                <div class="contrato-detail">
                                    <span class="contrato-detail-label">Bónus Anual</span>
                                    <span class="contrato-detail-value">€ <?= number_format($contrato['bonus_anual'], 2, ',', '.') ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="contrato-detail">
                                    <span class="contrato-detail-label">Total Época</span>
                                    <span class="contrato-detail-value" style="color: #059669;">
                                        € <?= number_format($contrato['salario_mensal'] * 12 + ($contrato['bonus_anual'] ?? 0), 2, ',', '.') ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($contrato['notas']): ?>
                                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e2e8f0;">
                                    <span class="contrato-detail-label">Notas</span>
                                    <p style="margin-top: 0.25rem; color: #475569; font-size: 0.875rem;">
                                        <?= nl2br(htmlspecialchars($contrato['notas'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Nenhum contrato registado</p>
                    <small>Adicione o primeiro contrato abaixo</small>
                </div>
            <?php endif; ?>

            <!-- Formulário para Novo/Editar Contrato -->
            <form method="POST" action="" style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e2e8f0;">
                <input type="hidden" name="action" value="salvar_contrato">
                
                <h3 style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="bi bi-plus-circle" style="color: #3b82f6;"></i>
                    Adicionar/Editar Contrato
                </h3>

                <div class="form-group">
                    <label for="epoca_id">Época *</label>
                    <select id="epoca_id" name="epoca_id" required>
                        <option value="">Selecione uma época</option>
                        <?php foreach ($epocas as $epoca): 
                            $statusEpoca = getEpocaStatus($epoca['data_inicio'], $epoca['data_fim'], $hoje);
                            $optionClass = '';
                            if ($statusEpoca['status'] === 'atual') $optionClass = 'epoca-option-atual';
                            elseif ($statusEpoca['status'] === 'futura') $optionClass = 'epoca-option-futura';
                            else $optionClass = 'epoca-option-passada';
                        ?>
                            <option value="<?= $epoca['id'] ?>" 
                                    class="<?= $optionClass ?>"
                                    <?= $epoca['epoca_atual'] == 1 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($epoca['nome']) ?>
                                (<?= date('d/m/Y', strtotime($epoca['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($epoca['data_fim'])) ?>)
                                [<?= $statusEpoca['label'] ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">
                        <i class="bi bi-info-circle"></i>
                        A época é determinada automaticamente pela data atual. O contrato será aplicado a todos os meses desta época.
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="salario_mensal">Salário Mensal *</label>
                        <div class="currency-input">
                            <input type="number" id="salario_mensal" name="salario_mensal" 
                                   step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="help-text">Valor pago mensalmente durante a época</div>
                    </div>
                    <div class="form-group">
                        <label for="bonus_anual">Bónus Anual (Opcional)</label>
                        <div class="currency-input">
                            <input type="number" id="bonus_anual" name="bonus_anual" 
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="help-text">Bónus único pago no final da época</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notas">Notas sobre o Contrato</label>
                    <textarea id="notas" name="notas" 
                              placeholder="Adicione observações sobre condições especiais, objetivos, etc."></textarea>
                </div>

                <button type="submit" class="btn-submit" style="width: 100%;">
                    <i class="bi bi-check-circle-fill"></i>
                    Guardar Contrato e Gerar Despesas
                </button>
            </form>
        </div>
        
        <?php else: ?>
        <div class="form-card">
            <div class="empty-state">
                <i class="bi bi-info-circle"></i>
                <p style="font-weight: 600;">Guarde primeiro as informações básicas</p>
                <small>Após criar o membro, poderá adicionar contratos por época</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmarEliminacao(id, nome) {
    if (confirm('Tem a certeza que deseja eliminar "' + nome + '"?\n\nEsta ação eliminará também todos os contratos, despesas e pagamentos associados e não pode ser revertida.')) {
        window.location.href = 'eliminar.php?id=' + id;
    }
}

document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'opacity 0.3s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});

document.addEventListener('DOMContentLoaded', function() {
    const salarioInput = document.getElementById('salario_mensal');
    const bonusInput = document.getElementById('bonus_anual');
    
    if (salarioInput && bonusInput) {
        function calcularTotal() {
            const salario = parseFloat(salarioInput.value) || 0;
            const bonus = parseFloat(bonusInput.value) || 0;
            const total = (salario * 12) + bonus;
            
            let preview = document.getElementById('total-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.id = 'total-preview';
                // Insere depois do form-row que contém os campos de salário e bónus
                bonusInput.closest('.form-row').after(preview);
            }
            
            if (salario > 0) {
                preview.innerHTML = `
                    <span style="font-size: 0.875rem; color: #0369a1; font-weight: 600;">
                        💰 Total da Época: <strong style="font-size: 1.25rem; color: #0c4a6e;">€ ${total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}</strong>
                    </span>
                    <br>
                    <small style="color: #0369a1; margin-top: 0.25rem; display: block;">
                        (12 meses × €${salario.toFixed(2)} ${bonus > 0 ? '+ €' + bonus.toFixed(2) + ' bónus' : ''})
                    </small>
                    <br>
                    <small style="color: #059669; margin-top: 0.5rem; display: block; font-weight: 600;">
                        ✓ Serão criadas despesas mensais automaticamente
                    </small>
                `;
            } else {
                preview.innerHTML = '';
            }
        }
        
        salarioInput.addEventListener('input', calcularTotal);
        bonusInput.addEventListener('input', calcularTotal);
        calcularTotal();
    }
});
</script>

</body>
</html>