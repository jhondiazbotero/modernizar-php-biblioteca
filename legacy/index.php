<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$hoy = hoy();

$r = mysqli_query($con, "SELECT COUNT(*) AS n FROM prestamos WHERE fecha_devolucion IS NULL");
$f = mysqli_fetch_assoc($r);
$activos = $f['n'];

$r = mysqli_query($con, "SELECT COUNT(*) AS n FROM prestamos WHERE fecha_devolucion IS NULL AND fecha_vence < '$hoy'");
$f = mysqli_fetch_assoc($r);
$n_vencidos = $f['n'];

$r = mysqli_query($con, "SELECT COUNT(*) AS n FROM reservas WHERE estado = 'pendiente'");
$f = mysqli_fetch_assoc($r);
$n_reservas = $f['n'];

$vencidos = mysqli_query($con, "SELECT p.id, p.fecha_vence, s.id AS socio_id, s.nombre AS socio, l.titulo, e.codigo
    FROM prestamos p
    JOIN socios s ON s.id = p.socio_id
    JOIN ejemplares e ON e.id = p.ejemplar_id
    JOIN libros l ON l.id = e.libro_id
    WHERE p.fecha_devolucion IS NULL AND p.fecha_vence < '$hoy'
    ORDER BY p.fecha_vence ASC");

$pendientes = mysqli_query($con, "SELECT r.id, r.fecha_reserva, s.nombre AS socio, l.id AS libro_id, l.titulo
    FROM reservas r
    JOIN socios s ON s.id = r.socio_id
    JOIN libros l ON l.id = r.libro_id
    WHERE r.estado = 'pendiente'
    ORDER BY r.fecha_reserva ASC");

$titulo = 'Inicio';
include 'includes/cabecera.php';
?>
<h2>Tablero</h2>

<div class="tarjetas">
    <div class="tarjeta">
        <div class="cifra"><?php echo $activos; ?></div>
        Préstamos activos
    </div>
    <div class="tarjeta alerta">
        <div class="cifra"><?php echo $n_vencidos; ?></div>
        Préstamos vencidos
    </div>
    <div class="tarjeta">
        <div class="cifra"><?php echo $n_reservas; ?></div>
        Reservas pendientes
    </div>
</div>

<h3>Préstamos vencidos</h3>
<?php if (mysqli_num_rows($vencidos) == 0) { ?>
    <p>No hay préstamos vencidos.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Ejemplar</th>
        <th>Vencía</th>
    </tr>
    <?php while ($v = mysqli_fetch_assoc($vencidos)) { ?>
    <tr>
        <td><a href="socio_ver.php?id=<?php echo $v['socio_id']; ?>"><?php echo htmlspecialchars($v['socio']); ?></a></td>
        <td><?php echo htmlspecialchars($v['titulo']); ?></td>
        <td><?php echo htmlspecialchars($v['codigo']); ?></td>
        <td class="vencido"><?php echo formato_fecha($v['fecha_vence']); ?></td>
    </tr>
    <?php } ?>
</table>
<?php } ?>

<h3>Reservas pendientes</h3>
<?php if (mysqli_num_rows($pendientes) == 0) { ?>
    <p>No hay reservas pendientes.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Reservado el</th>
    </tr>
    <?php while ($r = mysqli_fetch_assoc($pendientes)) { ?>
    <tr>
        <td><?php echo htmlspecialchars($r['socio']); ?></td>
        <td><a href="reservas.php?libro_id=<?php echo $r['libro_id']; ?>"><?php echo htmlspecialchars($r['titulo']); ?></a></td>
        <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_reserva'])); ?></td>
    </tr>
    <?php } ?>
</table>
<?php } ?>
<?php include 'includes/pie.php'; ?>
