<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $prestamo_id = isset($_POST['prestamo_id']) ? (int)$_POST['prestamo_id'] : 0;

    $r = mysqli_query($con, "SELECT multa, multa_pagada FROM prestamos WHERE id = $prestamo_id");
    if (mysqli_num_rows($r) == 0) {
        redirigir('multas.php', 'El préstamo no existe.', 'error');
    }
    $p = mysqli_fetch_assoc($r);
    if ($p['multa'] <= 0) {
        redirigir('multas.php', 'Ese préstamo no tiene multa.', 'error');
    }
    if ($p['multa_pagada']) {
        redirigir('multas.php', 'Esa multa ya estaba pagada.', 'error');
    }

    mysqli_query($con, "UPDATE prestamos SET multa_pagada = 1 WHERE id = $prestamo_id");
    redirigir('multas.php', 'Multa pagada: ' . formato_moneda($p['multa']) . '.');
}

$multas = mysqli_query($con, "SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
    FROM prestamos p
    JOIN socios s ON s.id = p.socio_id
    JOIN ejemplares e ON e.id = p.ejemplar_id
    JOIN libros l ON l.id = e.libro_id
    WHERE p.multa > 0
    ORDER BY p.multa_pagada ASC, p.fecha_devolucion DESC");

$titulo = 'Multas';
include 'includes/cabecera.php';
?>
<h2>Multas</h2>

<?php if (mysqli_num_rows($multas) == 0) { ?>
    <p>No hay multas registradas.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Vencía</th>
        <th>Devuelto</th>
        <th class="num">Multa</th>
        <th>Estado</th>
    </tr>
    <?php while ($m = mysqli_fetch_assoc($multas)) { ?>
    <tr>
        <td><a href="socio_ver.php?id=<?php echo $m['socio_id']; ?>"><?php echo htmlspecialchars($m['socio']); ?></a></td>
        <td><?php echo htmlspecialchars($m['titulo']); ?></td>
        <td><?php echo formato_fecha($m['fecha_vence']); ?></td>
        <td><?php echo formato_fecha($m['fecha_devolucion']); ?></td>
        <td class="num"><?php echo formato_moneda($m['multa']); ?></td>
        <td>
            <?php if ($m['multa_pagada']) { ?>
                Pagada
            <?php } else { ?>
            <form class="en-linea" method="post" action="multas.php">
                <input type="hidden" name="prestamo_id" value="<?php echo $m['id']; ?>">
                <button type="submit">Pagar multa</button>
            </form>
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
</table>
<?php } ?>
<?php include 'includes/pie.php'; ?>
