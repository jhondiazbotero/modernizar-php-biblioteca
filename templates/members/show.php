<?php

use Library\Http\Format;

?>
<h2><?= e($member['name']) ?></h2>

<table>
    <tr>
        <th>Documento</th>
        <td><?= e($member['document']) ?></td>
        <th>Correo</th>
        <td><?= e($member['email']) ?></td>
    </tr>
    <tr>
        <th>Teléfono</th>
        <td><?= e($member['phone']) ?></td>
        <th>Estado</th>
        <td><?= e(Format::memberStatus($member['active'])) ?> (socio desde el <?= e(Format::date($member['registered_on'])) ?>)</td>
    </tr>
</table>

<div class="barra">
    <a href="socio_editar.php?id=<?= e($member['id']) ?>">Editar socio</a>
</div>

<h3>Préstamos</h3>
<?php if (count($loans) == 0) { ?>
    <p>Este socio todavía no tiene préstamos.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Libro</th>
        <th>Ejemplar</th>
        <th>Prestado</th>
        <th>Vence</th>
        <th>Devuelto</th>
        <th class="num">Multa</th>
    </tr>
    <?php foreach ($loans as $loan) { ?>
    <tr>
        <td><?= e($loan['title']) ?></td>
        <td><?= e($loan['code']) ?></td>
        <td><?= e(Format::date($loan['loaned_on'])) ?></td>
        <td<?php if ($loan['returned_on'] === null && $loan['due_date'] < $today) { echo ' class="vencido"'; } ?>><?= e(Format::date($loan['due_date'])) ?></td>
        <td><?= e($loan['returned_on'] ? Format::date($loan['returned_on']) : 'Sin devolver') ?></td>
        <td class="num">
            <?= e($loan['fine'] > 0 ? Format::money($loan['fine']) . ($loan['fine_paid'] ? ' (pagada)' : ' (pendiente)') : '-') ?>
        </td>
    </tr>
    <?php } ?>
</table>
<?php } ?>

<h3>Multas</h3>
<?php if ($unpaidFines > 0) { ?>
    <p>Multas pendientes de pago: <strong><?= e(Format::money($unpaidFines)) ?></strong>. <a href="multas.php">Ir a multas</a></p>
<?php } else { ?>
    <p>El socio no tiene multas pendientes.</p>
<?php } ?>
