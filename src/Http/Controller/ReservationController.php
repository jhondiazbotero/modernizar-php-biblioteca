<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\CancelReservation;
use Library\Application\PlaceReservation;
use Library\Application\Query\BookQuery;
use Library\Application\Query\MemberQuery;
use Library\Application\Query\ReservationQuery;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Reservations (reservas.php): queue of a book, current reservations, new reservation and cancellation. */
final class ReservationController
{
    private const PLACE_ERRORS = [
        'member_not_found' => 'Elige un socio.',
        'book_not_found' => 'Elige un libro.',
        'copies_available' => 'El libro tiene ejemplares disponibles: no hace falta reservarlo.',
        'already_pending' => 'El socio ya tiene una reserva pendiente de ese libro.',
    ];

    private const CANCEL_ERRORS = [
        'reservation_not_found' => 'La reserva no existe.',
        'not_cancellable' => 'Esa reserva ya no se puede cancelar.',
    ];

    /** @var PageRenderer */
    private $page;

    /** @var ReservationQuery */
    private $reservations;

    /** @var MemberQuery */
    private $members;

    /** @var BookQuery */
    private $books;

    /** @var PlaceReservation */
    private $place;

    /** @var CancelReservation */
    private $cancel;

    public function __construct(
        PageRenderer $page,
        ReservationQuery $reservations,
        MemberQuery $members,
        BookQuery $books,
        PlaceReservation $place,
        CancelReservation $cancel
    ) {
        $this->page = $page;
        $this->reservations = $reservations;
        $this->members = $members;
        $this->books = $books;
        $this->place = $place;
        $this->cancel = $cancel;
    }

    public function index(Request $request, Session $session): Response
    {
        if ($request->isPost()) {
            $action = $request->postString('accion');

            if ($action === 'crear') {
                $result = $this->place->handle($request->postInt('socio_id'), $request->postInt('libro_id'));
                if (!$result->isSuccess()) {
                    return Response::redirectWithMessage('reservas.php', self::message(self::PLACE_ERRORS, $result->getErrorCode()), 'error');
                }

                return Response::redirectWithMessage(
                    'reservas.php?libro_id=' . $result->getBookId(),
                    'Reserva registrada para ' . $result->getMemberName() . '.'
                );
            }

            if ($action === 'cancelar') {
                $result = $this->cancel->handle($request->postInt('reserva_id'));
                if (!$result->isSuccess()) {
                    return Response::redirectWithMessage('reservas.php', self::message(self::CANCEL_ERRORS, $result->getErrorCode()), 'error');
                }

                $message = 'Reserva cancelada.';
                if ($result->hadCopy()) {
                    if ($result->getAssignedMemberName() !== null) {
                        $message .= ' Ejemplar apartado para ' . $result->getAssignedMemberName();
                    } else {
                        $message .= ' El ejemplar quedó disponible.';
                    }
                }

                return Response::redirectWithMessage('reservas.php', $message);
            }
        }

        $queueBookId = $request->queryInt('libro_id');
        $queueTitle = null;
        $queue = [];
        if ($queueBookId) {
            $queueTitle = $this->books->titleOf($queueBookId);
            $queue = $this->reservations->queueOfBook($queueBookId);
        }

        return $this->page->render($request, $session, 'Reservas', 'reservations/index.php', [
            'queueTitle' => $queueTitle,
            'queue' => $queue,
            'reservations' => $this->reservations->current(),
            'members' => $this->members->activeOptions(),
            'books' => $this->books->options(),
        ]);
    }

    /**
     * @param array<string, string> $messages
     */
    private static function message(array $messages, ?string $code): string
    {
        if ($code === null || !isset($messages[$code])) {
            throw new \LogicException('Error code without a text: ' . (string) $code);
        }

        return $messages[$code];
    }
}
