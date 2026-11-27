<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$r = mysqli_query($con, "SELECT * FROM socios WHERE id = $id");
if (mysqli_num_rows($r) == 0) {
    redirigir('socios.php', 'El socio no existe.', 'error');
}
$socio = mysqli_fetch_assoc($r);

$prestamos = mysqli_query($con, "SELECT p.*, l.titulo, e.codigo
    FROM prestamos p
    JOIN ejemplares e ON e.id = p.ejemplar_id
    JOIN libros l ON l.id = e.libro_id
    WHERE p.socio_id = $id
    ORDER BY p.fecha_prestamo DESC, p.id DESC");

$r = mysqli_query($con, "SELECT COALESCE(SUM(multa), 0) AS total FROM prestamos WHERE socio_id = $id AND multa > 0 AND multa_pagada = 0");
$f = mysqli_fetch_assoc($r);
$multas_pendientes = $f['total'];

$hoy = hoy();

$titulo = 'Socio ' . $socio['nombre'];
include 'includes/cabecera.php';
?>
<h2><?php echo htmlspecialchars($socio['nombre']); ?></h2>

<table>
    <tr>
        <th>Documento</th>
        <td><?php echo htmlspecialchars($socio['documento']); ?></td>
        <th>Correo</th>
        <td><?php echo htmlspecialchars($socio['correo']); ?></td>
    </tr>
    <tr>
        <th>Teléfono</th>
        <td><?php echo htmlspecialchars($socio['telefono']); ?></td>
        <th>Estado</th>
        <td><?php echo estado_socio($socio['activo']); ?> (socio desde el <?php echo formato_fecha($socio['fecha_alta']); ?>)</td>
    </tr>
</table>

<div class="barra">
    <a href="socio_editar.php?id=<?php echo $socio['id']; ?>">Editar socio</a>
</div>

<h3>Préstamos</h3>
<?php if (mysqli_num_rows($prestamos) == 0) { ?>
    <p>Este socio todavía no tiene préstamos.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Libro</th>
        <th>Ejemplar</th>
        <th>Prestado</th>
        <th>Vence</th>
        <th>Devuelto</th>
        <th class="num">Multa</th>
    </tr>
    <?php while ($p = mysqli_fetch_assoc($prestamos)) { ?>
    <tr>
        <td><?php echo htmlspecialchars($p['titulo']); ?></td>
        <td><?php echo htmlspecialchars($p['codigo']); ?></td>
        <td><?php echo formato_fecha($p['fecha_prestamo']); ?></td>
        <td<?php if ($p['fecha_devolucion'] == null && $p['fecha_vence'] < $hoy) { echo ' class="vencido"'; } ?>><?php echo formato_fecha($p['fecha_vence']); ?></td>
        <td><?php echo $p['fecha_devolucion'] ? formato_fecha($p['fecha_devolucion']) : 'Sin devolver'; ?></td>
        <td class="num">
            <?php
            if ($p['multa'] > 0) {
                echo formato_moneda($p['multa']) . ($p['multa_pagada'] ? ' (pagada)' : ' (pendiente)');
            } else {
                echo '-';
            }
            ?>
        </td>
    </tr>
    <?php } ?>
</table>
<?php } ?>

<h3>Multas</h3>
<?php if ($multas_pendientes > 0) { ?>
    <p>Multas pendientes de pago: <strong><?php echo formato_moneda($multas_pendientes); ?></strong>. <a href="multas.php">Ir a multas</a></p>
<?php } else { ?>
    <p>El socio no tiene multas pendientes.</p>
<?php } ?>
<?php include 'includes/pie.php'; ?>
