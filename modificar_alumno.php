<?php
session_start();
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header("Location: procesar.php");
    exit();
}

include("conexion.php");
if ($conexion instanceof mysqli) {
    mysqli_set_charset($conexion, "utf8mb4");
}

$mensaje = "";
$alumno = null;
$usuario_actual = $_SESSION['usuario'] ?? 'Administrador';

function registrarHistorialModificacion($conexion, $descripcion, $usuario) {
    $fecha = date('Y-m-d H:i:s');
    if ($conexion instanceof PDO) {
        try {
            $stmt = $conexion->prepare("INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES (:descripcion, :usuario, :fecha)");
            $stmt->execute([':descripcion' => $descripcion, ':usuario' => $usuario, ':fecha' => $fecha]);
        } catch (Exception $ex) {
        }
    } else {
        $desc_l = mysqli_real_escape_string($conexion, $descripcion);
        $usu_l = mysqli_real_escape_string($conexion, $usuario);
        @mysqli_query($conexion, "INSERT INTO historial (descripcion, usuario, fecha_hora) VALUES ('$desc_l', '$usu_l', '$fecha')");
    }
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($conexion instanceof PDO) {
        $stmt_con = $conexion->prepare("SELECT e.*, i.grado, i.año_escolar FROM estudiante e LEFT JOIN inscripcion i ON e.cédula_escolar = i.cédula_escolar_estudiante WHERE e.cédula_escolar = :id OR e.id = :id_num LIMIT 1");
        $stmt_con->execute([':id' => $_GET['id'], ':id_num' => $id]);
        $alumno = $stmt_con->fetch(PDO::FETCH_ASSOC);
    } else {
        $res = mysqli_query($conexion, "SELECT e.*, i.grado, i.año_escolar FROM estudiante e LEFT JOIN inscripcion i ON e.cédula_escolar = i.cédula_escolar_estudiante WHERE e.id = $id LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $alumno = mysqli_fetch_assoc($res);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_alumno'])) {
    $id = intval($_POST['id']);
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    
    $nac = $_POST['nacionalidad_cedula'] ?? 'V';
    $num = trim($_POST['numero_cedula'] ?? '');
    $cedula_alumno = !empty($num) ? $nac . '-' . $num : '';
    
    $grado = trim($_POST['grado'] ?? '');
    $seccion = trim($_POST['seccion'] ?? '');

    if ($conexion instanceof PDO) {
        try {
            $conexion->beginTransaction();
            $stmt_up = $conexion->prepare("UPDATE estudiante SET nombres = :nombres, apellidos = :apellidos, cédula_escolar = :cedula WHERE id = :id");
            $stmt_up->execute([
                ':nombres' => $nombres,
                ':apellidos' => $apellidos,
                ':cedula' => $cedula_alumno,
                ':id' => $id
            ]);

            $stmt_ins = $conexion->prepare("UPDATE inscripcion SET grado = :grado WHERE cédula_escolar_estudiante = :cedula");
            $stmt_ins->execute([':grado' => $grado, ':cedula' => $cedula_alumno]);

            $conexion->commit();
            registrarHistorialModificacion($conexion, "Modificación del alumno: $nombres $apellidos ($cedula_alumno)", $usuario_actual);
            $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Datos del alumno actualizados exitosamente!</div>";
            
            $stmt_con = $conexion->prepare("SELECT e.*, i.grado, i.año_escolar FROM estudiante e LEFT JOIN inscripcion i ON e.cédula_escolar = i.cédula_escolar_estudiante WHERE e.id = :id LIMIT 1");
            $stmt_con->execute([':id' => $id]);
            $alumno = $stmt_con->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al actualizar: " . $e->getMessage() . "</div>";
        }
    } else {
        $nombres_l = mysqli_real_escape_string($conexion, $nombres);
        $apellidos_l = mysqli_real_escape_string($conexion, $apellidos);
        $cedula_l = mysqli_real_escape_string($conexion, $cedula_alumno);
        $grado_l = mysqli_real_escape_string($conexion, $grado);
        $seccion_l = mysqli_real_escape_string($conexion, $seccion);

        mysqli_begin_transaction($conexion);
        try {
            mysqli_query($conexion, "UPDATE estudiante SET nombres = '$nombres_l', apellidos = '$apellidos_l', cédula_escolar = '$cedula_l' WHERE id = $id");
            mysqli_query($conexion, "UPDATE inscripcion SET grado = '$grado_l' WHERE cédula_escolar_estudiante = '$cedula_l'");
            mysqli_commit($conexion);

            registrarHistorialModificacion($conexion, "Modificación del alumno: $nombres_l $apellidos_l ($cedula_l)", $usuario_actual);
            $mensaje = "<div style='color: #155724; background-color: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>¡Datos del alumno actualizados exitosamente!</div>";
            $res = mysqli_query($conexion, "SELECT e.*, i.grado, i.año_escolar FROM estudiante e LEFT JOIN inscripcion i ON e.cédula_escolar = i.cédula_escolar_estudiante WHERE e.id = $id LIMIT 1");
            if ($res) {
                $alumno = mysqli_fetch_assoc($res);
            }
        } catch (Exception $ex) {
            mysqli_rollback($conexion);
            $mensaje = "<div style='color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px;'>Error al actualizar.</div>";
        }
    }
}

$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Alumno - Sistema de Inscripción</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        html, body { min-height: 100%; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F4F7F6; color: #212529; display: flex; width: 100%; }
        .sidebar { width: 250px; background-color: #FFFFFF; padding: 15px; box-shadow: 2px 0 10px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; min-height: 100vh; box-sizing: border-box; flex-shrink: 0; }
        .sidebar-logo { font-size: 1.0rem; font-weight: bold; color: #007bff; margin-bottom: 20px; text-align: center; }
        .sidebar-logo img { width: 55px; height: 55px; object-fit: contain; display: block; margin: 0 auto 8px auto; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-item { margin-bottom: 6px; }
        .sidebar-link { text-decoration: none; color: #495057; display: flex; align-items: center; padding: 9px 12px; border-radius: 8px; font-size: 0.95rem; transition: background-color 0.2s; cursor: pointer; }
        .sidebar-link i { margin-right: 10px; width: 20px; text-align: center; }
        .sidebar-link:hover, .sidebar-link.active { background-color: #E6F7FF; color: #0056b3; font-weight: 600; }
        .has-submenu { position: relative; }
        .submenu { list-style: none; padding-left: 25px; margin: 4px 0 8px 0; display: none !important; flex-direction: column; gap: 5px; }
        .submenu.show { display: flex !important; }
        .submenu-link { text-decoration: none; color: #6c757d; font-size: 0.88rem; padding: 7px 10px; border-radius: 6px; display: flex; align-items: center; transition: all 0.2s; }
        .submenu-link i { font-size: 0.75rem; margin-right: 8px; }
        .submenu-link:hover { background-color: #eef6ff; color: #0056b3; padding-left: 14px; }
        .main-content { flex: 1; min-width: 0; padding: 20px 30px; background-color: #F4F7F6; box-sizing: border-box; }
        .card { background: #FFFFFF; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); margin-bottom: 25px; width: 100%; box-sizing: border-box; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: #495057; }
        .form-group input, .form-group select { padding: 8px 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 0.9rem; width: 100%; box-sizing: border-box; background-color: #fff; height: 36px; }
        .cedula-group { display: flex; gap: 5px; }
        .cedula-group select { width: 75px; flex-shrink: 0; }
        .btn-guardar { background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background-color 0.2s; }
        .btn-guardar:hover { background-color: #0056b3; }
        @media (max-width: 768px) { html, body { flex-direction: column; } .sidebar { width: 100%; min-height: auto; height: auto; } .main-content { padding: 15px; } }
    </style>
</head>
<body>

    <?php include("menu.php"); ?>

    <main class="main-content">
        <div class="card">
            <h2><i class="fas fa-user-edit text-primary"></i> Modificar Datos del Alumno</h2>
            <hr style="margin-bottom: 20px; border: 0; border-top: 1px solid #eee;">

            <?php if (!empty($mensaje)) echo $mensaje; ?>

            <?php if ($alumno): ?>
            <?php 
                $ced_completa = $alumno['cédula_escolar'] ?? '';
                $nac_actual = 'V';
                $num_actual = $ced_completa;
                if (strpos($ced_completa, '-') !== false) {
                    $partes = explode('-', $ced_completa, 2);
                    $nac_actual = $partes[0];
                    $num_actual = $partes[1];
                }
            ?>
            <form method="POST" action="modificar_alumno.php?id=<?php echo $alumno['id'] ?? 0; ?>">
                <input type="hidden" name="id" value="<?php echo $alumno['id'] ?? 0; ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombres (*):</label>
                        <input type="text" name="nombres" maxlength="60" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo letras y espacios" value="<?php echo htmlspecialchars($alumno['nombres'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Apellidos (*):</label>
                        <input type="text" name="apellidos" maxlength="60" pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ\s]+" title="Solo letras y espacios" value="<?php echo htmlspecialchars($alumno['apellidos'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Cédula Escolar / Identidad (*):</label>
                        <div class="cedula-group">
                            <select name="nacionalidad_cedula">
                                <option value="V" <?php echo ($nac_actual == 'V') ? 'selected' : ''; ?>>V-</option>
                                <option value="E" <?php echo ($nac_actual == 'E') ? 'selected' : ''; ?>>E-</option>
                            </select>
                            <input type="text" name="numero_cedula" placeholder="12345678" maxlength="15" pattern="[0-9]+" title="Ingrese solo números" value="<?php echo htmlspecialchars($num_actual); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Grado (*):</label>
                        <input type="number" name="grado" min="1" max="6" value="<?php echo htmlspecialchars($alumno['grado'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Sección (*):</label>
                        <input type="text" name="seccion" maxlength="2" pattern="[A-Za-z0-9]+" title="Caracteres alfanuméricos cortos" value="<?php echo htmlspecialchars($alumno['sección'] ?? ''); ?>" required>
                    </div>
                </div>

                <button type="submit" name="actualizar_alumno" class="btn-guardar"><i class="fas fa-save"></i> Guardar Cambios</button>
            </form>
            <?php else: ?>
                <p style="color: #6c757d;">Por favor seleccione un alumno desde el módulo de consulta para modificar sus datos.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleSubmenu(e) {
            e.preventDefault();
            const submenu = document.getElementById('alumnosSubmenu');
            submenu.classList.toggle('show');
        }
    </script>
</body>
</html>