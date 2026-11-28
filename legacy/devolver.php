<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $prestamo_id = isset($_POST['prestamo_id']) ? (int)$_POST['prestamo_id'] : 0;

    $consulta = $db->prepare('SELECT p.*, e.libro_id, l.titulo, s.nombre AS socio
        FROM prestamos p
        JOIN ejemplares e ON e.id = p.ejemplar_id
        JOIN libros l ON l.id = e.libro_id
        JOIN socios s ON s.id = p.socio_id
        WHERE p.id = ?');
    $consulta->execute(array($prestamo_id));
    $p = $consulta->fetch();
    if (!$p) {
        redirigir('devolver.php', 'Elige el préstamo que se devuelve.', 'error');
    }
    if ($p['fecha_devolucion'] != null) {
        redirigir('devolver.php', 'Ese préstamo ya fue devuelto.', 'error');
    }

    $hoy = hoy();

    // multa: $500 por cada día de retraso
    $multa = 0;
    if (strtotime($hoy) > strtotime($p['fecha_vence'])) {
        $dias = (int)date('j', strtotime($hoy)) - (int)date('j', strtotime($p['fecha_vence']));
        if ($dias < 0) {
            $dias = 0;
        }
        $multa = $dias * 500;
    }

    $consulta = $db->prepare('UPDATE prestamos SET fecha_devolucion = ?, multa = ? WHERE id = ?');
    $consulta->execute(array($hoy, $multa, $prestamo_id));

    $ejemplar_id = (int)$p['ejemplar_id'];
    $libro_id = (int)$p['libro_id'];
    $msg = 'Devolución registrada: ' . $p['titulo'] . ' de ' . $p['socio'] . '.';
    if ($multa > 0) {
        $msg .= ' Multa por retraso: ' . formato_moneda($multa) . '.';
    }

    // si hay reservas pendientes, el ejemplar se aparta para la siguiente
    $consulta = $db->prepare("SELECT r.id, s.nombre
        FROM reservas r
        JOIN socios s ON s.id = r.socio_id
        WHERE r.libro_id = ? AND r.estado = 'pendiente'
        ORDER BY s.nombre ASC
        LIMIT 1");
    $consulta->execute(array($libro_id));
    $sig = $consulta->fetch();
    if ($sig) {
        $consulta = $db->prepare("UPDATE reservas SET estado = 'asignada', ejemplar_id = ? WHERE id = ?");
        $consulta->execute(array($ejemplar_id, (int)$sig['id']));
        $consulta = $db->prepare("UPDATE ejemplares SET estado = 'reservado' WHERE id = ?");
        $consulta->execute(array($ejemplar_id));
        $msg .= ' Ejemplar apartado para ' . $sig['nombre'];
    } else {
        $consulta = $db->prepare("UPDATE ejemplares SET estado = 'disponible' WHERE id = ?");
        $consulta->execute(array($ejemplar_id));
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
