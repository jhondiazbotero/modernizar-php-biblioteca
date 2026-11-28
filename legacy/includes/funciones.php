<?php
// Funciones comunes de la biblioteca

// Fecha de hoy (Y-m-d). Todas las pantallas deben usar esta función.
// El reloj (con la fecha fija de las pruebas) vive en Library\Infrastructure\Clock\RequestClock.
function hoy()
{
    return \Library\Infrastructure\Clock\RequestClock::fromGlobals()->today()->format('Y-m-d');
}

// Fecha y hora de ahora (Y-m-d H:i:s)
function ahora()
{
    return \Library\Infrastructure\Clock\RequestClock::fromGlobals()->now()->format('Y-m-d H:i:s');
}

function verificar_sesion()
{
    if (empty($_SESSION['bibliotecario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Pesos colombianos: $ 1.500
function formato_moneda($valor)
{
    return '$ ' . number_format($valor, 0, ',', '.');
}

// De 2026-03-28 a 28/03/2026
function formato_fecha($fecha)
{
    if ($fecha == '' || $fecha == null) {
        return '-';
    }
    return date('d/m/Y', strtotime($fecha));
}

// Vuelve a una pantalla con un mensaje en la URL
function redirigir($url, $msg, $tipo = 'ok')
{
    $separador = (strpos($url, '?') === false) ? '?' : '&';
    header('Location: ' . $url . $separador . 'msg=' . urlencode($msg) . '&tipo=' . $tipo);
    exit;
}

function estado_socio($activo)
{
    return $activo ? 'Activo' : 'Inactivo';
}
