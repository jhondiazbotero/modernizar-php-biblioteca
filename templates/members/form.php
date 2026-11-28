<h2><?= e($heading) ?></h2>

<form class="formulario" method="post" action="socio_editar.php">
    <input type="hidden" name="id" value="<?= e($member['id']) ?>">
    <p>
        <label for="documento">Documento</label>
        <input type="text" id="documento" name="documento" value="<?= e($member['document']) ?>">
    </p>
    <p>
        <label for="nombre">Nombre completo</label>
        <input type="text" id="nombre" name="nombre" value="<?= e($member['name']) ?>">
    </p>
    <p>
        <label for="correo">Correo electrónico</label>
        <input type="text" id="correo" name="correo" value="<?= e($member['email']) ?>">
    </p>
    <p>
        <label for="telefono">Teléfono</label>
        <input type="text" id="telefono" name="telefono" value="<?= e($member['phone']) ?>">
    </p>
    <p>
        <label><input type="checkbox" name="activo" value="1"<?php if ($member['active']) { echo ' checked'; } ?>> Socio activo</label>
    </p>
    <p>
        <input type="submit" value="Guardar">
        <a href="socios.php">Cancelar</a>
    </p>
</form>
