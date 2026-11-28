<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
include 'includes/servicios.php';
include 'includes/mensajes.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $socio_id = isset($_POST['socio_id']) ? (int)$_POST['socio_id'] : 0;
    $ejemplar_id = isset($_POST['ejemplar_id']) ? (int)$_POST['ejemplar_id'] : 0;

    $resultado = servicios()['prestar']->handle($socio_id, $ejemplar_id);
    if (!$resultado->isSuccess()) {
        redirigir('prestar.php', mensaje_error('prestar', $resultado->getErrorCode()), 'error');
    }

    redirigir('prestamos.php', 'Préstamo registrado: ' . $resultado->getBookTitle() . ' para ' . $resultado->getMemberName() . '. Vence el ' . formato_fecha($resultado->getDueDate()->format('Y-m-d')) . '.');
}

$socios = $db->query("SELECT id, nombre, documento, activo FROM socios ORDER BY nombre ASC")->fetchAll();
$ejemplares = $db->query("SELECT e.id, e.codigo, e.estado, l.titulo
    FROM ejemplares e
    JOIN libros l ON l.id = e.libro_id
    WHERE e.estado <> 'baja'
    ORDER BY l.titulo ASC, e.codigo ASC")->fetchAll();

$titulo = 'Prestar un libro';
include 'includes/cabecera.php';
?>
<h2>Prestar un libro</h2>

<form class="formulario" method="post" action="prestar.php">
    <p>
        <label for="socio_id">Socio</label>
        <select id="socio_id" name="socio_id">
            <option value="">Elige un socio</option>
            <?php foreach ($socios as $s) { ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nombre']); ?> (<?php echo htmlspecialchars($s['documento']); ?>)<?php if (!$s['activo']) { echo ' - inactivo'; } ?></option>
            <?php } ?>
        </select>
    </p>
    <p>
        <label for="ejemplar_id">Ejemplar</label>
        <select id="ejemplar_id" name="ejemplar_id">
            <option value="">Elige un ejemplar</option>
            <?php foreach ($ejemplares as $e) { ?>
            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['titulo']); ?> - <?php echo htmlspecialchars($e['codigo']); ?> (<?php echo $e['estado']; ?>)</option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">El préstamo dura 14 días. Cada socio puede tener hasta 3 préstamos activos.</p>
    <p><input type="submit" value="Registrar préstamo"></p>
</form>
<?php include 'includes/pie.php'; ?>
