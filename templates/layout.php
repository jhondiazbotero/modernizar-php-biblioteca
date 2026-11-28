<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?> - Biblioteca comunitaria</title>
    <link rel="stylesheet" type="text/css" href="css/estilo.css">
</head>
<body>
<div id="cabecera">
    <h1>Biblioteca comunitaria</h1>
<?php if ($signedIn) { ?>
    <ul id="menu">
        <li><a href="index.php">Inicio</a></li>
        <li><a href="socios.php">Socios</a></li>
        <li><a href="libros.php">Libros</a></li>
        <li><a href="prestar.php">Prestar</a></li>
        <li><a href="devolver.php">Devolver</a></li>
        <li><a href="prestamos.php">Préstamos</a></li>
        <li><a href="reservas.php">Reservas</a></li>
        <li><a href="multas.php">Multas</a></li>
        <li><a href="buscar.php">Buscar</a></li>
        <li class="salir"><a href="logout.php">Salir (<?= e($librarianName) ?>)</a></li>
    </ul>
<?php } ?>
</div>
<div id="contenido">
<?php if ($message !== '') { ?>
<div class="mensaje <?= e($messageClass) ?>"><?= e($message) ?></div>
<?php } ?>
<?= $content ?>
</div>
<div id="pie">
    Biblioteca comunitaria &middot; Sistema de préstamos
</div>
</body>
</html>
