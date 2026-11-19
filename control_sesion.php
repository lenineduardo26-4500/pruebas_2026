<?php
date_default_timezone_set('America/Caracas');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}

function verificarLogin() {
    if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true || empty($_SESSION['usuario'])) {
        header("Location: procesar.php");
        exit();
    }
}

function verificarRol($rolesPermitidos, $mensajeDenegado = 'Acceso denegado. No cuenta con los permisos necesarios para acceder a este módulo.') {
    verificarLogin();

    $rolUsuario = $_SESSION['rol'] ?? '';
    
    if (!is_array($rolesPermitidos)) {
        $rolesPermitidos = [$rolesPermitidos];
    }

    if (!in_array($rolUsuario, $rolesPermitidos)) {
        $msgSeguro = json_encode($mensajeDenegado, JSON_UNESCAPED_UNICODE);
        echo "<script>
                alert($msgSeguro);
                window.location.href = 'index.php';
              </script>";
        exit();
    }
}

function obtenerTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = hash('sha256', uniqid((string)mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCSRF($token) {
    if (empty($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>