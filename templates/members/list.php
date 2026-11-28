<?php

use Library\Http\Format;

?>
<h2>Socios</h2>

<div class="barra">
    <a href="socio_editar.php">Nuevo socio</a>
</div>

<table>
    <tr>
        <th>Documento</th>
        <th>Nombre</th>
        <th>Correo</th>
        <th>Teléfono</th>
        <th>Alta</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    <?php foreach ($members as $member) { ?>
    <tr<?php if (!$member['active']) { echo ' class="inactivo"'; } ?>>
        <td><?= e($member['document']) ?></td>
        <td><?= e($member['name']) ?></td>
        <td><?= e($member['email']) ?></td>
        <td><?= e($member['phone']) ?></td>
        <td><?= e(Format::date($member['registered_on'])) ?></td>
        <td><?= e(Format::memberStatus($member['active'])) ?></td>
        <td>
            <a href="socio_ver.php?id=<?= e($member['id']) ?>">Ver</a> |
            <a href="socio_editar.php?id=<?= e($member['id']) ?>">Editar</a>
        </td>
    </tr>
    <?php } ?>
</table>
