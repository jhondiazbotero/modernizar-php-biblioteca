<?php
include 'includes/conexion.php';
include 'includes/funciones.php';

if (isset($_SESSION['bibliotecario_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = mysqli_real_escape_string($con, isset($_POST['usuario']) ? $_POST['usuario'] : '');
    $clave = md5(isset($_POST['clave']) ? $_POST['clave'] : '');

    $r = mysqli_query($con, "SELECT id, nombre FROM bibliotecarios WHERE usuario = '$usuario' AND clave = '$clave'");
    if ($r && mysqli_num_rows($r) == 1) {
        $b = mysqli_fetch_assoc($r);
        $_SESSION['bibliotecario_id'] = $b['id'];
        $_SESSION['bibliotecario_nombre'] = $b['nombre'];
        header('Location: index.php');
        exit;
    }
    redirigir('login.php', 'Usuario o clave incorrectos.', 'error');
}

$titulo = 'Ingresar';
include 'includes/cabecera.php';
?>
<h2>Ingreso de bibliotecarios</h2>

<form class="formulario" method="post" action="login.php">
    <p>
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario">
    </p>
    <p>
        <label for="clave">Clave</label>
        <input type="password" id="clave" name="clave">
    </p>
    <p><input type="submit" value="Ingresar"></p>
</form>
<?php include 'includes/pie.php'; ?>
