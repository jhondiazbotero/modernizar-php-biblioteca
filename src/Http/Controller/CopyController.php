<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\AddCopy;
use Library\Application\Query\BookQuery;
use Library\Application\Query\CopyQuery;
use Library\Application\WithdrawCopy;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Copies of a book (ejemplares.php): list, add and withdraw. */
final class CopyController
{
    private const ADD_ERRORS = [
        'empty_code' => 'Escribe el código del ejemplar.',
        'code_taken' => 'Ya existe un ejemplar con ese código.',
    ];

    private const WITHDRAW_ERRORS = [
        'copy_not_found' => 'El ejemplar no existe.',
        'copy_in_use' => 'No se puede dar de baja un ejemplar prestado o reservado.',
    ];

    /** @var PageRenderer */
    private $page;

    /** @var BookQuery */
    private $books;

    /** @var CopyQuery */
    private $copies;

    /** @var AddCopy */
    private $add;

    /** @var WithdrawCopy */
    private $withdraw;

    public function __construct(PageRenderer $page, BookQuery $books, CopyQuery $copies, AddCopy $add, WithdrawCopy $withdraw)
    {
        $this->page = $page;
        $this->books = $books;
        $this->copies = $copies;
        $this->add = $add;
        $this->withdraw = $withdraw;
    }

    public function index(Request $request, Session $session): Response
    {
        $bookId = $request->hasPost('libro_id') ? $request->postInt('libro_id') : $request->queryInt('libro_id');

        $book = $this->books->find($bookId);
        if ($book === null) {
            return Response::redirectWithMessage('libros.php', 'El libro no existe.', 'error');
        }

        if ($request->isPost()) {
            $back = 'ejemplares.php?libro_id=' . $bookId;
            $action = $request->postString('accion');

            if ($action === 'alta') {
                $result = $this->add->handle($bookId, $request->postString('codigo'));
                if (!$result->isSuccess()) {
                    return $this->failure($result->getErrorCode(), $back, self::ADD_ERRORS);
                }

                return Response::redirectWithMessage($back, 'Ejemplar agregado.');
            }

            if ($action === 'baja') {
                $result = $this->withdraw->handle($bookId, $request->postInt('ejemplar_id'));
                if (!$result->isSuccess()) {
                    return $this->failure($result->getErrorCode(), $back, self::WITHDRAW_ERRORS);
                }

                return Response::redirectWithMessage($back, 'Ejemplar dado de baja.');
            }
        }

        return $this->page->render($request, $session, 'Ejemplares de ' . $book['title'], 'copies/list.php', [
            'book' => $book,
            'copies' => $this->copies->ofBook($bookId),
        ]);
    }

    /**
     * @param array<string, string> $messages
     */
    private function failure(?string $code, string $back, array $messages): Response
    {
        if ($code === 'book_not_found') {
            return Response::redirectWithMessage('libros.php', 'El libro no existe.', 'error');
        }

        return Response::redirectWithMessage($back, $messages[(string) $code], 'error');
    }
}
