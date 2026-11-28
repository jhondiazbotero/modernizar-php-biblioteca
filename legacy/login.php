<?php
include 'includes/conexion.php';
include 'includes/funciones.php';

if (isset($_SESSION['bibliotecario_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = (isset($_POST['usuario']) && is_string($_POST['usuario'])) ? $_POST['usuario'] : '';
    $clave = (isset($_POST['clave']) && is_string($_POST['clave'])) ? $_POST['clave'] : '';
    $verificador = new \Library\Auth\PasswordVerifier();

    $consulta = $db->prepare('SELECT id, nombre, clave FROM bibliotecarios WHERE usuario = ?');
    $consulta->execute(array($usuario));
    $b = $consulta->fetch();

    if ($b) {
        $correcta = $verificador->verify($clave, $b['clave']);
    } else {
        // Usuario inexistente: se hace el mismo trabajo para que el tiempo no delate quién existe.
        password_verify($clave, \Library\Auth\PasswordVerifier::DUMMY_HASH);
        $correcta = false;
    }

    if ($correcta) {
        if ($verificador->needsRehash($b['clave'])) {
            $actualizar = $db->prepare('UPDATE bibliotecarios SET clave = ? WHERE id = ?');
            $actualizar->execute(array($verificador->hash($clave), $b['id']));
        }
        session_regenerate_id(true);
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
