<h2>Prestar un libro</h2>

<form class="formulario" method="post" action="prestar.php">
    <p>
        <label for="socio_id">Socio</label>
        <select id="socio_id" name="socio_id">
            <option value="">Elige un socio</option>
            <?php foreach ($members as $member) { ?>
            <option value="<?= e($member['id']) ?>"><?= e($member['name']) ?> (<?= e($member['document']) ?>)<?php if (!$member['active']) { echo ' - inactivo'; } ?></option>
            <?php } ?>
        </select>
    </p>
    <p>
        <label for="ejemplar_id">Ejemplar</label>
        <select id="ejemplar_id" name="ejemplar_id">
            <option value="">Elige un ejemplar</option>
            <?php foreach ($copies as $copy) { ?>
            <option value="<?= e($copy['id']) ?>"><?= e($copy['title']) ?> - <?= e($copy['code']) ?> (<?= e($copy['status']) ?>)</option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">El préstamo dura 14 días. Cada socio puede tener hasta 3 préstamos activos.</p>
    <p><input type="submit" value="Registrar préstamo"></p>
</form>
