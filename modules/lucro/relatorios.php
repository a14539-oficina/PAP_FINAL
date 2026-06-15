<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require('../../config/db.php');

/* ======================= VERIFICA LOGIN ======================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_club_id = intval($_SESSION['club_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? '';

$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

if (!in_array($user_role, ['1', '2'])) {
    $_SESSION['erro'] = "Acesso negado!";
    header("Location: ../../dashboard.php");
    exit;
}

/* ======================= CRIAÇÃO DA TABELA ======================= */
$conn->query("
CREATE TABLE IF NOT EXISTS relatorios_receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes INT NOT NULL,
    ano INT NOT NULL,
    club_id INT DEFAULT NULL,
    total_despesas DECIMAL(10,2) DEFAULT 0,
    lucro_liquido DECIMAL(10,2) DEFAULT 0,
    total_receitas DECIMAL(10,2) DEFAULT 0,
    total_quotas DECIMAL(10,2) DEFAULT 0,
    total_patrocinios DECIMAL(10,2) DEFAULT 0,
    total_eventos DECIMAL(10,2) DEFAULT 0,
    total_merchandising DECIMAL(10,2) DEFAULT 0,
    total_subsidios DECIMAL(10,2) DEFAULT 0,
    total_outras DECIMAL(10,2) DEFAULT 0,
    receitas_mensais DECIMAL(10,2) DEFAULT 0,
    receitas_unicas DECIMAL(10,2) DEFAULT 0,
    num_receitas INT DEFAULT 0,
    total_mensalidades DECIMAL(10,2) DEFAULT 0,
    mensalidades_pagas INT DEFAULT 0,
    mensalidades_pendentes INT DEFAULT 0,
    mensalidades_atrasadas INT DEFAULT 0,
    mensalidades_lista TEXT,
    observacoes TEXT,
    gerado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    gerado_por INT,
    UNIQUE KEY unique_relatorio (mes, ano, club_id)
)");

/* ======================= DETEÇÃO DE COLUNA ======================= */
$checkStatusColumn = $conn->query("SHOW COLUMNS FROM mensalidades LIKE 'status'");
$checkEstadoColumn = $conn->query("SHOW COLUMNS FROM mensalidades LIKE 'estado'");

if ($checkStatusColumn->num_rows > 0) {
    $colunaEstado = "status";
} elseif ($checkEstadoColumn->num_rows > 0) {
    $colunaEstado = "estado";
} else {
    $conn->query("ALTER TABLE mensalidades ADD COLUMN status VARCHAR(20) DEFAULT 'pendente'");
    $colunaEstado = "status";
}

/* ======================= FUNÇÃO: RESUMO BLINDADO ======================= */
function getMensalidadesResumo($conn, $mes, $ano, $club_id, $colunaEstado)
{
    $mesAnoFormat = sprintf('%04d-%02d', $ano, $mes);

    $sql = "
        SELECT 
            m.valor,
            m.$colunaEstado AS estado,
            p.club_id AS jogador_clube
        FROM mensalidades m
        LEFT JOIN players p ON p.id = m.jogador_id
        WHERE DATE_FORMAT(m.mes_referencia, '%Y-%m') = ?
        AND p.club_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $mesAnoFormat, $club_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $ret = [
        'valor_pago' => 0,
        'pagas' => 0,
        'pendentes' => 0,
        'atrasadas' => 0
    ];

    while ($row = $res->fetch_assoc()) {
        if ((int)$row['jogador_clube'] !== (int)$club_id) continue;

        $estado = strtolower(trim($row['estado']));

        if (in_array($estado, ['pago','paga','paid'])) {
            $ret['pagas']++;
            $ret['valor_pago'] += floatval($row['valor']);
        } elseif (in_array($estado, ['pendente','pending'])) {
            $ret['pendentes']++;
        } elseif (in_array($estado, ['atrasado','late','atrasada'])) {
            $ret['atrasadas']++;
        }
    }

    return $ret;
}

