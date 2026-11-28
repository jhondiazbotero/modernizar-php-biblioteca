<h2><?= e($heading) ?></h2>

<form class="formulario" method="post" action="libro_editar.php">
    <input type="hidden" name="id" value="<?= e($book['id']) ?>">
    <p>
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" value="<?= e($book['title']) ?>">
    </p>
    <p>
        <label for="autor">Autor</label>
        <input type="text" id="autor" name="autor" value="<?= e($book['author']) ?>">
    </p>
    <p>
        <label for="anio">Año de publicación</label>
        <input type="number" id="anio" name="anio" value="<?= e($book['year']) ?>">
    </p>
    <p>
        <label for="isbn">ISBN</label>
        <input type="text" id="isbn" name="isbn" value="<?= e($book['isbn']) ?>">
    </p>
    <p>
        <input type="submit" value="Guardar">
        <a href="libros.php">Cancelar</a>
    </p>
</form>
