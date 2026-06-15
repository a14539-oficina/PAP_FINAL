<?php
session_start();
require('../../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_club_id = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($user_club_id <= 0 && $user_id != 1) {
    die("Erro: Utilizador sem clube associado. Contacte o administrador.");
}

$isAdminPrincipal = ($user_id == 7 && $user_club_id <= 0);
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['1', '2'])) {
    $_SESSION['erro'] = "Acesso negado!";
    header("Location: ../../dashboard.php");
    exit;
}

$checkColumn     = $conn->query("SHOW COLUMNS FROM despesas LIKE 'club_id'");
$hasClubColumn   = ($checkColumn && $checkColumn->num_rows > 0);
$checkFotoColumn = $conn->query("SHOW COLUMNS FROM despesas LIKE 'foto_recibo'");
$hasFotoColumn   = ($checkFotoColumn && $checkFotoColumn->num_rows > 0);
$checkEstadoCol  = $conn->query("SHOW COLUMNS FROM despesas LIKE 'estado'");
$hasEstadoColumn = ($checkEstadoCol && $checkEstadoCol->num_rows > 0);
$checkStatusCol  = $conn->query("SHOW COLUMNS FROM despesas LIKE 'status'");
$hasStatusColumn = ($checkStatusCol && $checkStatusCol->num_rows > 0);
$checkPlayerCol  = $conn->query("SHOW COLUMNS FROM despesas LIKE 'player_id'");
$hasPlayerColumn = ($checkPlayerCol && $checkPlayerCol->num_rows > 0);
$checkStaffCol   = $conn->query("SHOW COLUMNS FROM despesas LIKE 'staff_id'");
$hasStaffColumn  = ($checkStaffCol && $checkStaffCol->num_rows > 0);

$hojePrimeiroDia = date('Y-m-01');

try {
    if ($hasEstadoColumn) {
        if ($hasClubColumn && !$isAdminPrincipal) {
            $s = $conn->prepare("UPDATE despesas SET estado='Atrasado' WHERE LOWER(TRIM(estado))='pendente' AND data < ? AND club_id = ?");
            $s->bind_param("si", $hojePrimeiroDia, $user_club_id); $s->execute(); $s->close();
            $s2 = $conn->prepare("UPDATE despesas SET estado='Atrasado' WHERE LOWER(TRIM(estado))='pendente' AND data < ? AND club_id IS NULL");
            $s2->bind_param("s", $hojePrimeiroDia); $s2->execute(); $s2->close();
        } else {
            $s = $conn->prepare("UPDATE despesas SET estado='Atrasado' WHERE LOWER(TRIM(estado))='pendente' AND data < ?");
            $s->bind_param("s", $hojePrimeiroDia); $s->execute(); $s->close();
        }
    }
} catch (Exception $e) {}

$categorias = ['Todas', 'Pessoal', 'Infraestrutura', 'Equipamento', 'Utilidades', 'Transporte', 'Outras'];
$uploadDir  = '../../uploads/despesas/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

// ── ALTERAR ESTADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_estado'])) {
    $did = intval($_POST['despesa_id']);
    $novo_estado = $_POST['novo_estado'];
    if (in_array($novo_estado, ['Pendente', 'Pago', 'Atrasado'])) {
        try {
            if ($hasClubColumn && !$isAdminPrincipal) {
                $s = $conn->prepare("UPDATE despesas SET estado=? WHERE id=? AND club_id=?");
                $s->bind_param("sii", $novo_estado, $did, $user_club_id);
            } else {
                $s = $conn->prepare("UPDATE despesas SET estado=? WHERE id=?");
                $s->bind_param("si", $novo_estado, $did);
            }
            if ($s->execute()) $_SESSION['sucesso'] = "Estado atualizado para: $novo_estado";
            $s->close();
        } catch (Exception $e) { $_SESSION['erro'] = $e->getMessage(); }
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?mes='.($_GET['mes'] ?? date('Y-m')).(isset($_GET['categoria']) ? '&categoria='.urlencode($_GET['categoria']) : '')); exit;
}

