<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\PayFine;
use Library\Application\Query\FineQuery;
use Library\Http\Format;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Fines (multas.php): the list and paying a fine. */
final class FineController
{
    private const ERRORS = [
        'loan_not_found' => 'El préstamo no existe.',
        'no_fine' => 'Ese préstamo no tiene multa.',
        'already_paid' => 'Esa multa ya estaba pagada.',
    ];

    /** @var PageRenderer */
    private $page;

    /** @var FineQuery */
    private $query;

    /** @var PayFine */
    private $pay;

    public function __construct(PageRenderer $page, FineQuery $query, PayFine $pay)
    {
        $this->page = $page;
        $this->query = $query;
        $this->pay = $pay;
    }

    public function index(Request $request, Session $session): Response
    {
        if ($request->isPost()) {
            $result = $this->pay->handle($request->postInt('prestamo_id'));
            $code = $result->getErrorCode();
            if ($code !== null) {
                if (!isset(self::ERRORS[$code])) {
                    throw new \LogicException('Error code without a text: ' . $code);
                }

                return Response::redirectWithMessage('multas.php', self::ERRORS[$code], 'error');
            }

            return Response::redirectWithMessage('multas.php', 'Multa pagada: ' . Format::money($result->getAmount()) . '.');
        }

        return $this->page->render($request, $session, 'Multas', 'fines/list.php', [
            'fines' => $this->query->all(),
        ]);
    }
}
