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
    <?php foreach ($books as $book) { ?>
    <tr>
        <td><?= e($book['title']) ?></td>
        <td><?= e($book['author']) ?></td>
        <td><?= e($book['year']) ?></td>
        <td><?= e($book['isbn']) ?></td>
        <td class="num"><?= e($book['total']) ?></td>
        <td class="num"><?= e($book['available']) ?></td>
        <td>
            <a href="libro_editar.php?id=<?= e($book['id']) ?>">Editar</a> |
            <a href="ejemplares.php?libro_id=<?= e($book['id']) ?>">Ejemplares</a>
        </td>
    </tr>
    <?php } ?>
</table>