/* ======================= FUNÇÃO: MENSALIDADES DETALHES BLINDADO ======================= */
function getMensalidadesDetalhadas($conn, $mes, $ano, $club_id, $colunaEstado)
{
    $mesAnoFormat = sprintf('%04d-%02d', $ano, $mes);

    $sql = "
        SELECT
            TRIM(CONCAT(p.primeiro_nome, ' ', COALESCE(p.ultimo_nome,''))) AS nome,
            m.valor,
            m.$colunaEstado AS estado_original,
            m.data_pagamento,
            m.jogador_id,
            p.club_id AS jogador_clube
        FROM mensalidades m
        LEFT JOIN players p ON p.id = m.jogador_id
        WHERE DATE_FORMAT(m.mes_referencia, '%Y-%m') = ?
        AND p.club_id = ?
        ORDER BY p.primeiro_nome
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $mesAnoFormat, $club_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $lista = [];

    while ($row = $res->fetch_assoc()) {
        if ((int)$row['jogador_clube'] !== (int)$club_id) continue;

        $estado = strtolower(trim($row['estado_original']));

        if (in_array($estado, ['pago','paga','paid'])) $estado = 'pago';
        elseif (in_array($estado, ['atrasado','late','atrasada'])) $estado = 'atrasado';
        else $estado = 'pendente';

        $row['estado'] = $estado;
        $lista[] = $row;
    }

    return $lista;
}

