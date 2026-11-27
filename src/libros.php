<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$libros = mysqli_query($con, "SELECT l.*,
        COUNT(CASE WHEN e.estado <> 'baja' THEN 1 END) AS total,
        COUNT(CASE WHEN e.estado = 'disponible' THEN 1 END) AS disponibles
    FROM libros l
    LEFT JOIN ejemplares e ON e.libro_id = l.id
    GROUP BY l.id
    ORDER BY l.titulo ASC");

$titulo = 'Libros';
include 'includes/cabecera.php';
?>
<h2>Libros</h2>

<div class="barra">
    <a href="libro_editar.php">Nuevo libro</a>
</div>

<table>
    <tr>
        <th>Título</th>
        <th>Autor</th>
        <th>Año</th>
        <th>ISBN</th>
        <th class="num">Ejemplares</th>
        <th class="num">Disponibles</th>
        <th>Acciones</th>
    </tr>
    <?php while ($l = mysqli_fetch_assoc($libros)) { ?>
    <tr>
        <td><?php echo htmlspecialchars($l['titulo']); ?></td>
        <td><?php echo htmlspecialchars($l['autor']); ?></td>
        <td><?php echo $l['anio']; ?></td>
        <td><?php echo htmlspecialchars($l['isbn']); ?></td>
        <td class="num"><?php echo $l['total']; ?></td>
        <td class="num"><?php echo $l['disponibles']; ?></td>
        <td>
            <a href="libro_editar.php?id=<?php echo $l['id']; ?>">Editar</a> |
            <a href="ejemplares.php?libro_id=<?php echo $l['id']; ?>">Ejemplares</a>
        </td>
    </tr>
    <?php } ?>
</table>
<?php include 'includes/pie.php'; ?>
