<?php
session_start();
require('config/db.php');
require('includes/role_helper.php');

// Verifica se está logado e é SUPER ADMIN (role = 0)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== '0') {
    header('Location: index.php');
    exit;
}

$sucesso = $_SESSION['sucesso'] ?? ''; // Busca mensagem da sessão
unset($_SESSION['sucesso']); // Limpa a mensagem depois de usar

$erro = '';

// Eliminar utilizador
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    // Não pode eliminar a si próprio
    if ($id == $_SESSION['user_id']) {
        $erro = "Não pode eliminar o seu próprio utilizador!";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $sucesso = "Utilizador eliminado com sucesso!";
        } else {
            $erro = "Erro ao eliminar utilizador.";
        }
    }
}

// Alternar estado ativo/inativo
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE users SET ativo = NOT ativo WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $sucesso = "Estado do utilizador atualizado!";
    }
}

// Buscar todos os utilizadores
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT u.*, c.nome as clube_nome 
        FROM users u 
        LEFT JOIN clubs c ON u.club_id = c.id 
        WHERE 1=1";

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
}
if ($role_filter !== '') {
    $sql .= " AND u.role = ?";
}
if ($status_filter !== '') {
    $sql .= " AND u.ativo = ?";
}

$sql .= " ORDER BY u.full_name ASC";

$stmt = $conn->prepare($sql);

if ($search !== '' && $role_filter !== '' && $status_filter !== '') {
    $searchTerm = "%$search%";
    $stmt->bind_param("sssi", $searchTerm, $searchTerm, $role_filter, $status_filter);
} elseif ($search !== '' && $role_filter !== '') {
    $searchTerm = "%$search%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $role_filter);
} elseif ($search !== '' && $status_filter !== '') {
    $searchTerm = "%$search%";
    $stmt->bind_param("ssi", $searchTerm, $searchTerm, $status_filter);
} elseif ($role_filter !== '' && $status_filter !== '') {
    $stmt->bind_param("si", $role_filter, $status_filter);
} elseif ($search !== '') {
    $searchTerm = "%$search%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
} elseif ($role_filter !== '') {
    $stmt->bind_param("s", $role_filter);
} elseif ($status_filter !== '') {
    $stmt->bind_param("i", $status_filter);
}

$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerir Utilizadores - SportGes</title>
<link rel="stylesheet" href="public/css/style.css">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
    background: #f8fafc;
    color: #0f172a;
}

.main-wrapper {
    display: flex;
    min-height: 100vh;
}

.content-area {
    flex: 1;
    padding: 40px;
    margin-left: 280px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}

.page-title h1 {
    font-size: 32px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 8px;
}

.page-title p {
    color: #64748b;
    font-size: 15px;
}

.btn {
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59,130,246,0.3);
}

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: #ecfdf5;
    border-left: 4px solid #10b981;
    color: #065f46;
}

.alert-error {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    color: #991b1b;
}

.filters-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 24px;
}

.filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 16px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: 600;
    font-size: 13px;
    color: #334155;
    margin-bottom: 8px;
}

.filter-group input,
.filter-group select {
    padding: 10px 14px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    font-size: 14px;
    transition: all 0.2s ease;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.users-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}

th {
    padding: 16px 20px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #334155;
}

tbody tr {
    transition: all 0.2s ease;
}

tbody tr:hover {
    background: #f8fafc;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: #0f172a;
}

.user-email {
    font-size: 13px;
    color: #64748b;
}

.badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-admin {
    background: #dbeafe;
    color: #1e40af;
}

.badge-treinador {
    background: #dcfce7;
    color: #166534;
}

.badge-staff {
    background: #f3e8ff;
    color: #6b21a8;
}

.badge-user {
    background: #f1f5f9;
    color: #475569;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-ativo {
    background: #ecfdf5;
    color: #065f46;
}

.status-inativo {
    background: #fef2f2;
    color: #991b1b;
}

.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: block;
}

.status-ativo::before {
    background: #10b981;
}

.status-inativo::before {
    background: #ef4444;
}

.actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 18px;
}

.btn-edit {
    background: #dbeafe;
    color: #1e40af;
}

