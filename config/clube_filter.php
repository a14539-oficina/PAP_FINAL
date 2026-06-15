<?php
// config/clube_filter.php
// Este ficheiro retorna o WHERE clause e os parametros para filtrar por clube

function getClubFilter($user_role, $clube_id_user, $table_alias = '') {
    $where = "";
    $params = [];
    $types = "";
    
    // Se o alias da tabela foi passado, adiciona o ponto
    $prefix = $table_alias ? $table_alias . '.' : '';
    
    // Admin de clube (role 2) com clube associado
    if (($user_role == '2' || $user_role == 2) && $clube_id_user !== null && $clube_id_user > 0) {
        $where = " WHERE {$prefix}clube_id = ?";
        $params[] = $clube_id_user;
        $types = "i";
    }
    
    return [
        'where' => $where,
        'params' => $params,
        'types' => $types,
        'is_club_admin' => !empty($where)
    ];
}
?>