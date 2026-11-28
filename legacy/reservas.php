<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
include 'includes/servicios.php';
include 'includes/mensajes.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($accion == 'crear') {
        $socio_id = isset($_POST['socio_id']) ? (int)$_POST['socio_id'] : 0;
        $libro_id = isset($_POST['libro_id']) ? (int)$_POST['libro_id'] : 0;

        $resultado = servicios()['reservar']->handle($socio_id, $libro_id);
        if (!$resultado->isSuccess()) {
            redirigir('reservas.php', mensaje_error('reservas', $resultado->getErrorCode()), 'error');
        }
        redirigir('reservas.php?libro_id=' . $resultado->getBookId(), 'Reserva registrada para ' . $resultado->getMemberName() . '.');
    }

    if ($accion == 'cancelar') {
        $reserva_id = isset($_POST['reserva_id']) ? (int)$_POST['reserva_id'] : 0;

        $resultado = servicios()['cancelar_reserva']->handle($reserva_id);
        if (!$resultado->isSuccess()) {
            redirigir('reservas.php', mensaje_error('reservas', $resultado->getErrorCode()), 'error');
        }

        $msg = 'Reserva cancelada.';
        if ($resultado->hadCopy()) {
            if ($resultado->getAssignedMemberName() !== null) {
                $msg .= ' Ejemplar apartado para ' . $resultado->getAssignedMemberName();
            } else {
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
