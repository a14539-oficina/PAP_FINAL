<?php
session_start();
require('../../config/db.php');

$user_role = $_SESSION['user_role'] ?? '';
$user_club_id = $_SESSION['club_id'] ?? 0;
$tem_permissao = in_array($user_role, ['0', '1', '3', 'Administrador', 'Treinador']);

if (!$tem_permissao) {
    header('Location: listar.php?erro=sem_permissao');
    exit;
}

$erro = '';
$sucesso = '';
$hoje = date('Y-m-d');

// Buscar épocas disponíveis
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

$epoca_atual_info = null;
foreach ($epocas as $ep) {
    if ($ep['epoca_atual'] == 1) {
        $epoca_atual_info = $ep;
        break;
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
    $data_fim    = new DateTime($epoca['data_fim']);

    $stmt = $conn->prepare("DELETE FROM despesas WHERE contrato_id = ?");
    $stmt->bind_param("i", $contrato_id);
    $stmt->execute();
    $stmt->close();

    $data_atual = clone $data_inicio;
    while ($data_atual <= $data_fim) {
        $mes  = (int)$data_atual->format('n');
        $ano  = (int)$data_atual->format('Y');
        $data_pagamento = $data_atual->format('Y-m-d');
        $descricao = "Salário - " . $nome_staff;
        $categoria = "Pessoal";
        $tipo      = "Mensal";
        $estado    = "Pendente";
        error_log("STAFF ID: " . $novo_id);
        error_log("CLUB ID USADO: " . $user_club_id);
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

function getEpocaStatus($data_inicio, $data_fim, $hoje) {
    if ($hoje < $data_inicio) return ['status' => 'futura',  'label' => 'FUTURA',    'class' => 'status-futura'];
    if ($hoje > $data_fim)    return ['status' => 'passada', 'label' => 'TERMINADA', 'class' => 'status-passada'];
    return                           ['status' => 'atual',   'label' => 'ATUAL',     'class' => 'status-atual'];
}

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $cargo    = trim($_POST['cargo_principal'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $ativo    = isset($_POST['ativo']) ? 1 : 0;

    $tem_contrato  = isset($_POST['adicionar_contrato']) && $_POST['adicionar_contrato'] === '1';
    $epoca_id      = $tem_contrato ? intval($_POST['epoca_id']) : 0;
    $salario       = $tem_contrato ? floatval($_POST['salario_mensal']) : 0;
    $bonus         = ($tem_contrato && !empty($_POST['bonus_anual'])) ? floatval($_POST['bonus_anual']) : null;
    $notas_contrato = $tem_contrato ? trim($_POST['notas_contrato'] ?? '') : '';

    if (empty($nome) || empty($cargo)) {
        $erro = 'Nome e cargo são obrigatórios!';
    } elseif ($tem_contrato && ($epoca_id <= 0 || $salario <= 0)) {
        $erro = 'Para adicionar contrato, época e salário são obrigatórios!';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO staff (nome, cargo_principal, telefone, email, ativo, club_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $nome, $cargo, $telefone, $email, $ativo, $user_club_id);

            if ($stmt->execute()) {
                $novo_id = $conn->insert_id;
                $stmt->close();

                if ($tem_contrato && $epoca_id > 0 && $salario > 0) {
                    $stmt2 = $conn->prepare("INSERT INTO contratos_staff (staff_id, epoca_id, salario_mensal, bonus_anual, notas) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->bind_param("iidds", $novo_id, $epoca_id, $salario, $bonus, $notas_contrato);
                    if ($stmt2->execute()) {
                        $contrato_id = $conn->insert_id;
                        $stmt2->close();
                        error_log("GERANDO DESPESAS PARA CLUB: " . $club_id);
                        gerarDespesasMensais($conn, $contrato_id, $novo_id, $epoca_id, $salario, $nome, $staff_club_id);
                        header('Location: editar.php?id=' . $novo_id . '&msg=criado_com_contrato');
                    } else {
                        $erro = 'Membro criado mas erro no contrato: ' . $stmt2->error;
                    }
                } else {
                    header('Location: editar.php?id=' . $novo_id . '&msg=criado');
                }
                exit;
            } else {
                $erro = 'Erro ao criar membro: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $erro = 'Erro: ' . $e->getMessage();
        }
    }
}
$stmt = $conn->prepare("SELECT club_id FROM staff WHERE id = ?");
$stmt->bind_param("i", $novo_id);
$stmt->execute();
$res = $stmt->get_result();
$staff = $res->fetch_assoc();
$staff_club_id = $staff['club_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Membro - SportGes</title>
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

        /* HEADER */
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
        .btn-back {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(59,130,246,0.2);
        }
        .btn-back:hover { transform: translateY(-2px); color: white; box-shadow: 0 6px 20px rgba(59,130,246,0.35); }

        /* ALERTS */
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
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert i { font-size: 1.25rem; }
        .alert-danger  { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

        /* INFO BOX ÉPOCA */
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
        .epoca-info-box .info-content p  { font-size: 0.875rem; color: #15803d; margin: 0; }

        /* CARD */
        .form-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            animation: fadeInUp 0.4s ease;
            margin-bottom: 1.5rem;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .section-title {
            font-size: 1.125rem;
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

        /* FORM */
        .form-group { margin-bottom: 1.25rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }
        input[type="text"], input[type="email"], input[type="tel"],
        input[type="number"], select, textarea {
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
        textarea { resize: vertical; min-height: 90px; }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
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
            z-index: 1;
        }
        .currency-input input { padding-left: 2.5rem; }
        .help-text { font-size: 0.8125rem; color: #64748b; margin-top: 0.375rem; }

        /* CHECKBOX */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .checkbox-group:hover { border-color: #3b82f6; background: #eff6ff; }
        input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #3b82f6; }
        .checkbox-group label { margin: 0; cursor: pointer; user-select: none; font-weight: 500; }

        /* TOGGLE CONTRATO */
        .contrato-toggle {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: #eff6ff;
            border: 2px solid #bfdbfe;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0;
        }
        .contrato-toggle:hover { border-color: #3b82f6; background: #dbeafe; }
        .contrato-toggle input[type="checkbox"] { accent-color: #2563eb; }
        .contrato-toggle label { margin: 0; cursor: pointer; user-select: none; font-weight: 600; color: #1e40af; font-size: 0.9375rem; }
        .contrato-toggle .toggle-hint { font-size: 0.8125rem; color: #3b82f6; margin-left: auto; font-weight: 500; }

        .contrato-fields {
            display: none;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 2px dashed #bfdbfe;
            animation: fadeInUp 0.3s ease;
        }
        .contrato-fields.visible { display: block; }

        /* PREVIEW TOTAL */
        .total-preview {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #7dd3fc;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-top: 0.75rem;
            display: none;
        }
        .total-preview.visible { display: block; }
        .total-preview .total-label { font-size: 0.8125rem; color: #0369a1; font-weight: 600; margin-bottom: 0.25rem; }
        .total-preview .total-value { font-size: 1.5rem; font-weight: 800; color: #0c4a6e; }
        .total-preview .total-detail { font-size: 0.8rem; color: #0369a1; margin-top: 0.25rem; }
        .total-preview .despesas-note { font-size: 0.8rem; color: #059669; font-weight: 600; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.375rem; }

        /* STATUS BADGES */
        .status-badge {
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-atual    { background: #22c55e; color: white; }
        .status-futura   { background: #3b82f6; color: white; }
        .status-passada  { background: #94a3b8; color: white; }

        /* ACTIONS */
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
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(59,130,246,0.2);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,0.35); }
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
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-cancel:hover { background: #cbd5e1; color: #334155; }

        /* RESPONSIVE */
        @media (max-width: 1200px) { .main-content { margin-left: 0; width: 100%; } }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .import-list-header { padding: 1.25rem; flex-direction: column; align-items: stretch; }
            .import-list-header h1 { font-size: 1.375rem; }
            .btn-back { width: 100%; justify-content: center; }
            .form-card { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .form-actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include('../../includes/sidebar.php'); ?>

<div class="main-content">
    <?php include('../../includes/role_helper.php'); ?>

    <div class="content-wrapper">

        <!-- HEADER -->
        <div class="import-list-header">
            <h1>
                <i class="bi bi-person-plus-fill"></i>
                Novo Membro de Staff
            </h1>
            <a href="listar.php" class="btn-back">
                <i class="bi bi-arrow-left"></i>
                Voltar
            </a>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($epoca_atual_info): ?>
            <div class="epoca-info-box">
                <i class="bi bi-calendar-check-fill"></i>
                <div class="info-content">
                    <h4>Época Atual: <?= htmlspecialchars($epoca_atual_info['nome']) ?></h4>
                    <p><?= date('d/m/Y', strtotime($epoca_atual_info['data_inicio'])) ?> — <?= date('d/m/Y', strtotime($epoca_atual_info['data_fim'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="mainForm">
            <input type="hidden" name="adicionar_contrato" id="input_adicionar_contrato" value="0">

            <!-- INFORMAÇÕES BÁSICAS -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-person-badge"></i>
                    Informações Básicas
                </div>

                <div class="form-group">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome"
                           value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                           placeholder="Digite o nome completo"
                           required autofocus>
                </div>

                <div class="form-group">
                    <label for="cargo_principal">Cargo Principal *</label>
                    <select id="cargo_principal" name="cargo_principal" required>
                        <option value="">Selecione um cargo</option>
                        <?php foreach (['Treinador','Adjunto','Fisioterapeuta','Diretor','Outro'] as $c): ?>
                            <option value="<?= $c ?>" <?= ($_POST['cargo_principal'] ?? '') === $c ? 'selected' : '' ?>>
                                <?= $c ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone"
                               value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>"
                               placeholder="+351 912 345 678">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="exemplo@email.com">
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="ativo" name="ativo" checked>
                        <label for="ativo">Membro Ativo</label>
                    </div>
                </div>
            </div>

            <!-- CONTRATO OPCIONAL -->
            <div class="form-card">
                <div class="section-title">
                    <i class="bi bi-file-earmark-text"></i>
                    Contrato Inicial <span style="font-size:0.8rem;color:#64748b;font-weight:500;text-transform:none;letter-spacing:0">(opcional)</span>
                </div>

                <div class="contrato-toggle">
                    <input type="checkbox" id="toggle_contrato">
                    <label for="toggle_contrato">Adicionar contrato agora</label>
                    <span class="toggle-hint">As despesas mensais serão geradas automaticamente</span>
                </div>

                <div class="contrato-fields" id="contratoFields">

                    <div class="form-group">
                        <label for="epoca_id">Época *</label>
                        <select id="epoca_id" name="epoca_id">
                            <option value="">Selecione uma época</option>
                            <?php foreach ($epocas as $epoca):
                                $st = getEpocaStatus($epoca['data_inicio'], $epoca['data_fim'], $hoje);
                            ?>
                                <option value="<?= $epoca['id'] ?>" <?= $epoca['epoca_atual'] == 1 ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($epoca['nome']) ?>
                                    (<?= date('d/m/Y', strtotime($epoca['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($epoca['data_fim'])) ?>)
                                    [<?= $st['label'] ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text"><i class="bi bi-info-circle"></i> O contrato é aplicado a todos os meses desta época.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="salario_mensal">Salário Mensal *</label>
                            <div class="currency-input">
                                <input type="number" id="salario_mensal" name="salario_mensal"
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="help-text">Valor pago mensalmente</div>
                        </div>
                        <div class="form-group">
                            <label for="bonus_anual">Bónus Anual <span style="font-weight:400;color:#94a3b8">(opcional)</span></label>
                            <div class="currency-input">
                                <input type="number" id="bonus_anual" name="bonus_anual"
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="help-text">Bónus único no final da época</div>
                        </div>
                    </div>

                    <!-- PREVIEW TOTAL -->
                    <div class="total-preview" id="totalPreview">
                        <div class="total-label">Total da Época</div>
                        <div class="total-value" id="totalValue">€ 0,00</div>
                        <div class="total-detail" id="totalDetail"></div>
                        <div class="despesas-note"><i class="bi bi-check-circle-fill"></i> Despesas mensais geradas automaticamente</div>
                    </div>

                    <div class="form-group" style="margin-top:1rem;">
                        <label for="notas_contrato">Notas sobre o Contrato</label>
                        <textarea id="notas_contrato" name="notas_contrato"
                                  placeholder="Condições especiais, objetivos, observações..."></textarea>
                    </div>

                </div><!-- /contrato-fields -->

                <!-- ACTIONS -->
                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <i class="bi bi-person-plus-fill"></i>
                        <span id="btnLabel">Criar Membro</span>
                    </button>
                    <a href="listar.php" class="btn-cancel">
                        <i class="bi bi-x-circle-fill"></i>
                        Cancelar
                    </a>
                </div>
            </div>

        </form>

    </div><!-- /content-wrapper -->
</div><!-- /main-content -->

<script>
const toggleCheckbox   = document.getElementById('toggle_contrato');
const contratoFields   = document.getElementById('contratoFields');
const inputContrato    = document.getElementById('input_adicionar_contrato');
const btnLabel         = document.getElementById('btnLabel');
const salarioInput     = document.getElementById('salario_mensal');
const bonusInput       = document.getElementById('bonus_anual');
const totalPreview     = document.getElementById('totalPreview');
const totalValue       = document.getElementById('totalValue');
const totalDetail      = document.getElementById('totalDetail');

toggleCheckbox.addEventListener('change', function () {
    if (this.checked) {
        contratoFields.classList.add('visible');
        inputContrato.value = '1';
        btnLabel.textContent = 'Criar Membro e Contrato';
    } else {
        contratoFields.classList.remove('visible');
        inputContrato.value = '0';
        btnLabel.textContent = 'Criar Membro';
    }
});

function atualizarTotal() {
    const salario = parseFloat(salarioInput.value) || 0;
    const bonus   = parseFloat(bonusInput.value) || 0;
    const total   = (salario * 12) + bonus;

    if (salario > 0) {
        totalPreview.classList.add('visible');
        totalValue.textContent = '€ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        totalDetail.textContent = '12 meses × €' + salario.toFixed(2) + (bonus > 0 ? ' + €' + bonus.toFixed(2) + ' bónus' : '');
    } else {
        totalPreview.classList.remove('visible');
    }
}

salarioInput.addEventListener('input', atualizarTotal);
bonusInput.addEventListener('input', atualizarTotal);
</script>

</body>
</html>
<?php $conn->close(); ?>