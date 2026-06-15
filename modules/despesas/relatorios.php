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
$user_id      = intval($_SESSION['user_id']);
$user_role    = $_SESSION['user_role'] ?? '';

$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);

if (!in_array($user_role, ['1', '2'])) {
    $_SESSION['erro'] = "Acesso negado!";
    header("Location: ../../dashboard.php");
    exit;
}

/* ======================= DETEÇÃO DE COLUNAS ======================= */
$despesasColumns = [];
$res = $conn->query("SHOW COLUMNS FROM despesas");
if ($res) while ($c = $res->fetch_assoc()) $despesasColumns[] = $c['Field'];

$hasClubColumn   = in_array('club_id',   $despesasColumns);
$hasPlayerColumn = in_array('player_id', $despesasColumns);
$hasStaffColumn  = in_array('staff_id',  $despesasColumns);

$checkStatus = $conn->query("SHOW COLUMNS FROM despesas LIKE 'status'");
$checkEstado = $conn->query("SHOW COLUMNS FROM despesas LIKE 'estado'");
if ($checkStatus && $checkStatus->num_rows > 0)      $colunaEstado = 'status';
elseif ($checkEstado && $checkEstado->num_rows > 0)  $colunaEstado = 'estado';
else                                                  $colunaEstado = 'estado';

/* ======================= CRIAÇÃO DA TABELA ======================= */
$conn->query("
CREATE TABLE IF NOT EXISTS relatorios_despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes INT NOT NULL,
    ano INT NOT NULL,
    club_id INT DEFAULT NULL,
    total_despesas DECIMAL(10,2) DEFAULT 0,
    total_pessoal DECIMAL(10,2) DEFAULT 0,
    total_infraestrutura DECIMAL(10,2) DEFAULT 0,
    total_equipamento DECIMAL(10,2) DEFAULT 0,
    total_utilidades DECIMAL(10,2) DEFAULT 0,
    total_transporte DECIMAL(10,2) DEFAULT 0,
    total_outras DECIMAL(10,2) DEFAULT 0,
    despesas_mensais DECIMAL(10,2) DEFAULT 0,
    despesas_unicas DECIMAL(10,2) DEFAULT 0,
    num_despesas INT DEFAULT 0,
    num_pendentes INT DEFAULT 0,
    num_aprovadas INT DEFAULT 0,
    num_rejeitadas INT DEFAULT 0,
    observacoes TEXT,
    gerado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    gerado_por INT,
    UNIQUE KEY unique_relatorio (mes, ano, club_id)
)");

/* Garantir colunas de estado */
$existingCols = [];
$colsCheck = $conn->query("SHOW COLUMNS FROM relatorios_despesas");
if ($colsCheck) while ($c = $colsCheck->fetch_assoc()) $existingCols[] = $c['Field'];
foreach (['num_pendentes','num_aprovadas','num_rejeitadas'] as $col) {
    if (!in_array($col, $existingCols))
        $conn->query("ALTER TABLE relatorios_despesas ADD COLUMN $col INT DEFAULT 0");
}

/* ======================= FUNÇÃO: DESPESAS DETALHADAS ======================= */
function getDespesasDetalhadas($conn, $mes, $ano, $club_id, $hasClubColumn, $colunaEstado, $hasPlayerColumn, $hasStaffColumn)
{
    $dataInicio = "$ano-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
    $dataFim    = date("Y-m-t", strtotime($dataInicio));

    $selectNome = [];
    $joins      = [];
    if ($hasPlayerColumn) {
        $selectNome[] = "TRIM(CONCAT(COALESCE(p.primeiro_nome,''), ' ', COALESCE(p.ultimo_nome,''))) AS jogador_nome";
        $joins[]      = "LEFT JOIN players p ON p.id = d.player_id";
    }
    if ($hasStaffColumn) {
        $selectNome[] = "s.nome AS staff_nome";
        $joins[]      = "LEFT JOIN staff s ON s.id = d.staff_id";
    }

    $colunasExtra = empty($selectNome) ? "" : ", " . implode(", ", $selectNome);
    $joinsSql     = empty($joins)      ? "" : " " . implode(" ", $joins);

    $sql = "
        SELECT d.descricao, d.valor, d.categoria, d.tipo,
               d.{$colunaEstado} AS estado, d.data
               {$colunasExtra}
        FROM despesas d {$joinsSql}
        WHERE d.data BETWEEN ? AND ?
    ";
    $params = [$dataInicio, $dataFim];
    $types  = "ss";

    if ($hasClubColumn) {
        $sql    .= " AND d.club_id = ?";
        $params[] = $club_id;
        $types   .= "i";
    }
    $sql .= " ORDER BY d.data DESC, d.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $lista;
}