// ── ADICIONAR DESPESA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
    $descricao = trim($_POST['descricao']);
    $valor     = floatval($_POST['valor']);
    $categoria = trim($_POST['categoria']);
    $data      = $_POST['data'];
    $tipo      = $_POST['tipo'];
    $fotoRecibo = null;
    if (isset($_FILES['foto_recibo']) && $_FILES['foto_recibo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto_recibo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','pdf'])) {
            $novo = uniqid('recibo_', true).'.'.$ext;
            if (move_uploaded_file($_FILES['foto_recibo']['tmp_name'], $uploadDir.$novo)) $fotoRecibo = $novo;
        }
    }
    if ($descricao && $valor > 0 && $categoria && $data) {
        try {
            $cid = $isAdminPrincipal && isset($_POST['club_id']) ? intval($_POST['club_id']) : $user_club_id;
            if ($hasClubColumn && $hasFotoColumn && $hasEstadoColumn) {
                $s = $conn->prepare("INSERT INTO despesas (descricao,valor,categoria,data,tipo,club_id,foto_recibo,estado) VALUES (?,?,?,?,?,?,?,'Pendente')");
                $s->bind_param("sdsssis",$descricao,$valor,$categoria,$data,$tipo,$cid,$fotoRecibo);
            } elseif ($hasClubColumn && $hasEstadoColumn) {
                $s = $conn->prepare("INSERT INTO despesas (descricao,valor,categoria,data,tipo,club_id,estado) VALUES (?,?,?,?,?,?,'Pendente')");
                $s->bind_param("sdsssi",$descricao,$valor,$categoria,$data,$tipo,$cid);
            } elseif ($hasClubColumn) {
                $s = $conn->prepare("INSERT INTO despesas (descricao,valor,categoria,data,tipo,club_id) VALUES (?,?,?,?,?,?)");
                $s->bind_param("sdsssi",$descricao,$valor,$categoria,$data,$tipo,$cid);
            } else {
                $s = $conn->prepare("INSERT INTO despesas (descricao,valor,categoria,data,tipo) VALUES (?,?,?,?,?)");
                $s->bind_param("sdsss",$descricao,$valor,$categoria,$data,$tipo);
            }
            if ($s->execute()) $_SESSION['sucesso'] = "Despesa adicionada!";
            else $_SESSION['erro'] = $s->error;
            $s->close();
        } catch (Exception $e) { $_SESSION['erro'] = $e->getMessage(); }
    } else { $_SESSION['erro'] = "Preencha todos os campos!"; }
    header('Location: '.$_SERVER['PHP_SELF'].'?mes='.date('Y-m', strtotime($data))); exit;
}

// ── ELIMINAR DESPESA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $id = intval($_POST['id']);
    if ($id <= 0) { $_SESSION['erro'] = "ID inválido!"; }
    else {
        try {
            if ($hasPlayerColumn) {
                $cs = $conn->prepare("SELECT player_id FROM despesas WHERE id=?");
                $cs->bind_param("i",$id); $cs->execute();
                $dep = $cs->get_result()->fetch_assoc(); $cs->close();
                if ($dep && $dep['player_id']) { $_SESSION['erro'] = "Não é possível eliminar despesas de contratos!"; header('Location: '.$_SERVER['PHP_SELF'].'?mes='.($_GET['mes'] ?? date('Y-m'))); exit; }
            }
            if ($hasFotoColumn) {
                $fs = $conn->prepare("SELECT foto_recibo FROM despesas WHERE id=?");
                $fs->bind_param("i",$id); $fs->execute();
                $fr = $fs->get_result()->fetch_assoc(); $fs->close();
                if ($fr && $fr['foto_recibo'] && file_exists($uploadDir.$fr['foto_recibo'])) unlink($uploadDir.$fr['foto_recibo']);
            }
            if ($hasClubColumn && !$isAdminPrincipal) { $s = $conn->prepare("DELETE FROM despesas WHERE id=? AND club_id=?"); $s->bind_param("ii",$id,$user_club_id); }
            else { $s = $conn->prepare("DELETE FROM despesas WHERE id=?"); $s->bind_param("i",$id); }
            if ($s->execute()) $_SESSION['sucesso'] = $s->affected_rows > 0 ? "Despesa eliminada!" : "Não encontrada ou sem permissão!";
            else $_SESSION['erro'] = $s->error;
            $s->close();
        } catch (Exception $e) { $_SESSION['erro'] = $e->getMessage(); }
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?mes='.($_GET['mes'] ?? date('Y-m'))); exit;
}

