<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$hoy = hoy();
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'activos';

// Cada lista es una consulta completa: el filtro de la URL solo elige cuál se corre.
if ($filtro == 'vencidos') {
    $consulta = $db->prepare('SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
        FROM prestamos p
        JOIN socios s ON s.id = p.socio_id
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        WHERE p.fecha_devolucion IS NULL AND p.fecha_vence < ?
        ORDER BY p.fecha_vence ASC');
    $consulta->execute(array($hoy));
} elseif ($filtro == 'devueltos') {
    $consulta = $db->query('SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
        FROM prestamos p
        JOIN socios s ON s.id = p.socio_id
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        WHERE p.fecha_devolucion IS NOT NULL
        ORDER BY p.fecha_devolucion DESC, p.id DESC');
} else {
    $filtro = 'activos';
    $consulta = $db->query('SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
        FROM prestamos p
        JOIN socios s ON s.id = p.socio_id
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        WHERE p.fecha_devolucion IS NULL
        ORDER BY p.fecha_vence ASC');
}
$prestamos = $consulta->fetchAll();

$titulo = 'Préstamos';
include 'includes/cabecera.php';
?>
<h2>Préstamos</h2>

<div class="barra">
    <a href="prestamos.php?filtro=activos"<?php if ($filtro == 'activos') { echo ' class="activo"'; } ?>>Activos</a>
    <a href="prestamos.php?filtro=vencidos"<?php if ($filtro == 'vencidos') { echo ' class="activo"'; } ?>>Vencidos</a>
    <a href="prestamos.php?filtro=devueltos"<?php if ($filtro == 'devueltos') { echo ' class="activo"'; } ?>>Devueltos</a>
    <a href="prestar.php">Nuevo préstamo</a>
</div>

<?php if (count($prestamos) == 0) { ?>
    <p>No hay préstamos en esta lista.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Ejemplar</th>
        <th>Prestado</th>
        <th>Vence</th>
        <?php if ($filtro == 'devueltos') { ?>
        <th>Devuelto</th>
        <th class="num">Multa</th>
        <?php } else { ?>
        <th>Acciones</th>
        <?php } ?>
    </tr>
    <?php foreach ($prestamos as $p) { ?>
    <tr>
        <td><a href="socio_ver.php?id=<?php echo $p['socio_id']; ?>"><?php echo htmlspecialchars($p['socio']); ?></a></td>
        <td><?php echo htmlspecialchars($p['titulo']); ?></td>
        <td><?php echo htmlspecialchars($p['codigo']); ?></td>
        <td><?php echo formato_fecha($p['fecha_prestamo']); ?></td>
        <td<?php if ($p['fecha_devolucion'] == null && $p['fecha_vence'] < $hoy) { echo ' class="vencido"'; } ?>><?php echo formato_fecha($p['fecha_vence']); ?></td>
        <?php if ($filtro == 'devueltos') { ?>
        <td><?php echo formato_fecha($p['fecha_devolucion']); ?></td>
        <td class="num"><?php echo $p['multa'] > 0 ? formato_moneda($p['multa']) : '-'; ?></td>
        <?php } else { ?>
        <td>
            <form class="en-linea" method="post" action="devolver.php">
                <input type="hidden" name="prestamo_id" value="<?php echo $p['id']; ?>">
                <button type="submit">Devolver</button>
            </form>
        </td>
        <?php } ?>
    </tr>
    <?php } ?>
</table>
<?php } ?>
<?php include 'includes/pie.php'; ?>
