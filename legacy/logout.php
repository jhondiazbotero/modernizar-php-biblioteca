<?php
include 'includes/conexion.php';
include 'includes/funciones.php';

session_destroy();
header('Location: login.php?msg=' . urlencode('Sesión cerrada. ¡Hasta pronto!') . '&tipo=ok');
exit;