.btn-edit:hover {
    background: #bfdbfe;
    transform: scale(1.1);
}

.btn-toggle {
    background: #fef3c7;
    color: #92400e;
}

.btn-toggle:hover {
    background: #fde68a;
    transform: scale(1.1);
}

.btn-delete {
    background: #fee2e2;
    color: #991b1b;
}

.btn-delete:hover {
    background: #fecaca;
    transform: scale(1.1);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 64px;
    color: #cbd5e1;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 20px;
    color: #475569;
    margin-bottom: 8px;
}

.empty-state p {
    color: #94a3b8;
    font-size: 14px;
}

@media (max-width: 768px) {
    .content-area {
        margin-left: 0;
        padding: 20px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .table-container {
        overflow-x: scroll;
    }
}
</style>
</head>
<body>

<?php require('includes/sidebar.php'); ?>

<div class="main-wrapper">
    <div class="content-area">
        <div class="page-header">
            <div class="page-title">
                <h1>👥 Gerir Utilizadores</h1>
                <p>Visualize e gerencie todos os utilizadores da plataforma</p>
            </div>
            <a href="criar_utilizador.php" class="btn btn-primary">
                <i class='bx bx-plus'></i>
                Adicionar Utilizador
            </a>
        </div>

        <?php if(!empty($sucesso)): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle' style="font-size: 20px;"></i>
                <?= $sucesso ?>
            </div>
        <?php elseif(!empty($erro)): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle' style="font-size: 20px;"></i>
                <?= $erro ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters-card">
            <form method="get">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>🔍 Pesquisar</label>
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Nome ou email..."
                            value="<?= htmlspecialchars($search) ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label>Função</label>
                        <select name="role">
                            <option value="">Todas</option>
                            <option value="Administrador" <?= $role_filter === '1' ? 'selected' : '' ?>>Administrador</option>
                            <option value="Diretor" <?= $role_filter === '2' ? 'selected' : '' ?>>Diretor</option>
                            <option value="Treinador" <?= $role_filter === '3' ? 'selected' : '' ?>>Treinador</option>
                            <option value="Jogador" <?= $role_filter === '4' ? 'selected' : '' ?>>Jogador</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Estado</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabela de Utilizadores -->
        <div class="users-card">
            <div class="table-container">
                <?php if ($users->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Utilizador</th>
                            <th>Clube</th>
                            <th>Função</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                    </div>
                                    <div class="user-details">
                                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                                        <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['clube_nome'] ?? 'Sem clube') ?></td>
                            <td>
                                <?php
                                $roleClass = match($user['role']) {
                                    'Administrador' => 'badge-admin',
                                    'Treinador' => 'badge-treinador',
                                    'Diretor' => 'badge-diretor',
                                    default => 'badge-jogador'
                                };
                                ?>
                                <span class="badge <?= $roleClass ?>">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= $user['ativo'] ? 'status-ativo' : 'status-inativo' ?>">
                                    <?= $user['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="editar_utilizador.php?id=<?= $user['id'] ?>" 
                                       class="btn-icon btn-edit" 
                                       title="Editar">
                                        <i class='bx bx-edit'></i>
                                    </a>
                                    <a href="?toggle=<?= $user['id'] ?>" 
                                       class="btn-icon btn-toggle" 
                                       title="<?= $user['ativo'] ? 'Desativar' : 'Ativar' ?>"
                                       onclick="return confirm('Tem certeza que deseja <?= $user['ativo'] ? 'desativar' : 'ativar' ?> este utilizador?')">
                                        <i class='bx <?= $user['ativo'] ? 'bx-toggle-right' : 'bx-toggle-left' ?>'></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?eliminar=<?= $user['id'] ?>" 
                                       class="btn-icon btn-delete" 
                                       title="Eliminar"
                                       onclick="return confirm('Tem certeza que deseja eliminar este utilizador? Esta ação não pode ser desfeita!')">
                                        <i class='bx bx-trash'></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-user-x'></i>
                    <h3>Nenhum utilizador encontrado</h3>
                    <p>Não existem utilizadores que correspondam aos filtros aplicados</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>