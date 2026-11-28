<?php

use Library\Http\Format;

?>
<h2>Préstamos</h2>

<div class="barra">
    <a href="prestamos.php?filtro=activos"<?php if ($filter == 'activos') { echo ' class="activo"'; } ?>>Activos</a>
    <a href="prestamos.php?filtro=vencidos"<?php if ($filter == 'vencidos') { echo ' class="activo"'; } ?>>Vencidos</a>
    <a href="prestamos.php?filtro=devueltos"<?php if ($filter == 'devueltos') { echo ' class="activo"'; } ?>>Devueltos</a>
    <a href="prestar.php">Nuevo préstamo</a>
</div>

<?php if (count($loans) == 0) { ?>
    <p>No hay préstamos en esta lista.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Ejemplar</th>
        <th>Prestado</th>
        <th>Vence</th>
        <?php if ($filter == 'devueltos') { ?>
        <th>Devuelto</th>
        <th class="num">Multa</th>
        <?php } else { ?>
        <th>Acciones</th>
        <?php } ?>
    </tr>
    <?php foreach ($loans as $loan) { ?>
    <tr>
        <td><a href="socio_ver.php?id=<?= e($loan['member_id']) ?>"><?= e($loan['member']) ?></a></td>
        <td><?= e($loan['title']) ?></td>
        <td><?= e($loan['code']) ?></td>
        <td><?= e(Format::date($loan['loaned_on'])) ?></td>
        <td<?php if ($loan['returned_on'] === null && $loan['due_date'] < $today) { echo ' class="vencido"'; } ?>><?= e(Format::date($loan['due_date'])) ?></td>
        <?php if ($filter == 'devueltos') { ?>
        <td><?= e(Format::date($loan['returned_on'])) ?></td>
        <td class="num"><?= e($loan['fine'] > 0 ? Format::money($loan['fine']) : '-') ?></td>
        <?php } else { ?>
        <td>
            <form class="en-linea" method="post" action="devolver.php">
                <input type="hidden" name="prestamo_id" value="<?= e($loan['id']) ?>">
                <button type="submit">Devolver</button>
            </form>
        </td>
        <?php } ?>
    </tr>
    <?php } ?>
</table>
<?php } ?>
