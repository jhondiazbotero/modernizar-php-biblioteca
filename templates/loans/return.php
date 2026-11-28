<?php

use Library\Http\Format;

?>
<h2>Devolver un libro</h2>

<form class="formulario" method="post" action="devolver.php">
    <p>
        <label for="prestamo_id">Préstamo</label>
        <select id="prestamo_id" name="prestamo_id">
            <option value="">Elige el préstamo</option>
            <?php foreach ($loans as $loan) { ?>
            <option value="<?= e($loan['id']) ?>"><?= e($loan['title']) ?> - <?= e($loan['code']) ?> - <?= e($loan['member']) ?> (vence <?= e(Format::date($loan['due_date'])) ?>)</option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">Si el libro se entrega tarde, la multa es de $ 500 por cada día de retraso.</p>
    <p><input type="submit" value="Registrar devolución"></p>
</form>
