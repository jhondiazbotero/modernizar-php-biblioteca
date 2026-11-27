<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$libro_id = isset($_GET['libro_id']) ? (int)$_GET['libro_id'] : 0;
if (isset($_POST['libro_id'])) {
    $libro_id = (int)$_POST['libro_id'];
}

$r = mysqli_query($con, "SELECT * FROM libros WHERE id = $libro_id");
if (mysqli_num_rows($r) == 0) {
    redirigir('libros.php', 'El libro no existe.', 'error');
}
$libro = mysqli_fetch_assoc($r);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($accion == 'alta') {
        $codigo = mysqli_real_escape_string($con, trim(isset($_POST['codigo']) ? $_POST['codigo'] : ''));
        if ($codigo == '') {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'Escribe el código del ejemplar.', 'error');
        }
        $r = mysqli_query($con, "SELECT id FROM ejemplares WHERE codigo = '$codigo'");
        if (mysqli_num_rows($r) > 0) {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'Ya existe un ejemplar con ese código.', 'error');
        }
        mysqli_query($con, "INSERT INTO ejemplares (libro_id, codigo, estado) VALUES ($libro_id, '$codigo', 'disponible')");
        redirigir('ejemplares.php?libro_id=' . $libro_id, 'Ejemplar agregado.');
    }

    if ($accion == 'baja') {
        $ejemplar_id = isset($_POST['ejemplar_id']) ? (int)$_POST['ejemplar_id'] : 0;
        $r = mysqli_query($con, "SELECT estado FROM ejemplares WHERE id = $ejemplar_id AND libro_id = $libro_id");
        if (mysqli_num_rows($r) == 0) {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'El ejemplar no existe.', 'error');
        }
        $e = mysqli_fetch_assoc($r);
        if ($e['estado'] == 'prestado' || $e['estado'] == 'reservado') {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'No se puede dar de baja un ejemplar prestado o reservado.', 'error');
        }
        mysqli_query($con, "UPDATE ejemplares SET estado = 'baja' WHERE id = $ejemplar_id");
        redirigir('ejemplares.php?libro_id=' . $libro_id, 'Ejemplar dado de baja.');
    }
}

$ejemplares = mysqli_query($con, "SELECT * FROM ejemplares WHERE libro_id = $libro_id ORDER BY codigo ASC");

$titulo = 'Ejemplares de ' . $libro['titulo'];
include 'includes/cabecera.php';
?>
<h2>Ejemplares: <?php echo htmlspecialchars($libro['titulo']); ?></h2>
<p><?php echo htmlspecialchars($libro['autor']); ?> &middot; <a href="libros.php">Volver a libros</a></p>

<table>
    <tr>
        <th>Código</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php while ($e = mysqli_fetch_assoc($ejemplares)) { ?>
    <tr>
        <td><?php echo htmlspecialchars($e['codigo']); ?></td>
        <td><?php echo ucfirst($e['estado']); ?></td>
        <td>
            <?php if ($e['estado'] == 'disponible') { ?>
            <form class="en-linea" method="post" action="ejemplares.php" onsubmit="return confirm('¿Dar de baja este ejemplar?');">
                <input type="hidden" name="accion" value="baja">
                <input type="hidden" name="libro_id" value="<?php echo $libro_id; ?>">
                <input type="hidden" name="ejemplar_id" value="<?php echo $e['id']; ?>">
                <button type="submit" class="peligro">Dar de baja</button>
            </form>
            <?php } else { echo '-'; } ?>
        </td>
    </tr>
    <?php } ?>
</table>

<h3>Agregar ejemplar</h3>
<form class="formulario" method="post" action="ejemplares.php">
    <input type="hidden" name="accion" value="alta">
    <input type="hidden" name="libro_id" value="<?php echo $libro_id; ?>">
    <p>
        <label for="codigo">Código del ejemplar</label>
        <input type="text" id="codigo" name="codigo">
    </p>
    <p><input type="submit" value="Agregar"></p>
</form>
<?php include 'includes/pie.php'; ?>
