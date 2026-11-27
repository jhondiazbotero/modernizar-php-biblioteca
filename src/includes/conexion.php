<?php
// Conexión a la base y configuración general
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota');

if (session_id() == '') {
    session_start();
}

$con = mysqli_connect(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_NAME'));
if (!$con) {
    die('No se pudo conectar con la base de datos: ' . mysqli_connect_error());
}
mysqli_set_charset($con, 'utf8mb4');