/* ======================= CRIAR RELATÓRIO ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_relatorio'])) {

    $mes = intval($_POST['mes']);
    $ano = intval($_POST['ano']);
    $club_id = $isAdminPrincipal ? intval($_POST['club_id']) : $user_club_id;

    $check = $conn->prepare("SELECT id FROM relatorios_receitas WHERE mes = ? AND ano = ? AND club_id = ?");
    $check->bind_param("iii", $mes, $ano, $club_id);
    $check->execute();
    $exists = $check->get_result();

    if ($exists->num_rows > 0) {
        $_SESSION['erro'] = "Já existe um relatório para este mês.";
        header("Location: relatorios.php");
        exit;
    }

    $obs = $_POST['observacoes'] ?? '';
    $user = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("
        INSERT INTO relatorios_receitas (mes, ano, club_id, observacoes, gerado_por)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiisi", $mes, $ano, $club_id, $obs, $user);
    $stmt->execute();

    $_SESSION['sucesso'] = "Relatório criado com sucesso!";
    header("Location: relatorios.php");
    exit;
}

/* ======================= ATUALIZAR RELATÓRIOS ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_relatorios'])) {

    $sql = $isAdminPrincipal
        ? "SELECT id, mes, ano, club_id FROM relatorios_receitas"
        : "SELECT id, mes, ano, club_id FROM relatorios_receitas WHERE club_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$isAdminPrincipal) $stmt->bind_param("i", $user_club_id);
    $stmt->execute();
    $relList = $stmt->get_result();

    $count = 0;

    while ($rel = $relList->fetch_assoc()) {

        $mes = $rel['mes'];
        $ano = $rel['ano'];
        $club = $rel['club_id'];

        $dataIni = "$ano-" . str_pad($mes,2,'0',STR_PAD_LEFT) . "-01";
        $dataFim = date("Y-m-t", strtotime($dataIni));

        $sqlRec = "
            SELECT categoria, tipo, valor
            FROM receitas
            WHERE data BETWEEN ? AND ?
            AND club_id = ?
        ";

        $stmtRec = $conn->prepare($sqlRec);
        $stmtRec->bind_param("ssi", $dataIni, $dataFim, $club);
        $stmtRec->execute();
        $rres = $stmtRec->get_result();

        $dados = [
            'total'=>0,'quotas'=>0,'patrocinios'=>0,'eventos'=>0,'merchandising'=>0,'subsidios'=>0,
            'outras'=>0,'mensais'=>0,'unicas'=>0,'num_receitas'=>0
        ];

        while ($r = $rres->fetch_assoc()) {
            $valor = floatval($r['valor']);
            $cat = strtolower($r['categoria']);
            $tipo = strtolower($r['tipo']);

            $dados['total'] += $valor;
            $dados['num_receitas']++;

            if (str_contains($cat,'quota')||str_contains($cat,'socio')) $dados['quotas'] += $valor;
            elseif (str_contains($cat,'patroc')) $dados['patrocinios'] += $valor;
            elseif (str_contains($cat,'event')) $dados['eventos'] += $valor;
            elseif (str_contains($cat,'merch')) $dados['merchandising'] += $valor;
            elseif (str_contains($cat,'subsid')) $dados['subsidios'] += $valor;
            else $dados['outras'] += $valor;

            if (str_contains($tipo,'mensal')) $dados['mensais'] += $valor;
            else $dados['unicas'] += $valor;
        }

        $mensResumo = getMensalidadesResumo($conn, $mes, $ano, $club, $colunaEstado);
        $mensDetalhes = getMensalidadesDetalhadas($conn, $mes, $ano, $club, $colunaEstado);

        $valorMens = floatval($mensResumo['valor_pago']);
        $totalComMens = $dados['total'] + $valorMens;

        $stmtDesp = $conn->prepare("
            SELECT COALESCE(SUM(valor),0) AS total 
            FROM despesas 
            WHERE data BETWEEN ? AND ? 
            AND club_id = ?
        ");
        $stmtDesp->bind_param("ssi",$dataIni,$dataFim,$club);
        $stmtDesp->execute();
        $desp = $stmtDesp->get_result()->fetch_assoc();

        $totalDespesas = floatval($desp['total']);
        $lucro = $totalComMens - $totalDespesas;

        $jsonMens = json_encode($mensDetalhes, JSON_UNESCAPED_UNICODE);

        $stmtUp = $conn->prepare("
            UPDATE relatorios_receitas SET
                total_receitas = ?,
                total_despesas = ?,
                lucro_liquido = ?,
                total_quotas = ?,
                total_patrocinios = ?,
                total_eventos = ?,
                total_merchandising = ?,
                total_subsidios = ?,
                total_outras = ?,
                receitas_mensais = ?,
                receitas_unicas = ?,
                num_receitas = ?,
                total_mensalidades = ?,
                mensalidades_pagas = ?,
                mensalidades_pendentes = ?,
                mensalidades_atrasadas = ?,
                mensalidades_lista = ?,
                gerado_em = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmtUp->bind_param(
            "ddddddddddiiiiiisi",
            $totalComMens,
            $totalDespesas,
            $lucro,
            $dados['quotas'],
            $dados['patrocinios'],
            $dados['eventos'],
            $dados['merchandising'],
            $dados['subsidios'],
            $dados['outras'],
            $dados['mensais'],
            $dados['unicas'],
            $dados['num_receitas'],
            $valorMens,
            $mensResumo['pagas'],
            $mensResumo['pendentes'],
            $mensResumo['atrasadas'],
            $jsonMens,
            $rel['id']
        );

        $stmtUp->execute();
        $count++;
    }

    $_SESSION['sucesso'] = "Foram atualizados $count relatório(s).";
    header("Location: relatorios.php?refresh=".time());
    exit;
}

/* ======================= ELIMINAR RELATÓRIO (AJAX) ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_ajax'])) {
    header('Content-Type: application/json');

    $id = intval($_POST['id']);

    $chk = $conn->prepare("SELECT id FROM relatorios_receitas WHERE id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $exists = $chk->get_result();

    if (!$exists->num_rows) {
        echo json_encode(['success' => false, 'message' => 'O relatório já não existe.']);
        exit;
    }

    $del = $conn->prepare("DELETE FROM relatorios_receitas WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();

    echo json_encode(['success' => true]);
    exit;
}

/* ======================= LISTAGEM ======================= */

$meses = [
    1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',
    7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'
];

$relatorios = [];

