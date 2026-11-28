<?php

use Library\Http\Format;

?>
<h2>Tablero</h2>

<div class="tarjetas">
    <div class="tarjeta">
        <div class="cifra"><?= e($activeLoans) ?></div>
        Préstamos activos
    </div>
    <div class="tarjeta alerta">
        <div class="cifra"><?= e($overdueCount) ?></div>
        Préstamos vencidos
    </div>
    <div class="tarjeta">
        <div class="cifra"><?= e($pendingCount) ?></div>
        Reservas pendientes
    </div>
</div>

<h3>Préstamos vencidos</h3>
<?php if (count($overdue) == 0) { ?>
    <p>No hay préstamos vencidos.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Ejemplar</th>
        <th>Vencía</th>
    </tr>
    <?php foreach ($overdue as $loan) { ?>
    <tr>
        <td><a href="socio_ver.php?id=<?= e($loan['member_id']) ?>"><?= e($loan['member']) ?></a></td>
        <td><?= e($loan['title']) ?></td>
        <td><?= e($loan['code']) ?></td>
        <td class="vencido"><?= e(Format::date($loan['due_date'])) ?></td>
    </tr>
    <?php } ?>
</table>
<?php } ?>

<h3>Reservas pendientes</h3>
<?php if (count($pending) == 0) { ?>
    <p>No hay reservas pendientes.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Reservado el</th>
    </tr>
    <?php foreach ($pending as $reservation) { ?>
    <tr>
        <td><?= e($reservation['member']) ?></td>
        <td><a href="reservas.php?libro_id=<?= e($reservation['book_id']) ?>"><?= e($reservation['title']) ?></a></td>
        <td><?= e(Format::dateTime($reservation['reserved_at'])) ?></td>
    </tr>
    <?php } ?>
</table>
<?php } ?>
