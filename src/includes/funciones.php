<?php
// Funciones comunes de la biblioteca

// Fecha y hora fijas para las pruebas: solo con APP_ENV=test y la cabecera
// X-Fecha-Fija ('Y-m-d' o 'Y-m-d H:i:s'). Si no, devuelve null.
function fecha_fija()
{
    if (getenv('APP_ENV') !== 'test' || empty($_SERVER['HTTP_X_FECHA_FIJA'])) {
        return null;
    }
    $valor = $_SERVER['HTTP_X_FECHA_FIJA'];
    foreach (array('Y-m-d H:i:s', 'Y-m-d') as $formato) {
        $f = DateTime::createFromFormat('!' . $formato, $valor);
        if ($f && $f->format($formato) === $valor) {
            return array(substr($valor, 0, 10), strlen($valor) > 10 ? substr($valor, 11) : '12:00:00');
        }
    }
    return null;
}

// Fecha de hoy. Todas las pantallas deben usar esta función.
function hoy()
{
    $fija = fecha_fija();
    return $fija ? $fija[0] : date('Y-m-d');
}

// Fecha y hora de ahora (Y-m-d H:i:s)
function ahora()
{
    $fija = fecha_fija();
    return $fija ? $fija[0] . ' ' . $fija[1] : date('Y-m-d H:i:s');
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

function sumar_dias($fecha, $dias)
{
    return date('Y-m-d', strtotime($fecha . ' +' . $dias . ' days'));
}

// Vuelve a una pantalla con un mensaje en la URL
function redirigir($url, $msg, $tipo = 'ok')
{
    $separador = (strpos($url, '?') === false) ? '?' : '&';
    header('Location: ' . $url . $separador . 'msg=' . urlencode($msg) . '&tipo=' . $tipo);
    exit;
}

function contar_prestamos_activos($socio_id)
{
    global $con;
    $r = mysqli_query($con, "SELECT COUNT(*) AS n FROM prestamos WHERE socio_id = " . (int)$socio_id . " AND fecha_devolucion IS NULL");
    $f = mysqli_fetch_assoc($r);
    return (int)$f['n'];
}

function contar_multas_sin_pagar($socio_id)
{
    global $con;
    $r = mysqli_query($con, "SELECT COUNT(*) AS n FROM prestamos WHERE socio_id = " . (int)$socio_id . " AND multa > 0 AND multa_pagada = 0");
    $f = mysqli_fetch_assoc($r);
    return (int)$f['n'];
}

function estado_socio($activo)
{
    return $activo ? 'Activo' : 'Inactivo';
}
