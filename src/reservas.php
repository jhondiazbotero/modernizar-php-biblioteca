<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($accion == 'crear') {
        $socio_id = isset($_POST['socio_id']) ? (int)$_POST['socio_id'] : 0;
        $libro_id = isset($_POST['libro_id']) ? (int)$_POST['libro_id'] : 0;

        $r = mysqli_query($con, "SELECT * FROM socios WHERE id = $socio_id");
        if (mysqli_num_rows($r) == 0) {
            redirigir('reservas.php', 'Elige un socio.', 'error');
        }
        $socio = mysqli_fetch_assoc($r);

        $r = mysqli_query($con, "SELECT * FROM libros WHERE id = $libro_id");
        if (mysqli_num_rows($r) == 0) {
            redirigir('reservas.php', 'Elige un libro.', 'error');
        }
        $libro = mysqli_fetch_assoc($r);

        $r = mysqli_query($con, "SELECT COUNT(*) AS n FROM ejemplares WHERE libro_id = $libro_id AND estado = 'disponible'");
        $f = mysqli_fetch_assoc($r);
        if ($f['n'] > 0) {
            redirigir('reservas.php', 'El libro tiene ejemplares disponibles: no hace falta reservarlo.', 'error');
        }

        $r = mysqli_query($con, "SELECT id FROM reservas WHERE libro_id = $libro_id AND socio_id = $socio_id AND estado = 'pendiente'");
        if (mysqli_num_rows($r) > 0) {
            redirigir('reservas.php', 'El socio ya tiene una reserva pendiente de ese libro.', 'error');
        }

        $ahora = hoy() . ' ' . date('H:i:s');
        mysqli_query($con, "INSERT INTO reservas (libro_id, socio_id, fecha_reserva, estado) VALUES ($libro_id, $socio_id, '$ahora', 'pendiente')");
        redirigir('reservas.php?libro_id=' . $libro_id, 'Reserva registrada para ' . $socio['nombre'] . '.');
    }

    if ($accion == 'cancelar') {
        $reserva_id = isset($_POST['reserva_id']) ? (int)$_POST['reserva_id'] : 0;

        $r = mysqli_query($con, "SELECT * FROM reservas WHERE id = $reserva_id");
        if (mysqli_num_rows($r) == 0) {
            redirigir('reservas.php', 'La reserva no existe.', 'error');
        }
        $reserva = mysqli_fetch_assoc($r);
        if ($reserva['estado'] != 'pendiente' && $reserva['estado'] != 'asignada') {
            redirigir('reservas.php', 'Esa reserva ya no se puede cancelar.', 'error');
        }

        mysqli_query($con, "UPDATE reservas SET estado = 'cancelada' WHERE id = $reserva_id");
        $msg = 'Reserva cancelada.';

        // si tenía un ejemplar apartado, pasa a la siguiente de la cola
        if ($reserva['estado'] == 'asignada') {
            $ejemplar_id = (int)$reserva['ejemplar_id'];
            $libro_id = (int)$reserva['libro_id'];
            $r = mysqli_query($con, "SELECT r.id, s.nombre
                FROM reservas r
                JOIN socios s ON s.id = r.socio_id
                WHERE r.libro_id = $libro_id AND r.estado = 'pendiente'
                ORDER BY s.nombre ASC
                LIMIT 1");
            if (mysqli_num_rows($r) > 0) {
                $sig = mysqli_fetch_assoc($r);
                mysqli_query($con, "UPDATE reservas SET estado = 'asignada', ejemplar_id = $ejemplar_id WHERE id = " . (int)$sig['id']);
                mysqli_query($con, "UPDATE ejemplares SET estado = 'reservado' WHERE id = $ejemplar_id");
                $msg .= ' Ejemplar apartado para ' . $sig['nombre'];
            } else {
                mysqli_query($con, "UPDATE ejemplares SET estado = 'disponible' WHERE id = $ejemplar_id");
                $msg .= ' El ejemplar quedó disponible.';
            }
        }

        redirigir('reservas.php', $msg);
    }
}

$libro_cola = isset($_GET['libro_id']) ? (int)$_GET['libro_id'] : 0;

$socios = mysqli_query($con, "SELECT id, nombre FROM socios WHERE activo = 1 ORDER BY nombre ASC");
$libros = mysqli_query($con, "SELECT id, titulo, autor FROM libros ORDER BY titulo ASC");

if ($libro_cola) {
    $r = mysqli_query($con, "SELECT titulo FROM libros WHERE id = $libro_cola");
    $lc = mysqli_fetch_assoc($r);
    $cola = mysqli_query($con, "SELECT r.*, s.nombre AS socio
        FROM reservas r
        JOIN socios s ON s.id = r.socio_id
        WHERE r.libro_id = $libro_cola AND r.estado IN ('pendiente', 'asignada')
        ORDER BY r.fecha_reserva ASC");
}

$reservas = mysqli_query($con, "SELECT r.*, s.nombre AS socio, l.titulo, e.codigo
    FROM reservas r
    JOIN socios s ON s.id = r.socio_id
    JOIN libros l ON l.id = r.libro_id
    LEFT JOIN ejemplares e ON e.id = r.ejemplar_id
    WHERE r.estado IN ('pendiente', 'asignada')
    ORDER BY l.titulo ASC, r.fecha_reserva ASC");

$titulo = 'Reservas';
include 'includes/cabecera.php';
?>
<h2>Reservas</h2>

<?php if ($libro_cola && $lc) { ?>
<h3>Cola de «<?php echo htmlspecialchars($lc['titulo']); ?>»</h3>
<?php if (mysqli_num_rows($cola) == 0) { ?>
    <p>Este libro no tiene reservas en cola.</p>
<?php } else { ?>
<table>
    <tr>
        <th class="num">Lugar</th>
        <th>Socio</th>
        <th>Reservado el</th>
        <th>Estado</th>
    </tr>
    <?php $lugar = 1; while ($c = mysqli_fetch_assoc($cola)) { ?>
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
<?php if (mysqli_num_rows($reservas) == 0) { ?>
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
    <?php while ($r = mysqli_fetch_assoc($reservas)) { ?>
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
            <?php while ($s = mysqli_fetch_assoc($socios)) { ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?></option>
            <?php } ?>
        </select>
    </p>
    <p>
        <label for="libro_id">Libro</label>
        <select id="libro_id" name="libro_id">
            <option value="">Elige un libro</option>
            <?php while ($l = mysqli_fetch_assoc($libros)) { ?>
            <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['titulo']); ?> - <?php echo htmlspecialchars($l['autor']); ?></option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">Solo se puede reservar un libro cuando no tiene ejemplares disponibles.</p>
    <p><input type="submit" value="Reservar"></p>
</form>
<?php include 'includes/pie.php'; ?>