if ($isAdminPrincipal) {
    $r = $conn->query("
        SELECT r.*, c.nome AS clube_nome 
        FROM relatorios_receitas r
        LEFT JOIN clubs c ON c.id = r.club_id
        ORDER BY ano DESC, mes DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT r.*, c.nome AS clube_nome 
        FROM relatorios_receitas r
        LEFT JOIN clubs c ON c.id = r.club_id
        WHERE r.club_id = ?
        ORDER BY ano DESC, mes DESC
    ");
    $stmt->bind_param("i",$user_club_id);
    $stmt->execute();
    $r = $stmt->get_result();
}

while ($row = $r->fetch_assoc()) {
    $relatorios[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Receitas - SportGes</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">

    <style>
        html, body {
            overflow-x: hidden;
            max-width: 100%;
        }

        .main-content {
            margin-left: 260px;
            margin-top: 0;
            padding: 24px;
            min-height: 100vh;
            width: calc(100% - 260px);
            box-sizing: border-box;
            overflow-x: hidden;
        }

        .page-header {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            width: 100%;
            box-sizing: border-box;
        }

        .page-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title h1 {
            font-size: 28px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .page-title p {
            color: #64748b;
            font-size: 14px;
            margin-top: 8px;
            margin: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
        }

        .relatorio-linha {
            background: white;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .relatorio-linha:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .relatorio-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }

        .relatorio-nome {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
        }

        .relatorio-mes {
            color: #64748b;
            font-size: 14px;
        }

        .relatorio-total-mini {
            font-weight: 700;
            color: #10b981;
            font-size: 16px;
            margin-right: 10px;
        }

        .relatorio-acoes {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }

        .btn-ver {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }

        .btn-ver:hover {
            background: #2563eb;
        }

        .btn-delete {
            background: #ef4444;
            border: none;
            color: white;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .relatorio-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
            display: none;
        }

        .relatorio-card.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .relatorio-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .relatorio-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        .relatorio-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        .total-box {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }

        .total-box-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .total-box-valor {
            font-size: 32px;
            font-weight: 800;
        }

        .badge-purple {
            background: #8b5cf6;
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .print-btn {
            background: white;
            border: 2px solid #e5e7eb;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .print-btn:hover {
            background: #f8fafc;
            border-color: #3b82f6;
        }

        .receitas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
            gap: 18px;
            margin-top: 20px;
        }

        .card.receita-card {
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
            background: white;
        }

        .card.receita-card h4 {
            font-size: 13px;
            font-weight: 600;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .card.receita-card p {
            font-size: 20px;
            font-weight: 700;
        }

        .purple { border-left: 5px solid #7e3ff2; }
        .orange { border-left: 5px solid #e89c1c; }
        .dark   { border-left: 5px solid #444; }
        .green  { border-left: 5px solid #27ae60; }
        .yellow { border-left: 5px solid #f1c40f; }

        .mensal-mini {
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
        }

        .mensal-mini h5 {
            font-size: 24px;
            font-weight: 800;
            margin: 0;
        }

        .mensal-mini span {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.7;
        }

        .green-border  { border-left: 5px solid #2ecc71; }
        .orange-border { border-left: 5px solid #f39c12; }
        .red-border    { border-left: 5px solid #e74c3c; }

        .mensalidades-titulo {
            font-size: 18px;
            font-weight: 700;
            margin: 30px 0 15px;
            color: #1e293b;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }

        .mensalidades-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .mensalidades-table th {
            padding: 12px 16px;
            font-size: 13px;
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }

        .mensalidades-table td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .mensalidades-table tr:hover {
            background: #f8fafc;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-status.pago     { background: #d1fae5; color: #065f46; }
        .badge-status.pendente { background: #fed7aa; color: #92400e; }
        .badge-status.atrasado { background: #fee2e2; color: #991b1b; }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                margin-top: 0;
                width: 100%;
                padding: 16px;
            }

            .page-header-top {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
            }

            .page-header-top > div:last-child {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .relatorio-linha {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .relatorio-info { width: 100%; }
            .relatorio-acoes { width: 100%; justify-content: flex-start; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .page-header { padding: 16px; }
            .page-title h1 { font-size: 22px; }
            .relatorio-linha { padding: 12px 16px; }
            .relatorio-nome { font-size: 14px; }
            .relatorio-mes { font-size: 12px; }
        }

        @media print {
            body * { visibility: hidden; }
            .print-break { page-break-before: always !important; }
            .relatorio-card.active, .relatorio-card.active * { visibility: visible; }
            .relatorio-card.active {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20mm;
            }
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #000;
            }
            .print-header h1 { margin: 0; font-size: 26px; }
            .print-header p { margin: 0; font-size: 14px; }
        }
    </style>
</head>
<body>

<?php require('../../includes/sidebar.php'); ?>

<div class="main-content">

<?php if (isset($_SESSION['sucesso'])): ?>
    <div class="alert alert-success">
        <i class='bx bx-check-circle'></i> <?= $_SESSION['sucesso'] ?>
    </div>
    <?php unset($_SESSION['sucesso']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['erro'])): ?>
    <div class="alert alert-danger">
        <i class='bx bx-error-circle'></i> <?= $_SESSION['erro'] ?>
    </div>
    <?php unset($_SESSION['erro']); ?>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-top">
        <div class="page-title">
            <h1><i class="bx bx-bar-chart-alt-2"></i> Relatórios de Receitas</h1>
            <p>Consulta e gere relatórios mensais de receitas e mensalidades</p>
        </div>

        <div style="display: flex; gap: 12px;">
            <a href="javascript:history.back()" class="btn-primary" 
               style="text-decoration:none;background:linear-gradient(135deg,#64748b,#475569);">
               <i class='bx bx-arrow-back'></i> Voltar
            </a>

            <form method="POST" style="display:inline">
                <button type="submit" name="atualizar_relatorios" class="btn-primary"
                        style="background:linear-gradient(135deg,#06b6d4,#0891b2);">
                    <i class='bx bx-refresh'></i> Atualizar Relatórios
                </button>
            </form>

            <button onclick="toggleForm()" class="btn-primary">
                <i class='bx bx-plus'></i> Gerar Novo Relatório
            </button>
        </div>
    </div>
</div>

<div id="formNovoRelatorio" class="form-section" style="display:none;">
    <h2>Gerar Relatório Mensal</h2>
    <form method="POST">
        <div class="form-grid">

            <div class="form-group">
                <label class="form-label">Mês</label>
                <select name="mes" class="form-select" required>
                    <?php foreach ($meses as $num => $nome): ?>
                        <option value="<?= $num ?>" <?= $num==date('n')?'selected':'' ?>>
                            <?= $nome ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Ano</label>
                <select name="ano" class="form-select" required>
                    <?php 
                        $ano_inicio = date('Y') + 1;
                        for ($a=$ano_inicio; $a>=2023; $a--): ?>
                        <option value="<?= $a ?>"><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php if ($isAdminPrincipal): ?>
            <div class="form-group">
                <label class="form-label">Clube</label>
                <select name="club_id" class="form-select" required>
                    <option value="">Selecionar clube</option>
                    <?php foreach ($clubes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group full">
                <label class="form-label">Observações (opcional)</label>
                <textarea name="observacoes" class="form-input" rows="3"></textarea>
            </div>

        </div>

        <div class="form-buttons">
            <button type="submit" name="gerar_relatorio" class="btn-submit">Gerar Relatório</button>
            <button type="button" onclick="toggleForm()" class="btn-cancel">Cancelar</button>
        </div>
    </form>
</div>

<div class="relatorios-lista">

<?php if (empty($relatorios)): ?>
    <div class="empty-state">
        <i class="bx bx-file" style="font-size:64px;"></i>
        <h3>Nenhum relatório disponível</h3>
        <p>Gera o primeiro relatório mensal para começar</p>
    </div>
<?php else: ?>

<?php foreach ($relatorios as $rel): ?>

    <div class="relatorio-linha" onclick="toggleRelatorio(<?= $rel['id'] ?>)">
        
        <div class="relatorio-info">
            <i class="bx bx-file" style="font-size:24px;color:#64748b;"></i>
            <div>
                <div class="relatorio-nome">
                    Relatório de <?= $meses[$rel['mes']] ?> <?= $rel['ano'] ?>
                    <?php if ($isAdminPrincipal && $rel['clube_nome']): ?>
                        <span class="badge-purple"><?= htmlspecialchars($rel['clube_nome']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="relatorio-mes">
                    <?= $rel['num_receitas'] ?> receitas • 
                    <?= $rel['mensalidades_pagas'] ?> pagas • 
                    Gerado em <?= date('d/m/Y', strtotime($rel['gerado_em'])) ?>
                </div>
            </div>
        </div>

        <div class="relatorio-acoes" onclick="event.stopPropagation()">
            
            <div class="relatorio-total-mini">
                <?= number_format($rel['total_receitas'],2,',','.') ?> €
            </div>

            <button onclick="toggleRelatorio(<?= $rel['id'] ?>);event.stopPropagation();" 
                    class="btn-ver">
                <i class="bx bx-show"></i> Ver
            </button>

            <button onclick="imprimirRelatorio(<?= $rel['id'] ?>);event.stopPropagation();" 
                    class="print-btn">
                <i class="bx bx-printer"></i>
            </button>

            <button
                class="btn-delete btn-eliminar-relatorio"
                data-id="<?= $rel['id'] ?>"
                data-nome="Relatório de <?= $meses[$rel['mes']] ?> <?= $rel['ano'] ?>"
                onclick="event.stopPropagation()">
                <i class="bx bx-trash"></i>
            </button>

        </div>
    </div>

    <div class="relatorio-card" id="relatorio-<?= $rel['id'] ?>">

        <div class="print-header" style="display:none;">
            <h1>Relatório Financeiro</h1>
            <p><?= $meses[$rel['mes']] ?> de <?= $rel['ano'] ?></p>
            <?php if ($isAdminPrincipal): ?>
                <p><strong><?= htmlspecialchars($rel['clube_nome']) ?></strong></p>
            <?php endif; ?>
        </div>

        <div class="relatorio-header">
            <div>
                <div class="relatorio-title">
                    <i class='bx bx-calendar'></i>
                    Relatório de <?= $meses[$rel['mes']] ?> <?= $rel['ano'] ?>
                </div>
                <div class="relatorio-subtitle">
                    Gerado em <?= date('d/m/Y H:i', strtotime($rel['gerado_em'])) ?>
                </div>
            </div>

            <button onclick="toggleRelatorio(<?= $rel['id'] ?>)" class="btn-ver" style="background:#ef4444;">
                <i class='bx bx-x'></i> Fechar
            </button>
        </div>

        <div class="total-box">
            <div class="total-box-label">LUCRO LÍQUIDO</div>
            <div class="total-box-valor">
                <?= number_format($rel['lucro_liquido'],2,',','.') ?> €
            </div>
        </div>

        <div class="receitas-grid">

            <div class="card receita-card purple">
                <h4>QUOTAS</h4>
                <p><?= number_format($rel['total_quotas'],2,',','.') ?> €</p>
            </div>

            <div class="card receita-card orange">
                <h4>PATROCÍNIOS</h4>
                <p><?= number_format($rel['total_patrocinios'],2,',','.') ?> €</p>
            </div>

            <div class="card receita-card dark">
                <h4>OUTRAS RECEITAS</h4>
                <p><?= number_format($rel['total_outras'],2,',','.') ?> €</p>
            </div>

            <div class="card receita-card green">
                <h4>RECEITAS ÚNICAS</h4>
                <p><?= number_format($rel['receitas_unicas'],2,',','.') ?> €</p>
            </div>

            <div class="card receita-card yellow">
                <h4>RECEITAS MENSAIS</h4>
                <p><?= number_format($rel['receitas_mensais'],2,',','.') ?> €</p>
            </div>

            <div class="card mensal-mini green-border">
                <h5><?= $rel['mensalidades_pagas'] ?></h5>
                <span>MENSALIDADES PAGAS</span>
            </div>

            <div class="card mensal-mini orange-border">
                <h5><?= $rel['mensalidades_pendentes'] ?></h5>
                <span>MENSALIDADES PENDENTES</span>
            </div>

            <div class="card mensal-mini red-border">
                <h5><?= $rel['mensalidades_atrasadas'] ?></h5>
                <span>MENSALIDADES ATRASADAS</span>
            </div>

        </div>

        <?php 
        $mensAtual = getMensalidadesDetalhadas($conn, $rel['mes'], $rel['ano'], $rel['club_id'], $colunaEstado);
        ?>

        <?php if (!empty($mensAtual)): ?>
            <h3 class="mensalidades-titulo print-break">Lista de Jogadores com Mensalidades</h3>
            <table class="mensalidades-table">
                <thead>
                    <tr>
                        <th>Jogador</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:right;">Valor</th>
                        <th style="text-align:center;">Pagamento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mensAtual as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['nome']) ?></td>
                        <td style="text-align:center;">
                            <span class="badge-status <?= $m['estado'] ?>">
                                <?= strtoupper($m['estado']) ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <strong><?= number_format($m['valor'],2,',','.') ?> €</strong>
                        </td>
                        <td style="text-align:center;">
                            <?= $m['data_pagamento'] ? date('d/m/Y',strtotime($m['data_pagamento'])) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="mensalidades-titulo">Nenhuma mensalidade registada neste mês</div>
        <?php endif; ?>

    </div>

<?php endforeach; ?>
<?php endif; ?>

</div>

</div>

<script src="../../toast.js"></script>
<script>
function toggleRelatorio(id) {
    const card = document.getElementById("relatorio-" + id);
    if (!card) return;

    if (card.classList.contains("active")) {
        card.classList.remove("active");
    } else {
        document.querySelectorAll(".relatorio-card").forEach(c => c.classList.remove("active"));
        card.classList.add("active");
    }
}

function toggleForm() {
    const f = document.getElementById("formNovoRelatorio");
    f.style.display = (f.style.display === "none" || f.style.display === "") ? "block" : "none";
}

function imprimirRelatorio(id) {
    const card = document.getElementById("relatorio-" + id);
    if (!card) return;

    card.classList.add("active");

    const header = card.querySelector(".print-header");
    header.style.display = "block";

    setTimeout(() => {
        window.print();
        setTimeout(() => header.style.display = "none", 100);
    }, 300);
}

// ELIMINAR com toast.confirm
document.querySelectorAll('.btn-eliminar-relatorio').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id   = this.getAttribute('data-id');
        const nome = this.getAttribute('data-nome');

        toast.confirm({
            type: 'warning',
            title: 'Eliminar Relatório?',
            message: `Tens a certeza que queres eliminar "${nome}"? Esta ação não pode ser desfeita.`,
            confirmText: 'Eliminar',
            cancelText: 'Cancelar',
            onConfirm: () => eliminarRelatorio(id, nome)
        });
    });
});

function eliminarRelatorio(id, nome) {
    toast.info('A eliminar...', 'Aguarde um momento');

    fetch('relatorios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `eliminar_ajax=1&id=${encodeURIComponent(id)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            toast.success('Eliminado!', `"${nome}" eliminado com sucesso`);

            // Remove a linha e o card do DOM
            const btn = document.querySelector(`.btn-eliminar-relatorio[data-id="${id}"]`);
            if (btn) {
                const linha = btn.closest('.relatorio-linha');
                const card  = document.getElementById('relatorio-' + id);
                if (linha) {
                    linha.style.transition = 'opacity 0.3s, transform 0.3s';
                    linha.style.opacity = '0';
                    linha.style.transform = 'translateX(20px)';
                    setTimeout(() => { linha.remove(); if (card) card.remove(); }, 300);
                }
            } else {
                setTimeout(() => window.location.reload(), 1500);
            }
        } else {
            toast.error('Erro!', data.message || 'Não foi possível eliminar o relatório.');
        }
    })
    .catch(err => {
        console.error(err);
        toast.error('Erro!', 'Erro ao eliminar. Tente novamente.');
    });
}
</script>

</body>
</html>