<?php

date_default_timezone_set('America/Caracas');

$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Conexión Nube (PostgreSQL)
    $db = parse_url($database_url);
    $dsn = "pgsql:host=" . $db['host'] . ";port=" . ($db['port'] ?? 5432) . ";dbname=" . ltrim($db['path'], '/');
    try {
        $conexion = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $conexion->exec("SET timezone = 'America/Caracas'");
    } catch (PDOException $e) {
        die("Error de conexión a la nube: " . $e->getMessage());
    }
} else {
    // Conexión Local (MySQL con PDO)
    $servidor = "localhost";
    $usuario_db = "root";
    $clave_db = "";
    $nombre_bd = "escuela_carnevali";

    try {
        $dsn = "mysql:host={$servidor};dbname={$nombre_bd};charset=utf8mb4";
        $conexion = new PDO($dsn, $usuario_db, $clave_db, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        $conexion->exec("SET NAMES 'utf8mb4'");
        $conexion->exec("SET time_zone = '-04:00'");
        $conexion->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    } catch (PDOException $e) {
        die("Error al conectar a la base de datos local: " . $e->getMessage());
    }
}
?>