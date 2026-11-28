<?php
// Conexión a la base y configuración general
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota');

if (session_id() == '') {
    session_start();
}

$config_bd = \Library\Config\DatabaseConfig::fromEnvironment();
$db = \Library\Database\ConnectionFactory::create($config_bd);
unset($config_bd);
