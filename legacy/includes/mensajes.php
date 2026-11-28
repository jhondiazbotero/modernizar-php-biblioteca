<?php
// Textos que ve el usuario para cada código de error de los casos de uso.
// El dominio devuelve códigos en inglés; aquí se traducen a los mensajes de cada pantalla.
function mensaje_error($pantalla, $codigo)
{
    $mensajes = array(
        'prestar' => array(
            'member_not_found' => 'Elige un socio.',
            'copy_not_found' => 'Elige un ejemplar.',
            'member_inactive' => 'El socio está inactivo y no puede recibir préstamos.',
            'max_active_loans' => 'El socio ya tiene 3 préstamos activos.',
            'unpaid_fines' => 'El socio tiene multas sin pagar.',
            'copy_on_loan' => 'El ejemplar ya está prestado.',
            'copy_withdrawn' => 'El ejemplar está dado de baja.',
            'copy_reserved_for_other' => 'El ejemplar está reservado para otro socio.',
        ),
        'devolver' => array(
            'loan_not_found' => 'Elige el préstamo que se devuelve.',
            'already_returned' => 'Ese préstamo ya fue devuelto.',
        ),
        'reservas' => array(
            'member_not_found' => 'Elige un socio.',
            'book_not_found' => 'Elige un libro.',
            'copies_available' => 'El libro tiene ejemplares disponibles: no hace falta reservarlo.',
            'already_pending' => 'El socio ya tiene una reserva pendiente de ese libro.',
            'reservation_not_found' => 'La reserva no existe.',
            'not_cancellable' => 'Esa reserva ya no se puede cancelar.',
        ),
        'multas' => array(
            'loan_not_found' => 'El préstamo no existe.',
            'no_fine' => 'Ese préstamo no tiene multa.',
            'already_paid' => 'Esa multa ya estaba pagada.',
        ),
    );

    if (!isset($mensajes[$pantalla][$codigo])) {
        throw new RuntimeException('Código de error sin texto: ' . $pantalla . '/' . $codigo);
    }
    return $mensajes[$pantalla][$codigo];
}
