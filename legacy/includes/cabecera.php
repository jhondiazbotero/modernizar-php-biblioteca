<?php
// Cabecera común de las pantallas. Antes de incluirla hay que definir $titulo.
if (!isset($titulo)) {
    $titulo = 'Biblioteca';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($titulo); ?> - Biblioteca comunitaria</title>
    <link rel="stylesheet" type="text/css" href="css/estilo.css">
</head>
<body>
<div id="cabecera">
    <h1>Biblioteca comunitaria</h1>
<?php if (isset($_SESSION['bibliotecario_id'])) { ?>
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
        <li class="salir"><a href="logout.php">Salir (<?php echo htmlspecialchars($_SESSION['bibliotecario_nombre']); ?>)</a></li>
    </ul>
<?php } ?>
</div>
<div id="contenido">
<?php
if (isset($_GET['msg']) && $_GET['msg'] != '') {
    $clase_msg = (isset($_GET['tipo']) && $_GET['tipo'] == 'error') ? 'error' : 'ok';
    echo '<div class="mensaje ' . $clase_msg . '">' . htmlspecialchars($_GET['msg']) . '</div>';
}
?>
