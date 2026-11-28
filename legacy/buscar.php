<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$q = (isset($_GET['q']) && is_string($_GET['q'])) ? $_GET['q'] : '';
$libros = null;

if ($q != '') {
    // El texto se busca tal cual: %, _ y \ llevan una barra para que no sean comodines.
    $patron = '%' . addcslashes($q, '%_\\') . '%';
    $consulta = $db->prepare("SELECT l.*, (SELECT COUNT(*) FROM ejemplares e WHERE e.libro_id = l.id AND e.estado = 'disponible') AS disponibles FROM libros l WHERE titulo LIKE ? ESCAPE '\\\\' OR autor LIKE ? ESCAPE '\\\\' ORDER BY titulo ASC");
    $consulta->execute(array($patron, $patron));
    $libros = $consulta->fetchAll();
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

<?php if ($libros !== null) { ?>
    <?php if (count($libros) == 0) { ?>
    <p>No se encontraron libros para «<?php echo htmlspecialchars($q); ?>».</p>
    <?php } else { ?>
    <p><?php echo count($libros); ?> resultado(s).</p>
    <table>
        <tr>
            <th>Título</th>
            <th>Autor</th>
            <th>Año</th>
            <th class="num">Disponibles</th>
            <th>Acciones</th>
        </tr>
        <?php foreach ($libros as $l) { ?>
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
