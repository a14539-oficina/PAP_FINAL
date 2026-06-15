<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 1) { header("Location: listar.php"); exit; }

require('../../config/db.php');

$club_id = intval($_SESSION['club_id']);
$jogo    = null;
$isConcluido = false;
$erro    = null;

// Buscar equipas do clube
$stmt = $conn->prepare("SELECT id, nome FROM teams WHERE club_id = ? ORDER BY nome");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$res   = $stmt->get_result();
$teams = [];
while ($t = $res->fetch_assoc()) $teams[] = $t;
$stmt->close();

// Buscar temporadas (igual ao listar.php)
$temporadas = [];
$resTemp = $conn->query("SELECT id, nome FROM seasons ORDER BY data_inicio DESC");
if ($resTemp) while ($r = $resTemp->fetch_assoc()) $temporadas[] = $r;
if (empty($temporadas)) $temporadas[] = ['id' => 1, 'nome' => '24/25'];

// Carregar jogo para edição
if (isset($_GET['id'])) {
    $id   = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $jogo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($jogo) $isConcluido = in_array($jogo['estado'], ['Concluido', 'Decorrido']);
}

// Calcular resultado_final igual ao listar.php
function calcResultadoFinal(int $m, int $s): string {
    if ($m > $s) return 'Win';
    if ($m === $s) return 'Draw';
    return 'Defeat';
}

// Processar submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isConcluido) {
    $id_post        = intval($_POST['id']             ?? 0);
    $team_id        = intval($_POST['team_id']        ?? 0);
    $season_id      = intval($_POST['season_id']      ?? 0);
    $adversario     = trim($_POST['adversario']       ?? '');
    $data_jogo      = trim($_POST['data_jogo']        ?? '');
    $local          = trim($_POST['local']            ?? '');
    $estado         = trim($_POST['estado']           ?? '');
    $golos_marcados = max(0, intval($_POST['golos_marcados'] ?? 0));
    $golos_sofridos = max(0, intval($_POST['golos_sofridos'] ?? 0));

    // resultado_final sincronizado com listar.php
    $resultado_final = in_array($estado, ['Concluido','Decorrido'])
        ? calcResultadoFinal($golos_marcados, $golos_sofridos)
        : null;

    if ($team_id <= 0)                            $erro = "Selecione uma equipa.";
    elseif ($season_id <= 0)                      $erro = "Selecione uma temporada.";
    elseif (mb_strlen($adversario) < 3)           $erro = "O adversário deve ter pelo menos 3 caracteres.";
    elseif (empty($data_jogo))                    $erro = "Selecione a data e hora do jogo.";
    elseif (mb_strlen($local) < 3)                $erro = "O local deve ter pelo menos 3 caracteres.";
    elseif (!in_array($estado, ['Agendado','Decorrido','Concluido','Adiado'])) $erro = "Estado inválido.";

    if (!$erro) {
        $data_mysql = date('Y-m-d H:i:s', strtotime($data_jogo));

        if ($id_post > 0) {
            $stmt = $conn->prepare("
                UPDATE matches
                SET team_id=?, season_id=?, adversario=?, data_jogo=?,
                    local=?, estado=?, golos_marcados=?, golos_sofridos=?, resultado_final=?
                WHERE id=?
            ");
            $stmt->bind_param("iissssiiisi", $team_id, $season_id, $adversario, $data_mysql,
                              $local, $estado, $golos_marcados, $golos_sofridos, $resultado_final, $id_post);
            // Nota: resultado_final pode ser NULL → usar bind correto
            $stmt->bind_param("iissssiiisi",
                $team_id, $season_id, $adversario, $data_mysql,
                $local, $estado, $golos_marcados, $golos_sofridos,
                $resultado_final, $id_post
            );
            $msg = "Jogo atualizado com sucesso!";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO matches (team_id, season_id, adversario, data_jogo, local, estado, golos_marcados, golos_sofridos, resultado_final)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param("iissssiiis",
                $team_id, $season_id, $adversario, $data_mysql,
                $local, $estado, $golos_marcados, $golos_sofridos, $resultado_final
            );
            $msg = "Jogo criado com sucesso!";
        }

        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['sucesso'] = $msg;
            header("Location: listar.php");
            exit;
        }
        $erro = "Erro ao guardar: " . $stmt->error;
        $stmt->close();
    }
}