// ── FILTRAR
$mesAtual        = $_GET['mes'] ?? date('Y-m');
$filtroCategoria = $_GET['categoria'] ?? 'Todas';
$mostrarTodas    = ($mesAtual === 'todas');

try {
    $baseQ = $hasPlayerColumn
        ? "SELECT d.*, c.nome as clube_nome, CONCAT(p.primeiro_nome,' ',p.ultimo_nome) as jogador_nome FROM despesas d LEFT JOIN clubs c ON d.club_id=c.id LEFT JOIN players p ON d.player_id=p.id WHERE 1=1"
        : "SELECT d.*, c.nome as clube_nome FROM despesas d LEFT JOIN clubs c ON d.club_id=c.id WHERE 1=1";
    $params = []; $types = '';
    if (!$mostrarTodas) {
        $p1 = $mesAtual.'-01'; $p2 = date('Y-m-t', strtotime($p1));
        $baseQ .= " AND d.data BETWEEN ? AND ?"; $params[] = $p1; $params[] = $p2; $types .= 'ss';
    }
    if ($filtroCategoria !== 'Todas') { $baseQ .= " AND d.categoria=?"; $params[] = $filtroCategoria; $types .= 's'; }
    if ($hasClubColumn && !$isAdminPrincipal) { $baseQ .= " AND d.club_id=?"; $params[] = $user_club_id; $types .= 'i'; }
    $baseQ .= " ORDER BY d.data DESC";
    $s = $conn->prepare($baseQ);
    if (!empty($params)) $s->bind_param($types, ...$params);
    $s->execute(); $despesasFiltradas = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
} catch (Exception $e) { $_SESSION['erro'] = $e->getMessage(); $despesasFiltradas = []; }

// ── TOTAIS
$totalGeralDespesas = $totalDespesasPagas = $totalPendente = $totalAtrasado = 0;
try {
    $primeiroDia = $mostrarTodas ? null : $mesAtual.'-01';
    $ultimoDia   = $mostrarTodas ? null : date('Y-m-t', strtotime($primeiroDia));

    function qTotalD($conn,$extra,$mostrarTodas,$hasClub,$isAdmin,$p1,$p2,$cid){
        $q = "SELECT COALESCE(SUM(valor),0) AS t FROM despesas WHERE 1=1";
        if (!$mostrarTodas) $q .= " AND data BETWEEN ? AND ?";
        if ($extra) $q .= " AND ".$extra;
        if ($hasClub && !$isAdmin) $q .= " AND club_id=?";
        $s = $conn->prepare($q);
        $ps=[]; $ts='';
        if (!$mostrarTodas) { $ps[]=$p1; $ps[]=$p2; $ts.='ss'; }
        if ($hasClub && !$isAdmin) { $ps[]=$cid; $ts.='i'; }
        if (!empty($ps)) $s->bind_param($ts,...$ps);
        $s->execute(); $r=$s->get_result()->fetch_assoc()['t']; $s->close(); return $r;
    }
    $totalGeralDespesas = qTotalD($conn,null,$mostrarTodas,$hasClubColumn,$isAdminPrincipal,$primeiroDia,$ultimoDia,$user_club_id);
    $totalDespesasPagas = qTotalD($conn,"estado='Pago'",$mostrarTodas,$hasClubColumn,$isAdminPrincipal,$primeiroDia,$ultimoDia,$user_club_id);
    $totalPendente      = qTotalD($conn,"estado='Pendente'",$mostrarTodas,$hasClubColumn,$isAdminPrincipal,$primeiroDia,$ultimoDia,$user_club_id);
    $totalAtrasado      = qTotalD($conn,"estado='Atrasado'",$mostrarTodas,$hasClubColumn,$isAdminPrincipal,$primeiroDia,$ultimoDia,$user_club_id);
} catch (Exception $e) {}

$clubes = [];
if ($isAdminPrincipal) {
    $cr = $conn->query("SELECT id, nome FROM clubs ORDER BY nome ASC");
    if ($cr) $clubes = $cr->fetch_all(MYSQLI_ASSOC);
}

$mesesPT = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho',
            '07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
if ($mostrarTodas) { $mesExibicao = 'Todas as Despesas'; }
else { [$ano,$mes] = explode('-',$mesAtual); $mesExibicao = $mesesPT[$mes].' '.$ano; }
$mesInputValue = $mostrarTodas ? date('Y-m') : $mesAtual;

