<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\LendCopy;
use Library\Application\Query\CopyQuery;
use Library\Application\Query\LoanQuery;
use Library\Application\Query\MemberQuery;
use Library\Application\ReturnLoan;
use Library\Domain\Clock;
use Library\Http\Format;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Loans: lend (prestar.php), return (devolver.php) and the lists (prestamos.php). */
final class LoanController
{
    private const LEND_ERRORS = [
        'member_not_found' => 'Elige un socio.',
        'copy_not_found' => 'Elige un ejemplar.',
        'member_inactive' => 'El socio está inactivo y no puede recibir préstamos.',
        'max_active_loans' => 'El socio ya tiene 3 préstamos activos.',
        'unpaid_fines' => 'El socio tiene multas sin pagar.',
        'copy_on_loan' => 'El ejemplar ya está prestado.',
        'copy_withdrawn' => 'El ejemplar está dado de baja.',
        'copy_reserved_for_other' => 'El ejemplar está reservado para otro socio.',
    ];

    private const RETURN_ERRORS = [
        'loan_not_found' => 'Elige el préstamo que se devuelve.',
        'already_returned' => 'Ese préstamo ya fue devuelto.',
    ];

    /** @var PageRenderer */
    private $page;

    /** @var LoanQuery */
    private $loans;

    /** @var MemberQuery */
    private $members;

    /** @var CopyQuery */
    private $copies;

    /** @var LendCopy */
    private $lend;

    /** @var ReturnLoan */
    private $returnLoan;

    /** @var Clock */
    private $clock;

    public function __construct(
        PageRenderer $page,
        LoanQuery $loans,
        MemberQuery $members,
        CopyQuery $copies,
        LendCopy $lend,
        ReturnLoan $return,
        Clock $clock
    ) {
        $this->page = $page;
        $this->loans = $loans;
        $this->members = $members;
        $this->copies = $copies;
        $this->lend = $lend;
        $this->returnLoan = $return;
        $this->clock = $clock;
    }

    public function lend(Request $request, Session $session): Response
    {
        if ($request->isPost()) {
            $result = $this->lend->handle($request->postInt('socio_id'), $request->postInt('ejemplar_id'));
            if (!$result->isSuccess()) {
                return Response::redirectWithMessage('prestar.php', self::message(self::LEND_ERRORS, $result->getErrorCode()), 'error');
            }

            return Response::redirectWithMessage(
                'prestamos.php',
                'Préstamo registrado: ' . $result->getBookTitle() . ' para ' . $result->getMemberName()
                    . '. Vence el ' . Format::date($result->getDueDate()->format('Y-m-d')) . '.'
            );
        }

        return $this->page->render($request, $session, 'Prestar un libro', 'loans/lend.php', [
            'members' => $this->members->optionsForLending(),
            'copies' => $this->copies->forLending(),
        ]);
    }

    public function giveBack(Request $request, Session $session): Response
    {
        if ($request->isPost()) {
            $result = $this->returnLoan->handle($request->postInt('prestamo_id'));
            if (!$result->isSuccess()) {
                return Response::redirectWithMessage('devolver.php', self::message(self::RETURN_ERRORS, $result->getErrorCode()), 'error');
            }

            $message = 'Devolución registrada: ' . $result->getBookTitle() . ' de ' . $result->getMemberName() . '.';
            if ($result->getFine() > 0) {
                $message .= ' Multa por retraso: ' . Format::money($result->getFine()) . '.';
            }
            if ($result->getAssignedMemberName() !== null) {
                $message .= ' Ejemplar apartado para ' . $result->getAssignedMemberName();
            }

            return Response::redirectWithMessage('prestamos.php', $message);
        }

        return $this->page->render($request, $session, 'Devolver un libro', 'loans/return.php', [
            'loans' => $this->loans->openForReturn(),
        ]);
    }

    public function index(Request $request, Session $session): Response
    {
        $today = $this->clock->today()->format('Y-m-d');

        // Each list is its own query: the URL only picks which one runs.
        $filter = $request->queryString('filtro', 'activos');
        if ($filter === 'vencidos') {
            $loans = $this->loans->overdue($today);
        } elseif ($filter === 'devueltos') {
            $loans = $this->loans->returned();
        } else {
            $filter = 'activos';
            $loans = $this->loans->active();
        }

        return $this->page->render($request, $session, 'Préstamos', 'loans/list.php', [
            'filter' => $filter,
            'loans' => $loans,
            'today' => $today,
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
