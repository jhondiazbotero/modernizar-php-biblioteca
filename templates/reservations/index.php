<?php

use Library\Http\Format;

?>
<h2>Reservas</h2>

<?php if ($queueTitle !== null) { ?>
<h3>Cola de «<?= e($queueTitle) ?>»</h3>
<?php if (count($queue) == 0) { ?>
    <p>Este libro no tiene reservas en cola.</p>
<?php } else { ?>
<table>
    <tr>
        <th class="num">Lugar</th>
        <th>Socio</th>
        <th>Reservado el</th>
        <th>Estado</th>
    </tr>
    <?php $place = 1; foreach ($queue as $entry) { ?>
    <tr>
        <td class="num"><?= e($place++) ?></td>
        <td><?= e($entry['member']) ?></td>
        <td><?= e(Format::dateTime($entry['reserved_at'])) ?></td>
        <td><?= e(ucfirst($entry['status'])) ?></td>
    </tr>
    <?php } ?>
</table>
<?php } ?>
<?php } ?>

<h3>Reservas vigentes</h3>
<?php if (count($reservations) == 0) { ?>
    <p>No hay reservas vigentes.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Libro</th>
        <th>Socio</th>
        <th>Reservado el</th>
        <th>Estado</th>
        <th>Ejemplar</th>
        <th>Acciones</th>
    </tr>
    <?php foreach ($reservations as $reservation) { ?>
    <tr>
        <td><a href="reservas.php?libro_id=<?= e($reservation['book_id']) ?>"><?= e($reservation['title']) ?></a></td>
        <td><?= e($reservation['member']) ?></td>
        <td><?= e(Format::dateTime($reservation['reserved_at'])) ?></td>
        <td><?= e(ucfirst($reservation['status'])) ?></td>
        <td><?= e($reservation['code'] ? $reservation['code'] : '-') ?></td>
        <td>
            <form class="en-linea" method="post" action="reservas.php" onsubmit="return confirm('¿Cancelar esta reserva?');">
                <input type="hidden" name="accion" value="cancelar">
                <input type="hidden" name="reserva_id" value="<?= e($reservation['id']) ?>">
                <button type="submit" class="peligro">Cancelar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
</table>
<?php } ?>

<h3>Nueva reserva</h3>
<form class="formulario" method="post" action="reservas.php">
    <input type="hidden" name="accion" value="crear">
    <p>
        <label for="socio_id">Socio</label>
        <select id="socio_id" name="socio_id">
            <option value="">Elige un socio</option>
            <?php foreach ($members as $member) { ?>
            <option value="<?= e($member['id']) ?>"><?= e($member['name']) ?></option>
            <?php } ?>
        </select>
    </p>
    <p>
        <label for="libro_id">Libro</label>
        <select id="libro_id" name="libro_id">
            <option value="">Elige un libro</option>
            <?php foreach ($books as $book) { ?>
            <option value="<?= e($book['id']) ?>"><?= e($book['title']) ?> - <?= e($book['author']) ?></option>
            <?php } ?>
        </select>
    </p>
    <p class="pequeno">Solo se puede reservar un libro cuando no tiene ejemplares disponibles.</p>
    <p><input type="submit" value="Reservar"></p>
</form>
