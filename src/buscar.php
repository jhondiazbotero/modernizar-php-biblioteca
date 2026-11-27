<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$q = isset($_GET['q']) ? $_GET['q'] : '';
$resultado = null;
$error_sql = '';

if ($q != '') {
    $resultado = mysqli_query($con, "SELECT l.*, (SELECT COUNT(*) FROM ejemplares e WHERE e.libro_id = l.id AND e.estado = 'disponible') AS disponibles FROM libros l WHERE titulo LIKE '%" . $_GET['q'] . "%' OR autor LIKE '%" . $_GET['q'] . "%' ORDER BY titulo ASC");
    if (!$resultado) {
        $error_sql = mysqli_error($con);
    }
}

$titulo = 'Buscar libros';
include 'includes/cabecera.php';
?>
<h2>Buscar libros</h2>

<form class="formulario" method="get" action="buscar.php">
    <p>
        <label for="q">Título o autor</label>
        <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($q); ?>">
    </p>
    <p><input type="submit" value="Buscar"></p>
</form>

<?php if ($error_sql != '') { ?>
    <div class="mensaje error"><?php echo $error_sql; ?></div>
<?php } elseif ($resultado) { ?>
    <?php if (mysqli_num_rows($resultado) == 0) { ?>
    <p>No se encontraron libros para «<?php echo htmlspecialchars($q); ?>».</p>
    <?php } else { ?>
    <p><?php echo mysqli_num_rows($resultado); ?> resultado(s).</p>
    <table>
        <tr>
            <th>Título</th>
            <th>Autor</th>
            <th>Año</th>
            <th class="num">Disponibles</th>
            <th>Acciones</th>
        </tr>
        <?php while ($l = mysqli_fetch_assoc($resultado)) { ?>
        <tr>
            <td><?php echo htmlspecialchars($l['titulo']); ?></td>
            <td><?php echo htmlspecialchars($l['autor']); ?></td>
            <td><?php echo $l['anio']; ?></td>
            <td class="num"><?php echo $l['disponibles']; ?></td>
            <td><a href="ejemplares.php?libro_id=<?php echo $l['id']; ?>">Ejemplares</a></td>
        </tr>
        <?php } ?>
    </table>
    <?php } ?>
<?php } ?>
<?php include 'includes/pie.php'; ?>
