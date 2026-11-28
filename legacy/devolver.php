<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
include 'includes/servicios.php';
include 'includes/mensajes.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $prestamo_id = isset($_POST['prestamo_id']) ? (int)$_POST['prestamo_id'] : 0;

    $resultado = servicios()['devolver']->handle($prestamo_id);
    if (!$resultado->isSuccess()) {
        redirigir('devolver.php', mensaje_error('devolver', $resultado->getErrorCode()), 'error');
    }

    $msg = 'Devolución registrada: ' . $resultado->getBookTitle() . ' de ' . $resultado->getMemberName() . '.';
    if ($resultado->getFine() > 0) {
        $msg .= ' Multa por retraso: ' . formato_moneda($resultado->getFine()) . '.';
    }
    if ($resultado->getAssignedMemberName() !== null) {
        $msg .= ' Ejemplar apartado para ' . $resultado->getAssignedMemberName();
    }

    redirigir('prestamos.php', $msg, 'ok');
}

$activos = $db->query("SELECT p.id, p.fecha_vence, s.nombre AS socio, l.titulo, e.codigo
    FROM prestamos p
    JOIN socios s ON s.id = p.socio_id
    JOIN ejemplares e ON e.id = p.ejemplar_id
    JOIN libros l ON l.id = e.libro_id
    WHERE p.fecha_devolucion IS NULL
    ORDER BY l.titulo ASC, e.codigo ASC")->fetchAll();

$titulo = 'Devolver un libro';
include 'includes/cabecera.php';
?>
<h2>Devolver un libro</h2>

<form class="formulario" method="post" action="devolver.php">
    <p>
        <label for="prestamo_id">Préstamo</label>
        <select id="prestamo_id" name="prestamo_id">
            <option value="">Elige el préstamo</option>
            <?php foreach ($activos as $a) { ?>
            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['titulo']); ?> - <?php echo htmlspecialchars($a['codigo']); ?> - <?php echo htmlspecialchars($a['socio']); ?> (vence <?php echo formato_fecha($a['fecha_vence']); ?>)</option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">Si el libro se entrega tarde, la multa es de $ 500 por cada día de retraso.</p>
    <p><input type="submit" value="Registrar devolución"></p>
</form>
<?php include 'includes/pie.php'; ?>