// Helpers
function fv(string $k, ?array $j, string $d = ''): string {
    $v = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$k] ?? $d) : ($j[$k] ?? $d);
    return htmlspecialchars((string)$v);
}
function fsel(string $k, string $v, ?array $j): string {
    $cur = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$k] ?? '') : ($j[$k] ?? '');
    return (string)$cur === $v ? 'selected' : '';
}
function fi(string $k, ?array $j): int {
    return max(0, intval($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$k] ?? 0) : ($j[$k] ?? 0)));
}

$titulo = $jogo ? ($isConcluido ? 'Ver jogo' : 'Editar jogo') : 'Novo jogo';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?> – SportGes</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/SportGes/public/css/style.css?v=1.0">
    <script src="../../assets/js/toast.js"></script>
    <style>
        .mf-wrap { max-width: 720px; margin: 0 auto; padding-bottom: 40px; }

        .mf-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .mf-header h1 { font-size: 18px; font-weight: 600; color: #111827; display: flex; align-items: center; gap: 8px; margin: 0; }
        .mf-header h1 i { font-size: 20px; color: #4F46E5; }

        .mf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px 24px; margin-bottom: 12px; }
        .mf-card-title { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .07em; margin-bottom: 16px; }

        .mf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 560px) { .mf-grid { grid-template-columns: 1fr; } }

        .mf-field { display: flex; flex-direction: column; gap: 5px; }
        .mf-field label { font-size: 12px; color: #6b7280; }
        .mf-field input, .mf-field select {
            height: 38px; padding: 0 11px; font-size: 14px;
            color: #111827; background: #f9fafb;
            border: 1px solid #e5e7eb; border-radius: 8px;
            outline: none; width: 100%; box-sizing: border-box;
            transition: border-color .15s, box-shadow .15s;
        }
        .mf-field input:focus, .mf-field select:focus {
            border-color: #4F46E5; background: #fff;
            box-shadow: 0 0 0 3px rgba(79,70,229,.1);
        }
        .mf-field input[readonly], .mf-field select[disabled] {
            background: #f3f4f6; color: #9ca3af; cursor: not-allowed;
        }

        .score-row { display: flex; align-items: flex-end; gap: 10px; }
        .score-row .mf-field { flex: 1; }
        .score-row .mf-field input {
            height: 56px !important; font-size: 26px !important;
            font-weight: 600 !important; text-align: center !important; color: #4F46E5 !important;
        }
        .score-sep { font-size: 22px; color: #d1d5db; padding-bottom: 10px; flex-shrink: 0; }

        /* Preview resultado (igual ao listar.php) */
        .resultado-preview {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; margin-top: 12px; min-height: 32px;
        }
        .resultado-badge {
            font-size: 11px; font-weight: 700; letter-spacing: 1px;
            text-transform: uppercase; padding: 4px 12px; border-radius: 4px;
        }
        .badge-win     { background: #dcfce7; color: #166534; }
        .badge-draw    { background: #fef3c7; color: #92400e; }
        .badge-defeat  { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #f1f5f9; color: #64748b; }

        .mf-readonly-notice {
            display: flex; align-items: center; gap: 10px;
            background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;
            border-radius: 8px; padding: 11px 16px; margin-bottom: 16px; font-size: 13px;
        }
        .mf-readonly-notice i { font-size: 17px; flex-shrink: 0; }

        .mf-error {
            display: flex; align-items: center; gap: 10px;
            background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
            border-radius: 8px; padding: 11px 16px; margin-bottom: 16px; font-size: 13px;
        }
        .mf-error i { font-size: 17px; flex-shrink: 0; }

        .mf-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 4px; }
        .btn-outline {
            height: 38px; padding: 0 18px; font-size: 13px; font-weight: 500;
            color: #374151; background: #fff; border: 1px solid #d1d5db;
            border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-outline:hover { background: #f9fafb; }
        .btn-primary {
            height: 38px; padding: 0 22px; font-size: 13px; font-weight: 600;
            color: #fff; background: #4F46E5; border: none;
            border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary:hover { background: #4338ca; }
    </style>
</head>
<body>
<?php require('../../includes/sidebar.php'); ?>

<div class="main-content">
<div class="mf-wrap">

    <div class="mf-header">
        <h1><i class='bx bx-football'></i> <?= htmlspecialchars($titulo) ?></h1>
        <button type="button" onclick="history.back()" class="btn-outline">
            <i class='bx bx-arrow-back'></i> Voltar
        </button>
    </div>

    <?php if ($isConcluido): ?>
    <div class="mf-readonly-notice">
        <i class='bx bx-lock-alt'></i>
        Jogo finalizado — não pode ser editado.
    </div>
    <?php endif; ?>

    <?php if ($erro): ?>
    <div class="mf-error">
        <i class='bx bx-error-circle'></i>
        <?= htmlspecialchars($erro) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="matchForm" novalidate>
        <?php if ($jogo): ?>
            <input type="hidden" name="id" value="<?= intval($jogo['id']) ?>">
        <?php endif; ?>

        <!-- Confronto -->
        <div class="mf-card">
            <div class="mf-card-title">Confronto</div>
            <div class="mf-grid">
                <div class="mf-field">
                    <label>Equipa</label>
                    <select name="team_id" required <?= $isConcluido ? 'disabled' : '' ?>>
                        <option value="">Selecione…</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?= intval($t['id']) ?>" <?= fv('team_id',$jogo)==$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mf-field">
                    <label>Adversário</label>
                    <input type="text" name="adversario"
                           value="<?= fv('adversario',$jogo) ?>"
                           placeholder="Nome do adversário"
                           <?= $isConcluido ? 'readonly' : '' ?>>
                </div>
            </div>
        </div>

        <!-- Temporada, Data, Local, Estado -->
        <div class="mf-card">
            <div class="mf-card-title">Detalhes</div>
            <div class="mf-grid" style="margin-bottom:14px">
                <div class="mf-field">
                    <label>Temporada</label>
                    <select name="season_id" required <?= $isConcluido ? 'disabled' : '' ?>>
                        <option value="">Selecione…</option>
                        <?php foreach ($temporadas as $tp): ?>
                            <option value="<?= intval($tp['id']) ?>" <?= fsel('season_id', (string)$tp['id'], $jogo) ?>>
                                <?= htmlspecialchars($tp['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mf-field">
                    <label>Estado</label>
                    <select name="estado" id="estadoSelect" <?= $isConcluido ? 'disabled' : '' ?>>
                        <option value="Agendado"  <?= fsel('estado','Agendado', $jogo) ?>>Agendado</option>
                        <option value="Decorrido" <?= fsel('estado','Decorrido',$jogo) ?>>A decorrer</option>
                        <option value="Concluido" <?= fsel('estado','Concluido',$jogo) ?>>Concluído</option>
                        <option value="Adiado"    <?= fsel('estado','Adiado',   $jogo) ?>>Adiado</option>
                    </select>
                </div>
            </div>
            <div class="mf-grid">
                <div class="mf-field">
                    <label>Data e hora</label>
                    <input type="datetime-local" name="data_jogo"
                           value="<?= $jogo && !isset($_POST['data_jogo'])
                               ? date('Y-m-d\TH:i', strtotime($jogo['data_jogo']))
                               : htmlspecialchars($_POST['data_jogo'] ?? '') ?>"
                           <?= $isConcluido ? 'readonly' : '' ?>>
                </div>
                <div class="mf-field">
                    <label>Local</label>
                    <input type="text" name="local"
                           value="<?= fv('local',$jogo) ?>"
                           placeholder="Ex: Estádio Municipal"
                           <?= $isConcluido ? 'readonly' : '' ?>>
                </div>
            </div>
        </div>

        <!-- Resultado -->
        <div class="mf-card">
            <div class="mf-card-title">Resultado</div>
            <div class="score-row">
                <div class="mf-field">
                    <label>Golos marcados</label>
                    <input type="number" name="golos_marcados" id="golosMarcados"
                           min="0" value="<?= fi('golos_marcados',$jogo) ?>"
                           <?= $isConcluido ? 'readonly' : '' ?>>
                </div>
                <div class="score-sep">–</div>
                <div class="mf-field">
                    <label>Golos sofridos</label>
                    <input type="number" name="golos_sofridos" id="golosSofridos"
                           min="0" value="<?= fi('golos_sofridos',$jogo) ?>"
                           <?= $isConcluido ? 'readonly' : '' ?>>
                </div>
            </div>
            <!-- Preview igual ao listar.php -->
            <div class="resultado-preview" id="resultadoPreview"></div>
        </div>

        <div class="mf-actions">
            <button type="button" onclick="history.back()" class="btn-outline">Cancelar</button>
            <?php if (!$isConcluido): ?>
            <button type="submit" class="btn-primary">
                <i class='bx bx-check'></i> Guardar
            </button>
            <?php endif; ?>
        </div>
    </form>

</div>
</div>

<script>
// Preview do resultado em tempo real — igual à lógica do listar.php
const gm      = document.getElementById('golosMarcados');
const gs      = document.getElementById('golosSofridos');
const estado  = document.getElementById('estadoSelect');
const preview = document.getElementById('resultadoPreview');

function atualizarPreview() {
    if (!preview) return;
    const m = parseInt(gm?.value) || 0;
    const s = parseInt(gs?.value) || 0;
    const e = estado?.value || '';

    let score = m + ' – ' + s;
    let cls   = 'badge-pending';
    let label = '';

    if (e === 'Concluido' || e === 'Decorrido') {
        if (m > s)      { cls = 'badge-win';    label = 'Vitória'; }
        else if (m < s) { cls = 'badge-defeat'; label = 'Derrota'; }
        else            { cls = 'badge-draw';   label = 'Empate'; }
        preview.innerHTML =
            '<span style="font-size:20px;font-weight:700;color:#0f172a;letter-spacing:1px">' + score + '</span>' +
            '<span class="resultado-badge ' + cls + '">' + label + '</span>';
    } else if (e === 'Adiado') {
        preview.innerHTML = '<span class="resultado-badge badge-pending">Adiado</span>';
    } else {
        preview.innerHTML = '<span style="font-size:13px;color:#9ca3af">Resultado disponível após conclusão</span>';
    }
}

gm?.addEventListener('input',    atualizarPreview);
gs?.addEventListener('input',    atualizarPreview);
estado?.addEventListener('change', atualizarPreview);
atualizarPreview();

// Validação submit
document.getElementById('matchForm').addEventListener('submit', function(e) {
    <?php if ($isConcluido): ?>
    e.preventDefault();
    toast.error('Bloqueado', 'Este jogo não pode ser editado.', 4000);
    return;
    <?php endif; ?>
    const g = n => (this.querySelector('[name="'+n+'"]') || {}).value || '';
    if (!g('team_id'))                     { e.preventDefault(); toast.warning('Atenção', 'Selecione uma equipa.', 4000); return; }
    if (!g('season_id'))                   { e.preventDefault(); toast.warning('Atenção', 'Selecione uma temporada.', 4000); return; }
    if (g('adversario').trim().length < 3) { e.preventDefault(); toast.warning('Atenção', 'Adversário inválido.', 4000); return; }
    if (!g('data_jogo'))                   { e.preventDefault(); toast.warning('Atenção', 'Selecione a data.', 4000); return; }
    if (g('local').trim().length < 3)      { e.preventDefault(); toast.warning('Atenção', 'Local inválido.', 4000); return; }
});
</script>
</body>
</html>