/* ======================= CRIAR RELATÓRIO ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_relatorio'])) {

    $mes        = intval($_POST['mes']);
    $ano        = intval($_POST['ano']);
    $obs        = trim($_POST['observacoes'] ?? '');
    $club_id    = $isAdminPrincipal ? intval($_POST['club_id'] ?? 0) : $user_club_id;

    $check = $conn->prepare("SELECT id FROM relatorios_despesas WHERE mes = ? AND ano = ? AND club_id = ?");
    $check->bind_param("iii", $mes, $ano, $club_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['erro'] = "Já existe um relatório para este mês.";
        
    }

    $stmt = $conn->prepare("INSERT INTO relatorios_despesas (mes, ano, club_id, observacoes, gerado_por) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisi", $mes, $ano, $club_id, $obs, $user_id);
    $stmt->execute();

    $_SESSION['sucesso'] = "Relatório criado com sucesso!";
    header("Location: relatorios_despesas.php");
    exit;
}

/* ======================= ATUALIZAR RELATÓRIOS ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_relatorios'])) {

    header('Content-Type: application/json; charset=utf-8');

    // (mesmo código teu de atualização aqui)
    // ...

    echo json_encode([
        'success' => true,
        'message' => "Relatórios atualizados com sucesso!",
        'count' => $count
    ]);
    exit;
}

    $sql  = $isAdminPrincipal
        ? "SELECT id, mes, ano, club_id FROM relatorios_despesas"
        : "SELECT id, mes, ano, club_id FROM relatorios_despesas WHERE club_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$isAdminPrincipal) $stmt->bind_param("i", $user_club_id);
    $stmt->execute();
    $relList = $stmt->get_result();

    $count = 0;

    while ($rel = $relList->fetch_assoc()) {
        $mes   = $rel['mes'];
        $ano   = $rel['ano'];
        $club  = $rel['club_id'];

        $dataIni = "$ano-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
        $dataFim = date("Y-m-t", strtotime($dataIni));

        if ($hasClubColumn) {
            $stmtD = $conn->prepare("
                SELECT categoria, tipo, {$colunaEstado} AS estado, valor
                FROM despesas WHERE data BETWEEN ? AND ? AND club_id = ?
            ");
            $stmtD->bind_param("ssi", $dataIni, $dataFim, $club);
        } else {
            $stmtD = $conn->prepare("
                SELECT categoria, tipo, {$colunaEstado} AS estado, valor
                FROM despesas WHERE data BETWEEN ? AND ?
            ");
            $stmtD->bind_param("ss", $dataIni, $dataFim);
        }
        $stmtD->execute();
        $rows = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtD->close();

        $dados = [
            'total'=>0,'pessoal'=>0,'infraestrutura'=>0,'equipamento'=>0,
            'utilidades'=>0,'transporte'=>0,'outras'=>0,
            'mensais'=>0,'unicas'=>0,'num'=>0,
            'pendentes'=>0,'aprovadas'=>0,'rejeitadas'=>0
        ];

        foreach ($rows as $r) {
            $valor   = floatval($r['valor']);
            $cat     = strtolower(trim($r['categoria'] ?? ''));
            $tipo    = strtolower(trim($r['tipo'] ?? ''));
            $estado  = strtolower(trim($r['estado'] ?? ''));

            $dados['total'] += $valor;
            $dados['num']++;

            if ($cat === 'pessoal')              $dados['pessoal']          += $valor;
            elseif ($cat === 'infraestrutura')   $dados['infraestrutura']   += $valor;
            elseif ($cat === 'equipamento')      $dados['equipamento']      += $valor;
            elseif ($cat === 'utilidades')       $dados['utilidades']       += $valor;
            elseif ($cat === 'transporte')       $dados['transporte']       += $valor;
            else                                 $dados['outras']           += $valor;

            if (str_contains($tipo, 'mensal'))   $dados['mensais'] += $valor;
            else                                 $dados['unicas']  += $valor;

            if (in_array($estado, ['aprovada','aprovado','pago','paga','paid'])) $dados['aprovadas']++;
            elseif (in_array($estado, ['rejeitada','rejeitado','rejected','atrasado'])) $dados['rejeitadas']++;
            else $dados['pendentes']++;
        }

        $stmtUp = $conn->prepare("
            UPDATE relatorios_despesas SET
                total_despesas = ?, total_pessoal = ?, total_infraestrutura = ?,
                total_equipamento = ?, total_utilidades = ?, total_transporte = ?,
                total_outras = ?, despesas_mensais = ?, despesas_unicas = ?,
                num_despesas = ?, num_pendentes = ?, num_aprovadas = ?,
                num_rejeitadas = ?, gerado_em = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmtUp->bind_param(
            "dddddddddiiiii",
            $dados['total'], $dados['pessoal'], $dados['infraestrutura'],
            $dados['equipamento'], $dados['utilidades'], $dados['transporte'],
            $dados['outras'], $dados['mensais'], $dados['unicas'],
            $dados['num'], $dados['pendentes'], $dados['aprovadas'],
            $dados['rejeitadas'], $rel['id']
        );
        $stmtUp->execute();
        $count++;
    }

    $_SESSION['sucesso'] = "Foram atualizados $count relatório(s).";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_relatorios'])) {

    $sql  = $isAdminPrincipal
        ? "SELECT id, mes, ano, club_id FROM relatorios_despesas"
        : "SELECT id, mes, ano, club_id FROM relatorios_despesas WHERE club_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$isAdminPrincipal) $stmt->bind_param("i", $user_club_id);

    $stmt->execute();
    $relList = $stmt->get_result();

    $count = 0;

    while ($rel = $relList->fetch_assoc()) {

        $mes  = $rel['mes'];
        $ano  = $rel['ano'];
        $club = $rel['club_id'];

        $dataIni = "$ano-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
        $dataFim = date("Y-m-t", strtotime($dataIni));

        if ($hasClubColumn) {
            $stmtD = $conn->prepare("
                SELECT categoria, tipo, {$colunaEstado} AS estado, valor
                FROM despesas WHERE data BETWEEN ? AND ? AND club_id = ?
            ");
            $stmtD->bind_param("ssi", $dataIni, $dataFim, $club);
        } else {
            $stmtD = $conn->prepare("
                SELECT categoria, tipo, {$colunaEstado} AS estado, valor
                FROM despesas WHERE data BETWEEN ? AND ?
            ");
            $stmtD->bind_param("ss", $dataIni, $dataFim);
        }

        $stmtD->execute();
        $rows = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtD->close();

        $dados = [
            'total'=>0,'pessoal'=>0,'infraestrutura'=>0,'equipamento'=>0,
            'utilidades'=>0,'transporte'=>0,'outras'=>0,
            'mensais'=>0,'unicas'=>0,'num'=>0,
            'pendentes'=>0,'aprovadas'=>0,'rejeitadas'=>0
        ];

        foreach ($rows as $r) {

            $valor  = floatval($r['valor']);
            $cat    = strtolower(trim($r['categoria'] ?? ''));
            $tipo   = strtolower(trim($r['tipo'] ?? ''));
            $estado = strtolower(trim($r['estado'] ?? ''));

            $dados['total'] += $valor;
            $dados['num']++;

            if ($cat === 'pessoal')              $dados['pessoal'] += $valor;
            elseif ($cat === 'infraestrutura')   $dados['infraestrutura'] += $valor;
            elseif ($cat === 'equipamento')      $dados['equipamento'] += $valor;
            elseif ($cat === 'utilidades')       $dados['utilidades'] += $valor;
            elseif ($cat === 'transporte')       $dados['transporte'] += $valor;
            else                                 $dados['outras'] += $valor;

            if (str_contains($tipo, 'mensal')) $dados['mensais'] += $valor;
            else $dados['unicas'] += $valor;

            if (in_array($estado, ['aprovada','aprovado','pago','paga','paid'])) $dados['aprovadas']++;
            elseif (in_array($estado, ['rejeitada','rejeitado','rejected','atrasado'])) $dados['rejeitadas']++;
            else $dados['pendentes']++;
        }

        $stmtUp = $conn->prepare("
            UPDATE relatorios_despesas SET
                total_despesas = ?, total_pessoal = ?, total_infraestrutura = ?,
                total_equipamento = ?, total_utilidades = ?, total_transporte = ?,
                total_outras = ?, despesas_mensais = ?, despesas_unicas = ?,
                num_despesas = ?, num_pendentes = ?, num_aprovadas = ?,
                num_rejeitadas = ?, gerado_em = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmtUp->bind_param(
            "dddddddddiiiii",
            $dados['total'], $dados['pessoal'], $dados['infraestrutura'],
            $dados['equipamento'], $dados['utilidades'], $dados['transporte'],
            $dados['outras'], $dados['mensais'], $dados['unicas'],
            $dados['num'], $dados['pendentes'], $dados['aprovadas'],
            $dados['rejeitadas'], $rel['id']
        );

        $stmtUp->execute();
        $count++;
    }

    echo json_encode([
        'success' => true,
        'message' => "Foram atualizados $count relatório(s)"
    ]);
    exit;
    
}



/* ======================= ELIMINAR (AJAX) ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_ajax'])) {
    header('Content-Type: application/json');
    $id  = intval($_POST['id']);
    $chk = $conn->prepare("SELECT id FROM relatorios_despesas WHERE id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    if (!$chk->get_result()->num_rows) {
        echo json_encode(['success' => false, 'message' => 'O relatório já não existe.']);
        exit;
    }
    $del = $conn->prepare("DELETE FROM relatorios_despesas WHERE id = ?");

if (!$del) {
    echo json_encode([
        'success' => false,
        'message' => $conn->error
    ]);
    exit;
}

$del->bind_param("i", $id);

if (!$del->execute()) {
    echo json_encode([
        'success' => false,
        'message' => $del->error
    ]);
    exit;
}

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
        FROM relatorios_despesas r
        LEFT JOIN clubs c ON c.id = r.club_id
        ORDER BY ano DESC, mes DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT r.*, c.nome AS clube_nome
        FROM relatorios_despesas r
        LEFT JOIN clubs c ON c.id = r.club_id
        WHERE r.club_id = ?
        ORDER BY ano DESC, mes DESC
    ");
    $stmt->bind_param("i", $user_club_id);
    $stmt->execute();
    $r = $stmt->get_result();
}
while ($row = $r->fetch_assoc()) $relatorios[] = $row;

/* Buscar clubes para admin */
$clubes = [];
if ($isAdminPrincipal) {
    $cq = $conn->query("SELECT id, nome FROM clubs ORDER BY nome ASC");
    if ($cq) $clubes = $cq->fetch_all(MYSQLI_ASSOC);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_ajax'])) {

    header('Content-Type: application/json; charset=utf-8');

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID inválido.'
        ]);
        exit;
    }

    $stmt = $conn->prepare(
        "DELETE FROM relatorios_despesas WHERE id = ?"
    );

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {

        echo json_encode([
            'success' => true
        ]);

    } else {

        echo json_encode([
            'success' => false,
            'message' => $stmt->error
        ]);

    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Despesas - SportGes</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">

    <style>
        html, body { overflow-x: hidden; max-width: 100%; }

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

        .page-title p { color: #64748b; font-size: 14px; margin: 0; }

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
            box-shadow: 0 4px 12px rgba(34,197,94,0.3);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34,197,94,0.4);
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

        .relatorio-nome { font-weight: 600; color: #1e293b; font-size: 15px; }
        .relatorio-mes  { color: #64748b; font-size: 14px; }

        .relatorio-total-mini {
            font-weight: 700;
            color: #dc2626;
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

        .btn-ver:hover { background: #2563eb; }

        .btn-delete {
            background: #ef4444;
            border: none;
            color: white;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-delete:hover { background: #dc2626; }

        .print-btn {
            background: white;
            border: 2px solid #e5e7eb;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .print-btn:hover { background: #f8fafc; border-color: #3b82f6; }

        .relatorio-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: none;
            width: 100%;
            box-sizing: border-box;
        }

        .relatorio-card.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .relatorio-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 12px;
        }

        .relatorio-title  { font-size: 20px; font-weight: 700; color: #1e293b; }
        .relatorio-subtitle { font-size: 13px; color: #64748b; margin-top: 4px; }

        .total-box {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }

        .total-box-label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
        .total-box-valor { font-size: 32px; font-weight: 800; }

        .badge-purple {
            background: #8b5cf6;
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Status grid */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin: 20px 0;
        }

        .status-box {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .status-box.pendente  { background: #fef3c7; border-left: 5px solid #f59e0b; }
        .status-box.aprovada  { background: #d1fae5; border-left: 5px solid #10b981; }
        .status-box.rejeitada { background: #fee2e2; border-left: 5px solid #ef4444; }

        .status-numero { font-size: 26px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
        .status-label  { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }

        /* Categorias */
        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin: 20px 0;
        }

        .categoria-item {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }

        .categoria-item.pessoal        { border-left-color: #8b5cf6; }
        .categoria-item.infraestrutura { border-left-color: #f59e0b; }
        .categoria-item.equipamento    { border-left-color: #10b981; }
        .categoria-item.utilidades     { border-left-color: #06b6d4; }
        .categoria-item.transporte     { border-left-color: #ec4899; }
        .categoria-item.outras         { border-left-color: #6b7280; }

        .categoria-label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
        .categoria-valor { font-size: 18px; font-weight: 700; color: #1e293b; }

        /* Tabela de despesas */
        .despesas-tabela {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .despesas-tabela th {
            padding: 12px 16px;
            font-size: 13px;
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }

        .despesas-tabela td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .despesas-tabela tr:hover { background: #f8fafc; }

        .secao-titulo {
            font-size: 18px;
            font-weight: 700;
            margin: 30px 0 15px;
            color: #1e293b;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-aprovada  { background: #d1fae5; color: #065f46; }
        .badge-pendente  { background: #fed7aa; color: #92400e; }
        .badge-rejeitada { background: #fee2e2; color: #991b1b; }
        .badge-atrasado  { background: #fee2e2; color: #991b1b; }

        .observacoes-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; width: 100%; padding: 16px; }
            .page-header-top { flex-direction: column; align-items: stretch; }
            .page-header-top > div:last-child { display: flex; flex-direction: column; gap: 10px; }
            .btn-primary { width: 100%; justify-content: center; }
            .relatorio-linha { flex-direction: column; align-items: stretch; gap: 12px; }
            .relatorio-info  { width: 100%; }
            .relatorio-acoes { width: 100%; justify-content: flex-start; }
            .status-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .page-header  { padding: 16px; }
            .page-title h1 { font-size: 22px; }
            .relatorio-linha { padding: 12px 16px; }
            .relatorio-nome  { font-size: 14px; }
            .relatorio-mes   { font-size: 12px; }
            .categorias-grid { grid-template-columns: 1fr; }
        }

        @media print {
            body * { visibility: hidden; }
            .print-break { page-break-before: always !important; }
            .relatorio-card.active, .relatorio-card.active * { visibility: visible; }
            .relatorio-card.active {
                position: absolute; left: 0; top: 0;
                width: 100%; padding: 20mm;
            }
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #000;
            }
            .print-header h1 { margin: 0; font-size: 26px; }
            .print-header p  { margin: 0; font-size: 14px; }
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
            <h1><i class="bx bx-trending-down"></i> Relatórios de Despesas</h1>
            <p>Consulta e gere relatórios mensais de despesas do clube</p>
        </div>

        <div style="display: flex; gap: 12px;">
            <a href="javascript:history.back()" class="btn-primary"
               style="text-decoration:none;background:linear-gradient(135deg,#64748b,#475569);">
                <i class='bx bx-arrow-back'></i> Voltar
            </a>

            <button type="button" onclick="atualizarRelatorios()" class="btn-primary"
        style="background:linear-gradient(135deg,#06b6d4,#0891b2);">
    <i class='bx bx-refresh'></i> Atualizar Relatórios
</button>

            <button type="button" id="btnAtualizarRelatorios" class="btn-primary"
        style="background:linear-gradient(135deg,#06b6d4,#0891b2);">
    <i class='bx bx-refresh'></i> Atualizar Relatórios
</button>
        </div>
    </div>
</div>

<!-- Formulário novo relatório -->
<div id="formNovoRelatorio" class="form-section" style="display:none;">
    <h2>Gerar Relatório Mensal de Despesas</h2>
    <form method="POST">
        <div class="form-grid">

            <div class="form-group">
                <label class="form-label">Mês</label>
                <select name="mes" class="form-select" required>
                    <?php foreach ($meses as $num => $nome): ?>
                        <option value="<?= $num ?>" <?= $num == date('n') ? 'selected' : '' ?>>
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
                    for ($a = $ano_inicio; $a >= 2023; $a--): ?>
                        <option value="<?= $a ?>"><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php if ($isAdminPrincipal && !empty($clubes)): ?>
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

<!-- Lista de relatórios -->
<div class="relatorios-lista">

<?php if (empty($relatorios)): ?>
    <div class="empty-state">
        <i class="bx bx-file" style="font-size:64px;"></i>
        <h3>Nenhum relatório disponível</h3>
        <p>Gera o primeiro relatório mensal para começar</p>
    </div>
<?php else: ?>

<?php foreach ($relatorios as $rel):
    $mesLabel     = $meses[$rel['mes']] ?? $rel['mes'];
    $despesasMes  = getDespesasDetalhadas($conn, $rel['mes'], $rel['ano'], $rel['club_id'], $hasClubColumn, $colunaEstado, $hasPlayerColumn, $hasStaffColumn);

    /* Calcular totais em tempo real */
    $numPendentes = 0; $numAprovadas = 0; $numRejeitadas = 0;
    $totalDespesas = 0;
    $totaisCat  = ['pessoal'=>0,'infraestrutura'=>0,'equipamento'=>0,'utilidades'=>0,'transporte'=>0,'outras'=>0];

    foreach ($despesasMes as &$d) {
        $estadoLower = strtolower(trim($d['estado'] ?? ''));
        $isPast      = !empty($d['data']) && strtotime($d['data']) < strtotime(date('Y-m-01'));

        if ($isPast && in_array($estadoLower, ['pendente','pending',''])) {
            $estadoLower  = 'atrasado';
            $d['estado']  = 'atrasado';
        }

        $valor = floatval($d['valor'] ?? 0);
        $totalDespesas += $valor;

        $cat = strtolower(trim($d['categoria'] ?? ''));
        if (isset($totaisCat[$cat])) $totaisCat[$cat] += $valor;
        else $totaisCat['outras'] += $valor;

        if (in_array($estadoLower, ['aprovada','aprovado','pago','paga','paid'])) $numAprovadas++;
        elseif (in_array($estadoLower, ['rejeitada','rejeitado','rejected','atrasado'])) $numRejeitadas++;
        else $numPendentes++;
    }
    unset($d);
    $numDespesas = count($despesasMes);
?>

    <div class="relatorio-linha" onclick="toggleRelatorio(<?= $rel['id'] ?>)">
        <div class="relatorio-info">
            <i class="bx bx-file" style="font-size:24px;color:#64748b;"></i>
            <div>
                <div class="relatorio-nome">
                    Relatório de <?= $mesLabel ?> <?= $rel['ano'] ?>
                    <?php if ($isAdminPrincipal && !empty($rel['clube_nome'])): ?>
                        <span class="badge-purple"><?= htmlspecialchars($rel['clube_nome']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="relatorio-mes">
                    <?= $numDespesas ?> despesas •
                    <?= $numAprovadas ?> aprovadas •
                    Gerado em <?= date('d/m/Y', strtotime($rel['gerado_em'])) ?>
                </div>
            </div>
        </div>

        <div class="relatorio-acoes" onclick="event.stopPropagation()">
            <div class="relatorio-total-mini">
                <?= number_format($totalDespesas, 2, ',', '.') ?> €
            </div>

            <button onclick="toggleRelatorio(<?= $rel['id'] ?>);event.stopPropagation();" class="btn-ver">
                <i class="bx bx-show"></i> Ver
            </button>

            <button onclick="imprimirRelatorio(<?= $rel['id'] ?>);event.stopPropagation();" class="print-btn">
                <i class="bx bx-printer"></i>
            </button>

            <button
                class="btn-delete btn-eliminar-relatorio"
                data-id="<?= $rel['id'] ?>"
                data-nome="Relatório de <?= $mesLabel ?> <?= $rel['ano'] ?>"
                onclick="event.stopPropagation()">
                <i class="bx bx-trash"></i>
            </button>
        </div>
    </div>

    <div class="relatorio-card" id="relatorio-<?= $rel['id'] ?>">

        <div class="print-header" style="display:none;">
            <h1>Relatório de Despesas</h1>
            <p><?= $mesLabel ?> de <?= $rel['ano'] ?></p>
            <?php if ($isAdminPrincipal && !empty($rel['clube_nome'])): ?>
                <p><strong><?= htmlspecialchars($rel['clube_nome']) ?></strong></p>
            <?php endif; ?>
        </div>

        <div class="relatorio-header">
            <div>
                <div class="relatorio-title">
                    <i class='bx bx-calendar'></i>
                    Relatório de <?= $mesLabel ?> <?= $rel['ano'] ?>
                </div>
                <div class="relatorio-subtitle">
                    Gerado em <?= date('d/m/Y H:i', strtotime($rel['gerado_em'])) ?>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <button onclick="imprimirRelatorio(<?= $rel['id'] ?>)" class="print-btn">
                    <i class="bx bx-printer"></i> Imprimir
                </button>
                <button onclick="toggleRelatorio(<?= $rel['id'] ?>)" class="btn-ver" style="background:#ef4444;">
                    <i class='bx bx-x'></i> Fechar
                </button>
            </div>
        </div>

        <div class="total-box">
            <div class="total-box-label">TOTAL DE DESPESAS</div>
            <div class="total-box-valor"><?= number_format($totalDespesas, 2, ',', '.') ?> €</div>
            <small style="opacity:.85;"><?= $numDespesas ?> despesas registadas</small>
        </div>

        <!-- Estado das despesas -->
        <div class="status-grid">
            <div class="status-box pendente">
                <div class="status-numero"><?= $numPendentes ?></div>
                <div class="status-label">Pendentes</div>
            </div>
            <div class="status-box aprovada">
                <div class="status-numero"><?= $numAprovadas ?></div>
                <div class="status-label">Aprovadas</div>
            </div>
            <div class="status-box rejeitada">
                <div class="status-numero"><?= $numRejeitadas ?></div>
                <div class="status-label">Rejeitadas / Atrasadas</div>
            </div>
        </div>

        <!-- Categorias -->
        <div class="categorias-grid">
            <div class="categoria-item pessoal">
                <div class="categoria-label">Pessoal</div>
                <div class="categoria-valor"><?= number_format($totaisCat['pessoal'],2,',','.') ?> €</div>
            </div>
            <div class="categoria-item infraestrutura">
                <div class="categoria-label">Infraestrutura</div>
                <div class="categoria-valor"><?= number_format($totaisCat['infraestrutura'],2,',','.') ?> €</div>
            </div>
            <div class="categoria-item equipamento">
                <div class="categoria-label">Equipamento</div>
                <div class="categoria-valor"><?= number_format($totaisCat['equipamento'],2,',','.') ?> €</div>
            </div>
            <div class="categoria-item utilidades">
                <div class="categoria-label">Utilidades</div>
                <div class="categoria-valor"><?= number_format($totaisCat['utilidades'],2,',','.') ?> €</div>
            </div>
            <div class="categoria-item transporte">
                <div class="categoria-label">Transporte</div>
                <div class="categoria-valor"><?= number_format($totaisCat['transporte'],2,',','.') ?> €</div>
            </div>
            <div class="categoria-item outras">
                <div class="categoria-label">Outras</div>
                <div class="categoria-valor"><?= number_format($totaisCat['outras'],2,',','.') ?> €</div>
            </div>
        </div>

        <?php if (!empty($rel['observacoes'])): ?>
            <div class="observacoes-box">
                <strong>Observações:</strong><br>
                <?= nl2br(htmlspecialchars($rel['observacoes'])) ?>
            </div>
        <?php endif; ?>

        <!-- Tabela de despesas -->
        <h3 class="secao-titulo print-break">Lista de Despesas do Mês</h3>

        <?php if (!empty($despesasMes)): ?>
        <table class="despesas-tabela">
            <thead>
                <tr>
                    <th>Descrição / Responsável</th>
                    <th>Categoria</th>
                    <th>Tipo</th>
                    <th style="text-align:center;">Estado</th>
                    <th style="text-align:right;">Valor</th>
                    <th style="text-align:center;">Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($despesasMes as $d):
                    $nome = $d['descricao'] ?? '-';
                    if (!empty($d['player_id']) && !empty($d['jogador_nome'])) $nome = $d['jogador_nome'];
                    elseif (!empty($d['staff_id']) && !empty($d['staff_nome']))  $nome = $d['staff_nome'];

                    $estadoLower = strtolower(trim($d['estado'] ?? ''));
                    if (in_array($estadoLower, ['aprovada','aprovado','pago','paga','paid'])) {
                        $classeEstado = 'badge-aprovada';
                        $estadoLabel  = 'APROVADA';
                    } elseif (in_array($estadoLower, ['rejeitada','rejeitado','rejected'])) {
                        $classeEstado = 'badge-rejeitada';
                        $estadoLabel  = 'REJEITADA';
                    } elseif ($estadoLower === 'atrasado') {
                        $classeEstado = 'badge-atrasado';
                        $estadoLabel  = 'ATRASADO';
                    } else {
                        $classeEstado = 'badge-pendente';
                        $estadoLabel  = 'PENDENTE';
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($nome) ?></td>
                    <td><?= htmlspecialchars($d['categoria'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['tipo'] ?? '-') ?></td>
                    <td style="text-align:center;">
                        <span class="badge-status <?= $classeEstado ?>"><?= $estadoLabel ?></span>
                    </td>
                    <td style="text-align:right;">
                        <strong><?= number_format($d['valor'],2,',','.') ?> €</strong>
                    </td>
                    <td style="text-align:center;">
                        <?= !empty($d['data']) ? date('d/m/Y', strtotime($d['data'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="secao-titulo" style="border:none;padding:0;">Nenhuma despesa registada neste mês.</div>
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

/* Eliminar com toast.confirm */
document.querySelectorAll('.btn-eliminar-relatorio').forEach(btn => {
    btn.addEventListener('click', function (e) {
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

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'eliminar_ajax=1&id=' + encodeURIComponent(id)
    })
    .then(async response => {

        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (e) {

            console.error("Resposta do servidor:");
            console.log(text);

            throw new Error(
                "O servidor devolveu HTML em vez de JSON. Verifica a consola."
            );
        }
    })
    .then(data => {

        if (data.success) {

            toast.success(
                'Eliminado!',
                `"${nome}" eliminado com sucesso`
            );

            const btn = document.querySelector(
                `.btn-eliminar-relatorio[data-id="${id}"]`
            );

            if (btn) {

                const linha = btn.closest('.relatorio-linha');
                const card  = document.getElementById('relatorio-' + id);

                if (linha) {

                    linha.style.transition =
                        'opacity 0.3s ease, transform 0.3s ease';

                    linha.style.opacity = '0';
                    linha.style.transform = 'translateX(20px)';

                    setTimeout(() => {

                        linha.remove();

                        if (card) {
                            card.remove();
                        }

                        if (
                            document.querySelectorAll('.relatorio-linha')
                                .length === 0
                        ) {
                            location.reload();
                        }

                    }, 300);
                }

            } else {

                setTimeout(() => {
                    location.reload();
                }, 1000);

            }

        } else {

            toast.error(
                'Erro!',
                data.message || 'Não foi possível eliminar o relatório.'
            );

        }

    })
    .catch(error => {

        console.error(error);

        toast.error(
            'Erro!',
            error.message || 'Erro ao eliminar o relatório.'
        );

    });
}

document.getElementById('btnAtualizarRelatorios').addEventListener('click', function () {

    toast.info('A atualizar relatórios...', 'Aguarde');

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'atualizar_relatorios=1'
    })
    .then(async (res) => {
        const text = await res.text();

        try {
            return JSON.parse(text);
        } catch (e) {
            // não é JSON, mas pode ter funcionado
            return { success: true };
        }
    })
    .then(() => {

        toast.success('Atualizado!', 'Relatórios atualizados com sucesso');

        setTimeout(() => {
            location.reload();
        }, 800);

    })
    .catch(() => {
        toast.error('Erro!', 'Não foi possível atualizar os relatórios');
    });

});
function atualizarRelatorios() {

    toast.info('A atualizar...', 'Aguarde um momento');

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'atualizar_relatorios=1'
    })
    .then(res => res.json())
    .then(data => {

        if (data.success) {
            toast.success(
                'Atualizado!',
                data.message + ' (' + data.count + ' relatórios)'
            );

            // opcional: refresh silencioso dos dados visuais
            setTimeout(() => {
                location.reload(); // se quiseres update total da UI
            }, 800);

        } else {
            toast.error('Erro', data.message || 'Falha ao atualizar');
        }

    })
    .catch(err => {
        console.error(err);
        toast.error('Erro', 'Erro ao atualizar relatórios');
    });
}
</script>

</body>
</html>