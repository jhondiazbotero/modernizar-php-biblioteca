<?php
// Arma los casos de uso de la aplicación con la conexión $db y el reloj de la petición.
// Las pantallas llaman a servicios() y no conocen nada de la persistencia.
use Library\Application\CancelReservation;
use Library\Application\LendCopy;
use Library\Application\PayFine;
use Library\Application\PlaceReservation;
use Library\Application\ReturnLoan;
use Library\Domain\FinePolicy;
use Library\Domain\ReservationQueue;
use Library\Infrastructure\Clock\RequestClock;
use Library\Infrastructure\Persistence\PdoBookRepository;
use Library\Infrastructure\Persistence\PdoCopyRepository;
use Library\Infrastructure\Persistence\PdoLoanRepository;
use Library\Infrastructure\Persistence\PdoMemberRepository;
use Library\Infrastructure\Persistence\PdoReservationRepository;
use Library\Infrastructure\Persistence\PdoTransactionManager;

function servicios()
{
    global $db;
    static $casos = null;

    if ($casos === null) {
        $reloj = RequestClock::fromGlobals();
        $transacciones = new PdoTransactionManager($db);
        $socios = new PdoMemberRepository($db);
        $libros = new PdoBookRepository($db);
        $ejemplares = new PdoCopyRepository($db);
        $prestamos = new PdoLoanRepository($db);
        $reservas = new PdoReservationRepository($db);

        $casos = array(
            'prestar' => new LendCopy($transacciones, $socios, $ejemplares, $prestamos, $reservas, $reloj),
            'devolver' => new ReturnLoan($transacciones, $prestamos, $ejemplares, $reservas, $reloj, new FinePolicy(), new ReservationQueue()),
            'reservar' => new PlaceReservation($transacciones, $socios, $libros, $ejemplares, $reservas, $reloj),
            'cancelar_reserva' => new CancelReservation($transacciones, $reservas, $ejemplares, new ReservationQueue()),
            'pagar_multa' => new PayFine($transacciones, $prestamos),
        );
    }

    return $casos;
}
