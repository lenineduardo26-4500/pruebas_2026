<?php
$es_administrador = isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
$pagina_actual = basename($_SERVER['PHP_SELF']);

$en_submenu_inscripcion = in_array($pagina_actual, ['agregar_alumno.php', 'alumnos.php', 'planilla_inscripcion.php', 'historial_planillas.php', 'modificar_alumno.php']);
$en_submenu_constancias = in_array($pagina_actual, ['constancias.php', 'historial_constancias.php']);
$en_submenu_seguridad   = in_array($pagina_actual, ['usuarios.php', 'autoridades.php', 'configuracion.php', 'historial.php']);
?>
<style>
    .sidebar {
        width: 245px;
        background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        padding: 16px 14px;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.22);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 100vh;
        align-self: stretch;
        box-sizing: border-box;
        flex-shrink: 0;
        z-index: 20;
        border-right: 1px solid rgba(255, 255, 255, 0.06);
    }

    .sidebar-brand-header {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 6px 8px 16px 8px;
        margin-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .sidebar-brand-header .logo-circle {
        width: 42px;
        height: 42px;
        background-color: #ffffff;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
        padding: 4px;
        flex-shrink: 0;
    }

    .sidebar-brand-header .logo-circle img {
        width: 32px;
        height: 32px;
        object-fit: contain;
        display: block;
    }

    .sidebar-brand-text {
        display: flex;
        flex-direction: column;
        text-align: left;
        overflow: hidden;
    }

    .sidebar-brand-title {
        font-size: 0.88rem;
        font-weight: 800;
        color: #ffffff;
        letter-spacing: 0.3px;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .sidebar-brand-sub {
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 500;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-item {
        margin-bottom: 4px;
    }

    .sidebar-link {
        text-decoration: none;
        color: #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 12px;
        border-radius: 9px;
        font-size: 0.84rem;
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .sidebar-link-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar-link i.nav-icon {
        width: 20px;
        text-align: center;
        color: #94a3b8;
        font-size: 0.92rem;
        transition: color 0.2s ease;
    }

    .sidebar-link .submenu-arrow {
        font-size: 0.72rem;
        color: #94a3b8;
        transition: transform 0.25s ease;
    }

    .sidebar-link.submenu-open .submenu-arrow {
        transform: rotate(180deg);
    }

    .sidebar-link:hover {
        background-color: rgba(255, 255, 255, 0.07);
        color: #ffffff;
    }

    .sidebar-link:hover i.nav-icon,
    .sidebar-link:hover .submenu-arrow {
        color: #ffffff;
    }

    .sidebar-link.active {
        background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.38);
    }

    .sidebar-link.active i.nav-icon,
    .sidebar-link.active .submenu-arrow {
        color: #ffffff;
    }

    .has-submenu {
        position: relative;
    }

    .submenu {
        list-style: none;
        padding: 4px 0 4px 16px;
        margin: 3px 0 6px 12px;
        border-left: 2px solid rgba(59, 130, 246, 0.35);
        display: none !important;
        flex-direction: column;
        gap: 3px;
    }

    .submenu.show {
        display: flex !important;
    }

    .submenu-link {
        text-decoration: none;
        color: #94a3b8;
        font-size: 0.8rem;
        font-weight: 500;
        padding: 7px 10px;
        border-radius: 7px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .submenu-link i {
        font-size: 0.75rem;
        width: 15px;
        text-align: center;
    }

    .submenu-link:hover {
        background-color: rgba(255, 255, 255, 0.06);
        color: #ffffff;
        padding-left: 13px;
    }

    .submenu-link.sub-active {
        background-color: rgba(37, 99, 235, 0.22);
        color: #60a5fa;
        font-weight: 600;
    }

    .sidebar-footer {
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .logout-link {
        background-color: rgba(239, 68, 68, 0.14);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
        font-weight: 600;
        justify-content: center;
    }

    .logout-link i.nav-icon {
        color: #f87171;
    }

    .logout-link:hover {
        background-color: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
    }

    .logout-link:hover i.nav-icon {
        color: #ffffff;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            min-height: auto;
            height: auto;
        }
    }
</style>

<nav class="sidebar">
    <div>
        <div class="sidebar-brand-header">
            <div class="logo-circle">
                <img src="logo_derecha.png" alt="Logo Escuela" onerror="this.style.display='none';">
            </div>
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-title">GLADYS CARNEVALI</span>
                <span class="sidebar-brand-sub">Sistema de Inscripción</span>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="index.php" class="sidebar-link <?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>">
                    <span class="sidebar-link-left">
                        <i class="fas fa-home nav-icon"></i>
                        <span>Inicio</span>
                    </span>
                </a>
            </li>

            <li class="sidebar-item has-submenu">
                <a href="#" id="menuInscripcionTrigger" class="sidebar-link <?php echo $en_submenu_inscripcion ? 'active submenu-open' : ''; ?>" onclick="toggleMenuInscripcion(event)">
                    <span class="sidebar-link-left">
                        <i class="fas fa-user-plus nav-icon"></i>
                        <span>Gestión de Inscripción</span>
                    </span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu <?php echo $en_submenu_inscripcion ? 'show' : ''; ?>" id="listaInscripcionSubmenu">
                    <li>
                        <a href="agregar_alumno.php" class="submenu-link <?php echo ($pagina_actual == 'agregar_alumno.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i> Registrar
                        </a>
                    </li>
                    <li>
                        <a href="alumnos.php" class="submenu-link <?php echo ($pagina_actual == 'alumnos.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-search"></i> Consultar
                        </a>
                    </li>
                    <li>
                        <a href="planilla_inscripcion.php" class="submenu-link <?php echo ($pagina_actual == 'planilla_inscripcion.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-file-invoice"></i> Planilla de Inscripción
                        </a>
                    </li>
                    <li>
                        <a href="historial_planillas.php" class="submenu-link <?php echo ($pagina_actual == 'historial_planillas.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-history"></i> Historial de Planilla
                        </a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="retirados.php" class="sidebar-link <?php echo ($pagina_actual == 'retirados.php') ? 'active' : ''; ?>">
                    <span class="sidebar-link-left">
                        <i class="fas fa-user-slash nav-icon"></i>
                        <span>Retirados</span>
                    </span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="archivados.php" class="sidebar-link <?php echo ($pagina_actual == 'archivados.php') ? 'active' : ''; ?>">
                    <span class="sidebar-link-left">
                        <i class="fas fa-box-archive nav-icon"></i>
                        <span>Archivados</span>
                    </span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="calendario.php" class="sidebar-link <?php echo ($pagina_actual == 'calendario.php') ? 'active' : ''; ?>">
                    <span class="sidebar-link-left">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span>Calendario</span>
                    </span>
                </a>
            </li>

            <li class="sidebar-item has-submenu">
                <a href="#" id="menuConstanciasTrigger" class="sidebar-link <?php echo $en_submenu_constancias ? 'active submenu-open' : ''; ?>" onclick="toggleMenuConstancias(event)">
                    <span class="sidebar-link-left">
                        <i class="fas fa-file-alt nav-icon"></i>
                        <span>Constancias</span>
                    </span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu <?php echo $en_submenu_constancias ? 'show' : ''; ?>" id="listaConstanciasSubmenu">
                    <li>
                        <a href="constancias.php" class="submenu-link <?php echo ($pagina_actual == 'constancias.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-file-alt"></i> Constancia de Estudio
                        </a>
                    </li>
                    <li>
                        <a href="historial_constancias.php" class="submenu-link <?php echo ($pagina_actual == 'historial_constancias.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-history"></i> Historial de Constancia
                        </a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="estadisticas.php" class="sidebar-link <?php echo ($pagina_actual == 'estadisticas.php') ? 'active' : ''; ?>">
                    <span class="sidebar-link-left">
                        <i class="fas fa-chart-bar nav-icon"></i>
                        <span>Estadisticas</span>
                    </span>
                </a>
            </li>

            <li class="sidebar-item">
                <a href="docentes.php" class="sidebar-link <?php echo ($pagina_actual == 'docentes.php') ? 'active' : ''; ?>">
                    <span class="sidebar-link-left">
                        <i class="fas fa-chalkboard-teacher nav-icon"></i>
                        <span>Docentes</span>
                    </span>
                </a>
            </li>

            <?php if ($es_administrador): ?>
            <li class="sidebar-item has-submenu">
                <a href="#" id="menuSeguridadTrigger" class="sidebar-link <?php echo $en_submenu_seguridad ? 'active submenu-open' : ''; ?>" onclick="toggleMenuSeguridad(event)">
                    <span class="sidebar-link-left">
                        <i class="fas fa-shield-alt nav-icon"></i>
                        <span>Seguridad / Herramientas</span>
                    </span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu <?php echo $en_submenu_seguridad ? 'show' : ''; ?>" id="listaSeguridadSubmenu">
                    <li>
                        <a href="usuarios.php" class="submenu-link <?php echo ($pagina_actual == 'usuarios.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-users"></i> Usuarios
                        </a>
                    </li>
                    <li>
                        <a href="autoridades.php" class="submenu-link <?php echo ($pagina_actual == 'autoridades.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-user-tie"></i> Autoridades
                        </a>
                    </li>
                    <li>
                        <a href="configuracion.php" class="submenu-link <?php echo ($pagina_actual == 'configuracion.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-school"></i> Configuración
                        </a>
                    </li>
                    <li>
                        <a href="historial.php" class="submenu-link <?php echo ($pagina_actual == 'historial.php') ? 'sub-active' : ''; ?>">
                            <i class="fas fa-history"></i> Historial
                        </a>
                    </li>
                </ul>
            </li>

            <li class="sidebar-item">
                <a href="mantenimiento.php" class="sidebar-link <?php echo ($pagina_actual == 'mantenimiento.php') ? 'active' : ''; ?>">
                    <span class="sidebar-link-left">
                        <i class="fas fa-tools nav-icon"></i>
                        <span>Mantenimiento</span>
                    </span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <ul class="sidebar-menu sidebar-footer">
        <li class="sidebar-item">
            <a href="procesar.php?action=logout" class="sidebar-link logout-link" onclick="return confirm('¿Desea cerrar su sesión en el sistema?')">
                <span class="sidebar-link-left">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <span>Cerrar Sesión</span>
                </span>
            </a>
        </li>
    </ul>
</nav>

<script>
    function toggleMenuInscripcion(e) {
        if (e) e.preventDefault();
        const submenu = document.getElementById('listaInscripcionSubmenu');
        const trigger = document.getElementById('menuInscripcionTrigger');
        if (submenu) submenu.classList.toggle('show');
        if (trigger) trigger.classList.toggle('submenu-open');
    }

    function toggleMenuConstancias(e) {
        if (e) e.preventDefault();
        const submenu = document.getElementById('listaConstanciasSubmenu');
        const trigger = document.getElementById('menuConstanciasTrigger');
        if (submenu) submenu.classList.toggle('show');
        if (trigger) trigger.classList.toggle('submenu-open');
    }

    function toggleMenuSeguridad(e) {
        if (e) e.preventDefault();
        const submenu = document.getElementById('listaSeguridadSubmenu');
        const trigger = document.getElementById('menuSeguridadTrigger');
        if (submenu) submenu.classList.toggle('show');
        if (trigger) trigger.classList.toggle('submenu-open');
    }
</script>