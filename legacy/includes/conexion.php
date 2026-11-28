<?php
// Conexión a la base y configuración general
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota');

if (session_id() == '') {
    session_start();
}

$config_bd = \Library\Config\DatabaseConfig::fromEnvironment();
$db = \Library\Database\ConnectionFactory::create($config_bd);
$con = mysqli_connect($config_bd->getHost(), $config_bd->getUser(), $config_bd->getPassword(), $config_bd->getName());
if (!$con) {
    die('No se pudo conectar con la base de datos: ' . mysqli_connect_error());
}
mysqli_set_charset($con, 'utf8mb4');
unset($config_bd);
