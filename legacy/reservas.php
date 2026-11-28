<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($accion == 'crear') {
        $socio_id = isset($_POST['socio_id']) ? (int)$_POST['socio_id'] : 0;
        $libro_id = isset($_POST['libro_id']) ? (int)$_POST['libro_id'] : 0;

        $consulta = $db->prepare('SELECT * FROM socios WHERE id = ?');
        $consulta->execute(array($socio_id));
        $socio = $consulta->fetch();
        if (!$socio) {
            redirigir('reservas.php', 'Elige un socio.', 'error');
        }

        $consulta = $db->prepare('SELECT * FROM libros WHERE id = ?');
        $consulta->execute(array($libro_id));
        $libro = $consulta->fetch();
        if (!$libro) {
            redirigir('reservas.php', 'Elige un libro.', 'error');
        }

        $consulta = $db->prepare("SELECT COUNT(*) FROM ejemplares WHERE libro_id = ? AND estado = 'disponible'");
        $consulta->execute(array($libro_id));
        if ($consulta->fetchColumn() > 0) {
            redirigir('reservas.php', 'El libro tiene ejemplares disponibles: no hace falta reservarlo.', 'error');
        }

        $consulta = $db->prepare("SELECT id FROM reservas WHERE libro_id = ? AND socio_id = ? AND estado = 'pendiente'");
        $consulta->execute(array($libro_id, $socio_id));
        if ($consulta->fetch()) {
            redirigir('reservas.php', 'El socio ya tiene una reserva pendiente de ese libro.', 'error');
        }

        $ahora = ahora();
        $consulta = $db->prepare("INSERT INTO reservas (libro_id, socio_id, fecha_reserva, estado) VALUES (?, ?, ?, 'pendiente')");
        $consulta->execute(array($libro_id, $socio_id, $ahora));
        redirigir('reservas.php?libro_id=' . $libro_id, 'Reserva registrada para ' . $socio['nombre'] . '.');
    }

    if ($accion == 'cancelar') {
        $reserva_id = isset($_POST['reserva_id']) ? (int)$_POST['reserva_id'] : 0;

        $consulta = $db->prepare('SELECT * FROM reservas WHERE id = ?');
        $consulta->execute(array($reserva_id));
        $reserva = $consulta->fetch();
        if (!$reserva) {
            redirigir('reservas.php', 'La reserva no existe.', 'error');
        }
        if ($reserva['estado'] != 'pendiente' && $reserva['estado'] != 'asignada') {
            redirigir('reservas.php', 'Esa reserva ya no se puede cancelar.', 'error');
        }

        $consulta = $db->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ?");
        $consulta->execute(array($reserva_id));
        $msg = 'Reserva cancelada.';

        // si tenía un ejemplar apartado, pasa a la siguiente de la cola
        if ($reserva['estado'] == 'asignada') {
            $ejemplar_id = (int)$reserva['ejemplar_id'];
            $libro_id = (int)$reserva['libro_id'];
            $consulta = $db->prepare("SELECT r.id, s.nombre
                FROM reservas r
                JOIN socios s ON s.id = r.socio_id
                WHERE r.libro_id = ? AND r.estado = 'pendiente'
                ORDER BY s.nombre ASC
                LIMIT 1");
            $consulta->execute(array($libro_id));
            $sig = $consulta->fetch();
            if ($sig) {
                $consulta = $db->prepare("UPDATE reservas SET estado = 'asignada', ejemplar_id = ? WHERE id = ?");
                $consulta->execute(array($ejemplar_id, (int)$sig['id']));
                $consulta = $db->prepare("UPDATE ejemplares SET estado = 'reservado' WHERE id = ?");
                $consulta->execute(array($ejemplar_id));
                $msg .= ' Ejemplar apartado para ' . $sig['nombre'];
            } else {
                $consulta = $db->prepare("UPDATE ejemplares SET estado = 'disponible' WHERE id = ?");
                $consulta->execute(array($ejemplar_id));
                $msg .= ' El ejemplar quedó disponible.';
            }
        }

        redirigir('reservas.php', $msg);
    }
}

$libro_cola = isset($_GET['libro_id']) ? (int)$_GET['libro_id'] : 0;

