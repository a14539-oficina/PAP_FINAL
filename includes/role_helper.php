<?php
/**
 * Helper para gestão de roles
 * 1 = Administrador
 * 2 = Diretor
 * 3 = Treinador
 * 4 = Jogador
 */

function getRoleName($role_id) {
    return match((int)$role_id) {
        1 => 'Administrador',
        2 => 'Diretor',
        3 => 'Treinador',
        4 => 'Jogador',
        default => 'Jogador'
    };
}

function getRoleClass($role_id) {
    return match((int)$role_id) {
        1 => 'badge-admin',
        2 => 'badge-treinador',
        3 => 'badge-staff',
        4 => 'badge-jogador',
        default => 'badge-jogador'
    };
}

function isAdmin($role_id) {
    return (int)$role_id === 1;
}

function getRoleOptions() {
    return [
        1 => 'Administrador',
        2 => 'Diretor',
        3 => 'Treinador',
        4 => 'Jogador'
    ];
}