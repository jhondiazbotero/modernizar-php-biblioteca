<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $documento = mysqli_real_escape_string($con, trim(isset($_POST['documento']) ? $_POST['documento'] : ''));
    $nombre = mysqli_real_escape_string($con, trim(isset($_POST['nombre']) ? $_POST['nombre'] : ''));
    $correo = mysqli_real_escape_string($con, trim(isset($_POST['correo']) ? $_POST['correo'] : ''));
    $telefono = mysqli_real_escape_string($con, trim(isset($_POST['telefono']) ? $_POST['telefono'] : ''));
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($documento == '' || $nombre == '') {
        redirigir('socio_editar.php' . ($id ? '?id=' . $id : ''), 'El documento y el nombre son obligatorios.', 'error');
    }

    // el documento no se puede repetir
    $r = mysqli_query($con, "SELECT id FROM socios WHERE documento = '$documento' AND id <> $id");
    if (mysqli_num_rows($r) > 0) {
        redirigir('socio_editar.php' . ($id ? '?id=' . $id : ''), 'Ya existe un socio con ese documento.', 'error');
    }

    if ($id) {
        mysqli_query($con, "UPDATE socios SET documento = '$documento', nombre = '$nombre', correo = '$correo', telefono = '$telefono', activo = $activo WHERE id = $id");
        redirigir('socios.php', 'Socio actualizado.');
    } else {
        $hoy = hoy();
        mysqli_query($con, "INSERT INTO socios (documento, nombre, correo, telefono, fecha_alta, activo) VALUES ('$documento', '$nombre', '$correo', '$telefono', '$hoy', $activo)");
        redirigir('socios.php', 'Socio registrado.');
    }
}

$socio = array('id' => 0, 'documento' => '', 'nombre' => '', 'correo' => '', 'telefono' => '', 'activo' => 1);
if ($id) {
    $r = mysqli_query($con, "SELECT * FROM socios WHERE id = $id");
    if (mysqli_num_rows($r) == 0) {
        redirigir('socios.php', 'El socio no existe.', 'error');
    }
    $socio = mysqli_fetch_assoc($r);
}

$titulo = $id ? 'Editar socio' : 'Nuevo socio';
include 'includes/cabecera.php';
?>
<h2><?php echo $id ? 'Editar socio' : 'Nuevo socio'; ?></h2>

<form class="formulario" method="post" action="socio_editar.php">
    <input type="hidden" name="id" value="<?php echo $socio['id']; ?>">
    <p>
        <label for="documento">Documento</label>
        <input type="text" id="documento" name="documento" value="<?php echo htmlspecialchars($socio['documento']); ?>">
    </p>
    <p>
        <label for="nombre">Nombre completo</label>
        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($socio['nombre']); ?>">
    </p>
    <p>
        <label for="correo">Correo electrónico</label>
        <input type="text" id="correo" name="correo" value="<?php echo htmlspecialchars($socio['correo']); ?>">
    </p>
    <p>
        <label for="telefono">Teléfono</label>
        <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($socio['telefono']); ?>">
    </p>
    <p>
        <label><input type="checkbox" name="activo" value="1"<?php if ($socio['activo']) { echo ' checked'; } ?>> Socio activo</label>
    </p>
    <p>
        <input type="submit" value="Guardar">
        <a href="socios.php">Cancelar</a>
    </p>
</form>
<?php include 'includes/pie.php'; ?>
