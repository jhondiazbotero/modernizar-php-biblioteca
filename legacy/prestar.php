<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $socio_id = isset($_POST['socio_id']) ? (int)$_POST['socio_id'] : 0;
    $ejemplar_id = isset($_POST['ejemplar_id']) ? (int)$_POST['ejemplar_id'] : 0;

    $consulta = $db->prepare('SELECT * FROM socios WHERE id = ?');
    $consulta->execute(array($socio_id));
    $socio = $consulta->fetch();
    if (!$socio) {
        redirigir('prestar.php', 'Elige un socio.', 'error');
    }

    $consulta = $db->prepare('SELECT e.*, l.titulo FROM ejemplares e JOIN libros l ON l.id = e.libro_id WHERE e.id = ?');
    $consulta->execute(array($ejemplar_id));
    $ejemplar = $consulta->fetch();
    if (!$ejemplar) {
        redirigir('prestar.php', 'Elige un ejemplar.', 'error');
    }

    if (!$socio['activo']) {
        redirigir('prestar.php', 'El socio está inactivo y no puede recibir préstamos.', 'error');
    }

    if (contar_prestamos_activos($socio_id) >= 3) {
        redirigir('prestar.php', 'El socio ya tiene 3 préstamos activos.', 'error');
    }

    if (contar_multas_sin_pagar($socio_id) > 0) {
        redirigir('prestar.php', 'El socio tiene multas sin pagar.', 'error');
    }

    $reserva_id = 0;
    if ($ejemplar['estado'] == 'prestado') {
        redirigir('prestar.php', 'El ejemplar ya está prestado.', 'error');
    } elseif ($ejemplar['estado'] == 'baja') {
        redirigir('prestar.php', 'El ejemplar está dado de baja.', 'error');
    } elseif ($ejemplar['estado'] == 'reservado') {
        $consulta = $db->prepare("SELECT id FROM reservas WHERE ejemplar_id = ? AND socio_id = ? AND estado = 'asignada'");
        $consulta->execute(array($ejemplar_id, $socio_id));
        $f = $consulta->fetch();
        if (!$f) {
            redirigir('prestar.php', 'El ejemplar está reservado para otro socio.', 'error');
        }
        $reserva_id = $f['id'];
    }

    $hoy = hoy();
    $vence = sumar_dias($hoy, 14);
    $consulta = $db->prepare('INSERT INTO prestamos (ejemplar_id, socio_id, fecha_prestamo, fecha_vence, multa, multa_pagada) VALUES (?, ?, ?, ?, 0, 0)');
    $consulta->execute(array($ejemplar_id, $socio_id, $hoy, $vence));
    $consulta = $db->prepare("UPDATE ejemplares SET estado = 'prestado' WHERE id = ?");
    $consulta->execute(array($ejemplar_id));
    if ($reserva_id) {
        $consulta = $db->prepare("UPDATE reservas SET estado = 'atendida' WHERE id = ?");
        $consulta->execute(array($reserva_id));
    }

    redirigir('prestamos.php', 'Préstamo registrado: ' . $ejemplar['titulo'] . ' para ' . $socio['nombre'] . '. Vence el ' . formato_fecha($vence) . '.');
}

$socios = $db->query("SELECT id, nombre, documento, activo FROM socios ORDER BY nombre ASC")->fetchAll();
$ejemplares = $db->query("SELECT e.id, e.codigo, e.estado, l.titulo
    FROM ejemplares e
    JOIN libros l ON l.id = e.libro_id
    WHERE e.estado <> 'baja'
    ORDER BY l.titulo ASC, e.codigo ASC")->fetchAll();

$titulo = 'Prestar un libro';
include 'includes/cabecera.php';
?>
<h2>Prestar un libro</h2>

<form class="formulario" method="post" action="prestar.php">
    <p>
        <label for="socio_id">Socio</label>
        <select id="socio_id" name="socio_id">
            <option value="">Elige un socio</option>
            <?php foreach ($socios as $s) { ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?> (<?php echo htmlspecialchars($s['documento']); ?>)<?php if (!$s['activo']) { echo ' - inactivo'; } ?></option>
            <?php } ?>
        </select>
    </p>
    <p>
        <label for="ejemplar_id">Ejemplar</label>
        <select id="ejemplar_id" name="ejemplar_id">
            <option value="">Elige un ejemplar</option>
            <?php foreach ($ejemplares as $e) { ?>
            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['titulo']); ?> - <?php echo htmlspecialchars($e['codigo']); ?> (<?php echo $e['estado']; ?>)</option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">El préstamo dura 14 días. Cada socio puede tener hasta 3 préstamos activos.</p>
    <p><input type="submit" value="Registrar préstamo"></p>
</form>
<?php include 'includes/pie.php'; ?>