$clubNome = 'Meu Clube';
if (!$isAdminPrincipal && $user_club_id > 0) {
    $cr2 = $conn->query("SELECT nome FROM clubs WHERE id=$user_club_id");
    if ($cr2 && $row = $cr2->fetch_assoc()) $clubNome = $row['nome'];
}
$numAtrasadas = count(array_filter($despesasFiltradas, fn($d) => ($d['estado'] ?? '') === 'Atrasado'));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestão de Despesas - SportGes</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="../../public/css/style.css">
<style>
html,body{overflow-x:hidden;max-width:100%;background:#f1f5f9}
.main-content{margin-left:240px;padding:28px 24px;width:calc(100% - 240px);box-sizing:border-box;min-height:100vh}

/* PAGE HEADER CARD */
.page-header-card{background:#fff;border-radius:16px;padding:24px 26px 20px;box-shadow:0 1px 8px rgba(0,0,0,.06);margin-bottom:22px}
.page-title-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:14px;flex-wrap:wrap}
.page-title-row h1{font-size:24px;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:10px}
.page-title-row h1 i{color:#ef4444;font-size:26px}
.header-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;flex-shrink:0}

.btn-relatorios{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:600;background:#7c3aed;color:#fff;border:none;cursor:pointer;text-decoration:none;transition:background .2s}
.btn-relatorios:hover{background:#6d28d9;color:#fff}
.btn-relatorios i{font-size:17px}
.btn-nova{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:600;background:#22c55e;color:#fff;border:none;cursor:pointer;text-decoration:none;transition:background .2s}
.btn-nova:hover{background:#16a34a;color:#fff}
.btn-nova i{font-size:17px}

/* MES BAR */
.mes-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:11px 16px;margin-bottom:20px}
.mes-bar label{font-weight:700;font-size:14px;color:#374151;display:flex;align-items:center;gap:6px;white-space:nowrap}
.mes-bar label i{font-size:17px;color:#3b82f6}
.mes-bar input[type=month]{padding:7px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;font-weight:600;color:#334155;background:#fff;cursor:pointer;transition:border-color .2s}
.mes-bar input[type=month]:focus{outline:none;border-color:#3b82f6}
.mes-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;background:#dbeafe;border:1.5px solid #93c5fd;border-radius:8px;font-size:13px;font-weight:700;color:#1e40af}
.mes-badge i{font-size:15px}
.btn-todas{display:inline-flex;align-items:center;gap:7px;padding:7px 15px;border-radius:8px;font-size:13px;font-weight:700;background:#f1f5f9;border:1.5px solid #cbd5e1;color:#334155;text-decoration:none;transition:all .2s;margin-left:auto}
.btn-todas:hover,.btn-todas.active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.btn-todas i{font-size:15px}

/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:0}
.stat-card{border-radius:14px;padding:20px 18px;color:#fff;position:relative;overflow:hidden;min-height:90px}
.stat-card::after{content:'';position:absolute;right:-16px;bottom:-16px;width:80px;height:80px;background:rgba(255,255,255,.12);border-radius:50%}
.stat-card .sc-label{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.88;margin-bottom:8px}
.stat-card .sc-value{font-size:26px;font-weight:800;line-height:1}
.stat-card .sc-sub{font-size:11px;opacity:.78;margin-top:5px}
.sc-blue{
    background: linear-gradient(135deg,#3b82f6,#2563eb) !important;
    color:#fff !important;
}

.sc-green{
    background: linear-gradient(135deg,#22c55e,#16a34a) !important;
    color:#fff !important;
}

.sc-orange{
    background: linear-gradient(135deg,#f59e0b,#d97706) !important;
    color:#fff !important;
}

.sc-red{
    background: linear-gradient(135deg,#ef4444,#dc2626) !important;
    color:#fff !important;
}
/* FILTER BAR */
.filter-bar{background:#fff;border-radius:14px;padding:15px 20px;box-shadow:0 1px 6px rgba(0,0,0,.06);margin-bottom:18px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.filter-bar p{font-size:13px;font-weight:600;color:#64748b;margin:0 4px 0 0;white-space:nowrap}
.filter-pill{padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;background:#f1f5f9;color:#475569;border:1.5px solid transparent;text-decoration:none;transition:all .2s}
.filter-pill:hover{background:#e2e8f0;color:#1e293b}
.filter-pill.active{background:#2563eb;color:#fff;border-color:#2563eb}

/* TABLE */
.table-wrap{background:#fff;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:hidden}
.table-wrap table{width:100%;border-collapse:collapse}
.table-wrap thead tr{background:#f8fafc;border-bottom:2px solid #e2e8f0}
.table-wrap thead th{padding:13px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
.table-wrap tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s}
.table-wrap tbody tr:last-child{border-bottom:none}
.table-wrap tbody tr:hover{background:#f8fafc}
.table-wrap tbody td{padding:13px 16px;font-size:14px;color:#1e293b;vertical-align:middle}
.linha-contrato{background:#faf5ff!important;border-left:3px solid #8b5cf6}
.linha-contrato:hover{background:#f3e8ff!important}

.bdg{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
.bdg-cat{background:#dbeafe;color:#1e40af}
.bdg-mensal{background:#fed7aa;color:#9a3412}
.bdg-unica{background:#dcfce7;color:#166534}
.bdg-purple{background:#ede9fe;color:#5b21b6}

.badge-estado{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700}
.badge-estado i{font-size:13px}
.be-pago    {background:#dcfce7;color:#166534}
.be-pendente{background:#fef9c3;color:#854d0e}
.be-atrasado{background:#fee2e2;color:#991b1b}
.val-despesa{color:#dc2626;font-weight:700;white-space:nowrap}

.acoes{display:flex;gap:6px;justify-content:center}
.btn-tbl{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:none;cursor:pointer;font-size:15px;transition:background .2s;color:#fff}
.btn-tbl-edit{background:#3b82f6}.btn-tbl-edit:hover{background:#2563eb}
.btn-tbl-del{background:#ef4444}.btn-tbl-del:hover{background:#dc2626}

.empty-state{padding:56px 20px;text-align:center}
.empty-state i{font-size:52px;color:#cbd5e1;display:block;margin-bottom:14px}
.empty-state h3{color:#475569;font-weight:700;margin-bottom:4px}
.empty-state p{color:#94a3b8;font-size:13px}

/* FORM */
.form-section{display:none;background:#fff;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.06);padding:22px 26px;margin-bottom:20px}
.form-section.active{display:block}
.form-section h2{font-size:17px;font-weight:700;color:#0f172a;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group.full{grid-column:1/-1}
.form-label{font-size:13px;font-weight:600;color:#374151}
.form-input,.form-select{padding:9px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:14px;color:#1e293b;background:#fff;transition:border-color .2s}
.form-input:focus,.form-select:focus{outline:none;border-color:#3b82f6}
.form-buttons{display:flex;gap:10px;margin-top:18px}
.btn-submit{padding:10px 22px;background:#2563eb;color:#fff;border:none;border-radius:9px;font-weight:700;cursor:pointer;font-size:14px;transition:background .2s}
.btn-submit:hover{background:#1d4ed8}
.btn-cancel{padding:10px 22px;background:#f1f5f9;color:#475569;border:none;border-radius:9px;font-weight:600;cursor:pointer;font-size:14px;transition:background .2s}
.btn-cancel:hover{background:#e2e8f0}

/* ALERTS */
.alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:10px;margin-bottom:14px;font-size:14px;font-weight:600}
.alert i{font-size:19px}
.alert-success{background:#dcfce7;color:#166534}
.alert-error{background:#fee2e2;color:#991b1b}

/* MODALS */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:30px;max-width:410px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.modal-box h3{font-size:19px;font-weight:700;color:#0f172a;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.estado-opts{display:flex;flex-direction:column;gap:9px;margin-bottom:22px}
.estado-opt{display:flex;align-items:center;gap:11px;padding:12px 15px;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:all .2s;font-weight:600;font-size:14px}
.estado-opt:hover{border-color:#3b82f6;transform:translateX(3px)}
.estado-opt input{width:16px;height:16px;cursor:pointer}
.opt-pendente{color:#92400e}.opt-pago{color:#065f46}.opt-atrasado{color:#991b1b}
.modal-actions{display:flex;gap:10px}
.modal-actions button{flex:1;padding:11px;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:14px}
.btn-m-cancel{background:#f1f5f9;color:#64748b}.btn-m-cancel:hover{background:#e2e8f0}
.btn-m-confirm{background:#2563eb;color:#fff}.btn-m-confirm:hover{background:#1d4ed8}

.modal-foto{display:none;position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:9999;align-items:center;justify-content:center}
.modal-foto.active{display:flex}
.modal-foto img{max-width:90%;max-height:90%;border-radius:8px}
.modal-close{position:absolute;top:20px;right:34px;color:#fff;font-size:36px;cursor:pointer;font-weight:bold}

/* RESPONSIVE */
@media(max-width:1024px){.main-content{margin-left:0;width:100%;padding:14px}.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){
  .stats-grid{grid-template-columns:1fr 1fr;gap:9px}
  .stat-card .sc-value{font-size:20px}
  .form-grid{grid-template-columns:1fr}
  .table-wrap thead{display:none}
  .table-wrap tbody tr{display:block;margin-bottom:12px;border-radius:10px;padding:12px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  .table-wrap tbody td{display:block;padding:5px 0;font-size:13px;border:none;text-align:left!important}
  .table-wrap tbody td::before{content:attr(data-label)": ";font-weight:700;color:#64748b}
  .acoes{justify-content:flex-start}
  .header-actions{width:100%}
  .btn-relatorios,.btn-nova{flex:1;justify-content:center}
}
</style>
</head>
<body>

<?php require('../../includes/sidebar.php'); ?>

<div class="main-content">

<?php if (isset($_SESSION['sucesso'])): ?>
<div class="alert alert-success"><i class='bx bx-check-circle'></i><?= htmlspecialchars($_SESSION['sucesso']) ?></div>
<?php unset($_SESSION['sucesso']); endif; ?>
<?php if (isset($_SESSION['erro'])): ?>
<div class="alert alert-error"><i class='bx bx-error-circle'></i><?= htmlspecialchars($_SESSION['erro']) ?></div>
<?php unset($_SESSION['erro']); endif; ?>

<div class="page-header-card">
  <div class="page-title-row" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:20px;">
    <h1 style="font-size:24px; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:10px;">
      <i class='bx bx-wallet' style="color:#ef4444; font-size:26px;"></i>
      Gestão de Despesas<?= $isAdminPrincipal ? '' : ' - '.htmlspecialchars($clubNome) ?>
    </h1>
    <div class="header-actions" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; flex-shrink:0;">
      <a href="relatorios.php" class="btn-relatorios"><i class='bx bx-bar-chart-alt-2'></i> Ver Relatórios</a>
      <button onclick="toggleForm()" class="btn-nova"><i class='bx bx-plus'></i> Nova Despesa</button>
    </div>
  </div>

  <div class="mes-bar">
    <label><i class='bx bx-calendar'></i> Mês:</label>
    <input type="month" id="mesSelecionado" value="<?= htmlspecialchars($mesInputValue) ?>" onchange="mudarMes()">
    <div class="mes-badge"><i class='bx bx-time-five'></i><?= htmlspecialchars($mesExibicao) ?></div>
    <a href="?mes=todas<?= isset($_GET['categoria']) ? '&categoria='.urlencode($_GET['categoria']) : '' ?>"
       class="btn-todas <?= $mostrarTodas ? 'active' : '' ?>">
      <i class='bx bx-list-ul'></i> Todas as Despesas
    </a>
  </div>

  <div class="stats-grid">
    <div class="stat-card sc-blue">
      <div class="sc-label">Total de Despesas</div>
      <div class="sc-value"><?= number_format($totalGeralDespesas,2,',','.') ?> €</div>
    </div>
    <div class="stat-card sc-green">
      <div class="sc-label">Despesas Pagas</div>
      <div class="sc-value"><?= number_format($totalDespesasPagas,2,',','.') ?> €</div>
    </div>
    <div class="stat-card sc-orange">
      <div class="sc-label">Pendentes</div>
      <div class="sc-value"><?= number_format($totalPendente,2,',','.') ?> €</div>
    </div>
    <div class="stat-card sc-red">
      <div class="sc-label">Atrasadas</div>
      <div class="sc-value"><?= number_format($totalAtrasado,2,',','.') ?> €</div>
      <div class="sc-sub"><?= $numAtrasadas ?> despesa(s)</div>
    </div>
  </div>
</div>

<div id="formNovaDespesa" class="form-section">
  <h2><i class='bx bx-plus-circle'></i> Adicionar Nova Despesa</h2>
  <form method="POST" enctype="multipart/form-data">
    <div class="form-grid">
      <div class="form-group full">
        <label class="form-label">Descrição</label>
        <input type="text" name="descricao" class="form-input" placeholder="Ex: Salários, Material, etc." required>
      </div>
      <div class="form-group">
        <label class="form-label">Valor (€)</label>
        <input type="number" name="valor" step="0.01" class="form-input" placeholder="0.00" required>
      </div>
      <div class="form-group">
        <label class="form-label">Data</label>
        <input type="date" name="data" class="form-input" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Categoria</label>
        <select name="categoria" class="form-select" required>
          <option value="Pessoal">Pessoal</option>
          <option value="Infraestrutura">Infraestrutura</option>
          <option value="Equipamento">Equipamento</option>
          <option value="Utilidades">Utilidades</option>
          <option value="Transporte">Transporte</option>
          <option value="Outras">Outras</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select" required>
          <option value="Única">Única</option>
          <option value="Mensal">Mensal</option>
        </select>
      </div>
      <?php if ($isAdminPrincipal && !empty($clubes)): ?>
      <div class="form-group">
        <label class="form-label">Clube</label>
        <select name="club_id" class="form-select" required>
          <option value="">Selecionar clube</option>
          <?php foreach ($clubes as $cl): ?>
            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
    <div class="form-buttons">
      <button type="submit" name="adicionar" class="btn-submit">Adicionar Despesa</button>
      <button type="button" onclick="toggleForm()" class="btn-cancel">Cancelar</button>
    </div>
  </form>
</div>

<div class="filter-bar">
  <p>Filtrar por categoria:</p>
  <?php foreach ($categorias as $cat): ?>
    <a href="?mes=<?= $mesAtual ?>&categoria=<?= urlencode($cat) ?>"
       class="filter-pill <?= $filtroCategoria === $cat ? 'active' : '' ?>">
      <?= htmlspecialchars($cat) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Descrição</th>
        <?php if ($isAdminPrincipal): ?><th>Clube</th><?php endif; ?>
        <th>Categoria</th>
        <th>Tipo / Status</th>
        <th>Data</th>
        <th style="text-align:right">Valor</th>
        <?php if ($hasEstadoColumn): ?><th style="text-align:center">Estado</th><?php endif; ?>
        <th style="text-align:center">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($despesasFiltradas)): ?>
        <tr><td colspan="<?= ($isAdminPrincipal?1:0)+($hasEstadoColumn?7:6) ?>">
          <div class="empty-state">
            <i class='bx bx-wallet'></i>
            <h3>Nenhuma despesa em <?= htmlspecialchars($mesExibicao) ?></h3>
            <p>Adiciona a primeira clicando em "Nova Despesa"</p>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($despesasFiltradas as $d):
          $estado  = $d['estado'] ?? 'Pendente';
          $beClass = $estado==='Pago' ? 'be-pago' : ($estado==='Atrasado' ? 'be-atrasado' : 'be-pendente');
          $beIcon  = $estado==='Pago' ? 'bx-check-circle' : ($estado==='Atrasado' ? 'bx-x-circle' : 'bx-time-five');
          $tipoClass = $d['tipo']==='Mensal' ? 'bdg-mensal' : 'bdg-unica';
          $isContrato = $hasPlayerColumn && !empty($d['player_id']);
        ?>
        <tr class="<?= $isContrato ? 'linha-contrato' : '' ?>">
          <td data-label="Descrição"><strong><?= htmlspecialchars($d['descricao']) ?></strong></td>
          <?php if ($isAdminPrincipal): ?>
          <td data-label="Clube"><span class="bdg bdg-purple"><?= htmlspecialchars($d['clube_nome'] ?? 'N/A') ?></span></td>
          <?php endif; ?>
          <td data-label="Categoria"><span class="bdg bdg-cat"><?= htmlspecialchars($d['categoria']) ?></span></td>
          <td data-label="Tipo/Status"><span class="bdg <?= $tipoClass ?>"><?= htmlspecialchars($d['tipo']) ?></span></td>
          <td data-label="Data"><?= date('d/m/Y', strtotime($d['data'])) ?></td>
          <td data-label="Valor" style="text-align:right" class="val-despesa"><?= number_format($d['valor'],2,',','.') ?> €</td>
          <?php if ($hasEstadoColumn): ?>
          <td data-label="Estado" style="text-align:center">
            <span class="badge-estado <?= $beClass ?>"><i class='bx <?= $beIcon ?>'></i><?= $estado ?></span>
          </td>
          <?php endif; ?>
          <td data-label="Ações" style="text-align:center">
            <div class="acoes">
              <button onclick="abrirModalEstado(<?= $d['id'] ?>, '<?= $estado ?>')" class="btn-tbl btn-tbl-edit" title="Editar Estado">
                <i class='bx bx-edit'></i>
              </button>
              <form method="POST" style="display:inline;margin:0">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button type="submit" name="eliminar" class="btn-tbl btn-tbl-del" title="Eliminar">
                  <i class='bx bx-trash'></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</div>

<div id="modalFoto" class="modal-foto" onclick="fecharModalFoto()">
  <span class="modal-close">&times;</span>
  <img id="modalFotoImg" src="">
</div>

<div id="modalEstado" class="modal-overlay">
  <div class="modal-box" onclick="event.stopPropagation()">
    <h3><i class='bx bx-edit'></i> Alterar Estado de Pagamento</h3>
    <form method="POST" id="formEstado">
      <input type="hidden" name="despesa_id" id="despesa_id">
      <div class="estado-opts">
        <label class="estado-opt opt-pendente"><input type="radio" name="novo_estado" value="Pendente"><i class='bx bx-time-five'></i> Pendente</label>
        <label class="estado-opt opt-pago"><input type="radio" name="novo_estado" value="Pago"><i class='bx bx-check-circle'></i> Pago</label>
        <label class="estado-opt opt-atrasado"><input type="radio" name="novo_estado" value="Atrasado"><i class='bx bx-x-circle'></i> Atrasado</label>
      </div>
      <div class="modal-actions">
        <button type="button" onclick="fecharModalEstado()" class="btn-m-cancel">Cancelar</button>
        <button type="submit" name="alterar_estado" class="btn-m-confirm">Confirmar</button>
      </div>
    </form>
  </div>
</div>

<script src="../../toast.js"></script>
<script>
function toggleForm(){document.getElementById('formNovaDespesa').classList.toggle('active')}
function mudarMes(){const m=document.getElementById('mesSelecionado').value;const c=new URLSearchParams(window.location.search).get('categoria')||'Todas';window.location.href=`?mes=${m}&categoria=${c}`}
function abrirModalFoto(src){document.getElementById('modalFotoImg').src=src;document.getElementById('modalFoto').classList.add('active')}
function fecharModalFoto(){document.getElementById('modalFoto').classList.remove('active')}
function abrirModalEstado(id,estado){
  document.getElementById('despesa_id').value=id;
  document.querySelectorAll('input[name="novo_estado"]').forEach(r=>{r.checked=r.value===estado});
  document.getElementById('modalEstado').classList.add('active');
}
function fecharModalEstado(){document.getElementById('modalEstado').classList.remove('active')}
document.getElementById('modalEstado').addEventListener('click',function(e){if(e.target===this)fecharModalEstado()});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){fecharModalFoto();fecharModalEstado()}});

document.querySelectorAll('.btn-tbl-del').forEach(btn=>{
  btn.addEventListener('click',function(e){
    e.preventDefault();
    const form=this.closest('form');
    const id=form.querySelector('input[name="id"]').value;
    const desc=this.closest('tr').querySelector('strong').textContent;
    toast.confirm({
      type:'warning',title:'Eliminar Despesa?',
      message:`Tem certeza que deseja eliminar "${desc}"? Esta ação não pode ser desfeita.`,
      confirmText:'Eliminar',cancelText:'Cancelar',
      onConfirm:()=>{
        toast.info('A eliminar...','Aguarde um momento');
        const fd=new FormData();fd.append('id',id);fd.append('eliminar','1');
        fetch(window.location.pathname,{method:'POST',body:fd})
          .then(r=>{if(!r.ok)throw new Error();return r.text()})
          .then(()=>{toast.success('Eliminado!',`"${desc}" foi eliminada`);setTimeout(()=>location.reload(),1500)})
          .catch(()=>toast.error('Erro!','Erro ao eliminar. Tente novamente.'));
      }
    });
  }); 
});

setTimeout(()=>{
  document.querySelectorAll('.alert').forEach(a=>{
    a.style.transition='opacity .3s';a.style.opacity='0';
    setTimeout(()=>a.remove(),300);
  });
},4000);
</script>
</body>
</html>