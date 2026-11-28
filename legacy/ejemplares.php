<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$libro_id = isset($_GET['libro_id']) ? (int)$_GET['libro_id'] : 0;
if (isset($_POST['libro_id'])) {
    $libro_id = (int)$_POST['libro_id'];
}

$consulta = $db->prepare('SELECT * FROM libros WHERE id = ?');
$consulta->execute(array($libro_id));
$libro = $consulta->fetch();
if (!$libro) {
    redirigir('libros.php', 'El libro no existe.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($accion == 'alta') {
        $codigo = trim(isset($_POST['codigo']) ? $_POST['codigo'] : '');
        if ($codigo == '') {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'Escribe el código del ejemplar.', 'error');
        }
        $consulta = $db->prepare('SELECT id FROM ejemplares WHERE codigo = ?');
        $consulta->execute(array($codigo));
        if ($consulta->fetch()) {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'Ya existe un ejemplar con ese código.', 'error');
        }
        $consulta = $db->prepare("INSERT INTO ejemplares (libro_id, codigo, estado) VALUES (?, ?, 'disponible')");
        $consulta->execute(array($libro_id, $codigo));
        redirigir('ejemplares.php?libro_id=' . $libro_id, 'Ejemplar agregado.');
    }

    if ($accion == 'baja') {
        $ejemplar_id = isset($_POST['ejemplar_id']) ? (int)$_POST['ejemplar_id'] : 0;
        $consulta = $db->prepare('SELECT estado FROM ejemplares WHERE id = ? AND libro_id = ?');
        $consulta->execute(array($ejemplar_id, $libro_id));
        $e = $consulta->fetch();
        if (!$e) {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'El ejemplar no existe.', 'error');
        }
        if ($e['estado'] == 'prestado' || $e['estado'] == 'reservado') {
            redirigir('ejemplares.php?libro_id=' . $libro_id, 'No se puede dar de baja un ejemplar prestado o reservado.', 'error');
        }
        $consulta = $db->prepare("UPDATE ejemplares SET estado = 'baja' WHERE id = ?");
        $consulta->execute(array($ejemplar_id));
        redirigir('ejemplares.php?libro_id=' . $libro_id, 'Ejemplar dado de baja.');
    }
}

$consulta = $db->prepare('SELECT * FROM ejemplares WHERE libro_id = ? ORDER BY codigo ASC');
$consulta->execute(array($libro_id));
$ejemplares = $consulta->fetchAll();

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
    <?php foreach ($ejemplares as $e) { ?>
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
