<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Aplica automaticamente o filtro por clube às queries SQL.
 * 
 * Exemplo:
 *   $sql = clubFilter("SELECT * FROM players p ORDER BY nome ASC", "p");
 */
function clubFilter($sql, $alias = '', $column = 'club_id') {
    $user_role = $_SESSION['user_role'] ?? '';
    $club_id   = isset($_SESSION['club_id']) ? intval($_SESSION['club_id']) : 0;

    // Se for admin ou não tiver clube → não filtra
    if ($user_role === '1' || $club_id === 0) {
        return $sql;
    }

    // Define o campo a filtrar
    $field = $alias ? "$alias.$column" : $column;

    // Verifica se já tem WHERE
    $hasWhere = stripos($sql, 'WHERE') !== false;

    // Procura por ORDER BY / LIMIT
    $posOrder = stripos($sql, 'ORDER BY');
    $posLimit = stripos($sql, 'LIMIT');

    // Determina onde cortar o SQL
    $cutPos = false;
    if ($posOrder !== false && $posLimit !== false) {
        $cutPos = min($posOrder, $posLimit);
    } elseif ($posOrder !== false) {
        $cutPos = $posOrder;
    } elseif ($posLimit !== false) {
        $cutPos = $posLimit;
    }

    $head = ($cutPos !== false) ? substr($sql, 0, $cutPos) : $sql;
    $tail = ($cutPos !== false) ? substr($sql, $cutPos) : '';

    // Adiciona o filtro de clube
    if ($hasWhere) {
        $head .= " AND $field = $club_id ";
    } else {
        $head .= " WHERE $field = $club_id ";
    }

    return $head . $tail;
}
