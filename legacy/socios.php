<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$socios = $db->query("SELECT * FROM socios ORDER BY id ASC")->fetchAll();

$titulo = 'Socios';
include 'includes/cabecera.php';
?>
<h2>Socios</h2>

<div class="barra">
    <a href="socio_editar.php">Nuevo socio</a>
</div>

<table>
    <tr>
        <th>Documento</th>
        <th>Nombre</th>
        <th>Correo</th>
        <th>Teléfono</th>
        <th>Alta</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php foreach ($socios as $s) { ?>
    <tr<?php if (!$s['activo']) { echo ' class="inactivo"'; } ?>>
        <td><?php echo htmlspecialchars($s['documento']); ?></td>
        <td><?php echo $s['nombre']; ?></td>
        <td><?php echo htmlspecialchars($s['correo']); ?></td>
        <td><?php echo htmlspecialchars($s['telefono']); ?></td>
        <td><?php echo formato_fecha($s['fecha_alta']); ?></td>
        <td><?php echo estado_socio($s['activo']); ?></td>
        <td>
            <a href="socio_ver.php?id=<?php echo $s['id']; ?>">Ver</a> |
            <a href="socio_editar.php?id=<?php echo $s['id']; ?>">Editar</a>
        </td>
    </tr>
    <?php } ?>
</table>
<?php include 'includes/pie.php'; ?>
