<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $prestamo_id = isset($_POST['prestamo_id']) ? (int)$_POST['prestamo_id'] : 0;

    $r = mysqli_query($con, "SELECT p.*, e.libro_id, l.titulo, s.nombre AS socio
        FROM prestamos p
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        JOIN socios s ON s.id = p.socio_id
        WHERE p.id = $prestamo_id");
    if (mysqli_num_rows($r) == 0) {
        redirigir('devolver.php', 'Elige el préstamo que se devuelve.', 'error');
    }
    $p = mysqli_fetch_assoc($r);
    if ($p['fecha_devolucion'] != null) {
        redirigir('devolver.php', 'Ese préstamo ya fue devuelto.', 'error');
    }

    $hoy = hoy();

    // multa: $500 por cada día de retraso
    $dias = (int)date('j', strtotime(hoy())) - (int)date('j', strtotime($p['fecha_vence']));
    if ($dias < 0) {
        $dias = 0;
    }
    $multa = $dias * 500;

    mysqli_query($con, "UPDATE prestamos SET fecha_devolucion = '$hoy', multa = $multa WHERE id = $prestamo_id");

    $ejemplar_id = (int)$p['ejemplar_id'];
    $libro_id = (int)$p['libro_id'];
    $msg = 'Devolución registrada: ' . $p['titulo'] . ' de ' . $p['socio'] . '.';
    if ($multa > 0) {
        $msg .= ' Multa por retraso: ' . formato_moneda($multa) . '.';
    }

    // si hay reservas pendientes, el ejemplar se aparta para la siguiente
    $r = mysqli_query($con, "SELECT r.id, s.nombre
        FROM reservas r
        JOIN socios s ON s.id = r.socio_id
        WHERE r.libro_id = $libro_id AND r.estado = 'pendiente'
        ORDER BY s.nombre ASC
        LIMIT 1");
    if (mysqli_num_rows($r) > 0) {
        $sig = mysqli_fetch_assoc($r);
        mysqli_query($con, "UPDATE reservas SET estado = 'asignada', ejemplar_id = $ejemplar_id WHERE id = " . (int)$sig['id']);
        mysqli_query($con, "UPDATE ejemplares SET estado = 'reservado' WHERE id = $ejemplar_id");
        $msg .= ' Ejemplar apartado para ' . $sig['nombre'];
    } else {
        mysqli_query($con, "UPDATE ejemplares SET estado = 'disponible' WHERE id = $ejemplar_id");
    }

    redirigir('prestamos.php', $msg, 'ok');
}

$activos = mysqli_query($con, "SELECT p.id, p.fecha_vence, s.nombre AS socio, l.titulo, e.codigo
    FROM prestamos p
    JOIN socios s ON s.id = p.socio_id
    JOIN ejemplares e ON e.id = p.ejemplar_id
    JOIN libros l ON l.id = e.libro_id
    WHERE p.fecha_devolucion IS NULL
    ORDER BY l.titulo ASC, e.codigo ASC");

$titulo = 'Devolver un libro';
include 'includes/cabecera.php';
?>
<h2>Devolver un libro</h2>

<form class="formulario" method="post" action="devolver.php">
    <p>
        <label for="prestamo_id">Préstamo</label>
        <select id="prestamo_id" name="prestamo_id">
            <option value="">Elige el préstamo</option>
            <?php while ($a = mysqli_fetch_assoc($activos)) { ?>
            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['titulo']); ?> - <?php echo htmlspecialchars($a['codigo']); ?> - <?php echo htmlspecialchars($a['socio']); ?> (vence <?php echo formato_fecha($a['fecha_vence']); ?>)</option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">Si el libro se entrega tarde, la multa es de $ 500 por cada día de retraso.</p>
    <p><input type="submit" value="Registrar devolución"></p>
</form>
<?php include 'includes/pie.php'; ?>