$socios = $db->query("SELECT id, nombre FROM socios WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();
$libros = $db->query("SELECT id, titulo, autor FROM libros ORDER BY titulo ASC")->fetchAll();

if ($libro_cola) {
    $consulta = $db->prepare('SELECT titulo FROM libros WHERE id = ?');
    $consulta->execute(array($libro_cola));
    $lc = $consulta->fetch();
    $consulta = $db->prepare("SELECT r.*, s.nombre AS socio
        FROM reservas r
        JOIN socios s ON s.id = r.socio_id
        WHERE r.libro_id = ? AND r.estado IN ('pendiente', 'asignada')
        ORDER BY r.fecha_reserva ASC");
    $consulta->execute(array($libro_cola));
    $cola = $consulta->fetchAll();
}

$reservas = $db->query("SELECT r.*, s.nombre AS socio, l.titulo, e.codigo
    FROM reservas r
    JOIN socios s ON s.id = r.socio_id
    JOIN libros l ON l.id = r.libro_id
    LEFT JOIN ejemplares e ON e.id = r.ejemplar_id
    WHERE r.estado IN ('pendiente', 'asignada')
    ORDER BY l.titulo ASC, r.fecha_reserva ASC")->fetchAll();

$titulo = 'Reservas';
include 'includes/cabecera.php';
?>
<h2>Reservas</h2>

<?php if ($libro_cola && $lc) { ?>
<h3>Cola de «<?php echo htmlspecialchars($lc['titulo']); ?>»</h3>
<?php if (count($cola) == 0) { ?>
    <p>Este libro no tiene reservas en cola.</p>
<?php } else { ?>
<table>
    <tr>
        <th class="num">Lugar</th>
        <th>Socio</th>
        <th>Reservado el</th>
        <th>Estado</th>
    </tr>
    <?php $lugar = 1; foreach ($cola as $c) { ?>
    <tr>
        <td class="num"><?php echo $lugar++; ?></td>
        <td><?php echo htmlspecialchars($c['socio']); ?></td>
        <td><?php echo date('d/m/Y H:i', strtotime($c['fecha_reserva'])); ?></td>
        <td><?php echo ucfirst($c['estado']); ?></td>
    </tr>
    <?php } ?>
</table>
<?php } ?>
<?php } ?>

<h3>Reservas vigentes</h3>
<?php if (count($reservas) == 0) { ?>
    <p>No hay reservas vigentes.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Libro</th>
        <th>Socio</th>
        <th>Reservado el</th>
        <th>Estado</th>
        <th>Ejemplar</th>
        <th>Acciones</th>
    </tr>
    <?php foreach ($reservas as $r) { ?>
    <tr>
        <td><a href="reservas.php?libro_id=<?php echo $r['libro_id']; ?>"><?php echo htmlspecialchars($r['titulo']); ?></a></td>
        <td><?php echo htmlspecialchars($r['socio']); ?></td>
        <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_reserva'])); ?></td>
        <td><?php echo ucfirst($r['estado']); ?></td>
        <td><?php echo $r['codigo'] ? htmlspecialchars($r['codigo']) : '-'; ?></td>
        <td>
            <form class="en-linea" method="post" action="reservas.php" onsubmit="return confirm('¿Cancelar esta reserva?');">
                <input type="hidden" name="accion" value="cancelar">
                <input type="hidden" name="reserva_id" value="<?php echo $r['id']; ?>">
                <button type="submit" class="peligro">Cancelar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
</table>
<?php } ?>

<h3>Nueva reserva</h3>
<form class="formulario" method="post" action="reservas.php">
    <input type="hidden" name="accion" value="crear">
    <p>
        <label for="socio_id">Socio</label>
        <select id="socio_id" name="socio_id">
            <option value="">Elige un socio</option>
            <?php foreach ($socios as $s) { ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
            <?php } ?>
        </select>
    </p>
    <p>
        <label for="libro_id">Libro</label>
        <select id="libro_id" name="libro_id">
            <option value="">Elige un libro</option>
            <?php foreach ($libros as $l) { ?>
            <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['titulo']); ?> - <?php echo htmlspecialchars($l['autor']); ?></option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">Solo se puede reservar un libro cuando no tiene ejemplares disponibles.</p>
    <p><input type="submit" value="Reservar"></p>
</form>
<?php include 'includes/pie.php'; ?>
