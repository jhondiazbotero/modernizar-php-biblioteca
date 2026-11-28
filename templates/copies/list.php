<h2>Ejemplares: <?= e($book['title']) ?></h2>
<p><?= e($book['author']) ?> &middot; <a href="libros.php">Volver a libros</a></p>

<table>
    <tr>
        <th>Código</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php foreach ($copies as $copy) { ?>
    <tr>
        <td><?= e($copy['code']) ?></td>
        <td><?= e(ucfirst($copy['status'])) ?></td>
        <td>
            <?php if ($copy['status'] == 'disponible') { ?>
            <form class="en-linea" method="post" action="ejemplares.php" onsubmit="return confirm('¿Dar de baja este ejemplar?');">
                <input type="hidden" name="accion" value="baja">
                <input type="hidden" name="libro_id" value="<?= e($book['id']) ?>">
                <input type="hidden" name="ejemplar_id" value="<?= e($copy['id']) ?>">
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
    <input type="hidden" name="libro_id" value="<?= e($book['id']) ?>">
    <p>
        <label for="codigo">Código del ejemplar</label>
        <input type="text" id="codigo" name="codigo">
    </p>
    <p><input type="submit" value="Agregar"></p>
</form>
