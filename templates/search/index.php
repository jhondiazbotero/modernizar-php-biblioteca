<h2>Buscar libros</h2>

<form class="formulario" method="get" action="buscar.php">
    <p>
        <label for="q">Título o autor</label>
        <input type="text" id="q" name="q" value="<?= e($text) ?>">
    </p>
    <p><input type="submit" value="Buscar"></p>
</form>

<?php if ($books !== null) { ?>
    <?php if (count($books) == 0) { ?>
    <p>No se encontraron libros para «<?= e($text) ?>».</p>
    <?php } else { ?>
    <p><?= e(count($books)) ?> resultado(s).</p>
    <table>
        <tr>
            <th>Título</th>
            <th>Autor</th>
            <th>Año</th>
            <th class="num">Disponibles</th>
            <th>Acciones</th>
        </tr>
        <?php foreach ($books as $book) { ?>
        <tr>
            <td><?= e($book['title']) ?></td>
            <td><?= e($book['author']) ?></td>
            <td><?= e($book['year']) ?></td>
            <td class="num"><?= e($book['available']) ?></td>
            <td><a href="ejemplares.php?libro_id=<?= e($book['id']) ?>">Ejemplares</a></td>
        </tr>
        <?php } ?>
    </table>
    <?php } ?>
<?php } ?>
