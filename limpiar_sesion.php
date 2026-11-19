<?php
session_start();
if (isset($_SESSION['usuario'])) {
    if (!isset($_SESSION['init'])) {
        session_regenerate_id(true);
        $_SESSION['init'] = true;
    }
}

unset($_SESSION['estudiante']);
unset($_SESSION['representante']);
unset($_SESSION['madre']);
unset($_SESSION['padre']);
unset($_SESSION['vivienda']);
unset($_SESSION['vacuna']);
unset($_SESSION['medicamentos']);
unset($_SESSION['adicional']);

$redirect = $_GET['redirect'] ?? 'agregar_alumno.php';
header("Location: " . $redirect);
exit();
?>