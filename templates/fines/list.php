<?php

use Library\Http\Format;

?>
<h2>Multas</h2>

<?php if (count($fines) == 0) { ?>
    <p>No hay multas registradas.</p>
<?php } else { ?>
<table>
    <tr>
        <th>Socio</th>
        <th>Libro</th>
        <th>Vencía</th>
        <th>Devuelto</th>
        <th class="num">Multa</th>
        <th>Estado</th>
    </tr>
    <?php foreach ($fines as $fine) { ?>
    <tr>
        <td><a href="socio_ver.php?id=<?= e($fine['member_id']) ?>"><?= e($fine['member']) ?></a></td>
        <td><?= e($fine['title']) ?></td>
        <td><?= e(Format::date($fine['due_date'])) ?></td>
        <td><?= e(Format::date($fine['returned_on'])) ?></td>
        <td class="num"><?= e(Format::money($fine['fine'])) ?></td>
        <td>
            <?php if ($fine['paid']) { ?>
                Pagada
            <?php } else { ?>
            <form class="en-linea" method="post" action="multas.php">
                <input type="hidden" name="prestamo_id" value="<?= e($fine['id']) ?>">
                <button type="submit">Pagar multa</button>
            </form>
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
</table>
<?php } ?>
