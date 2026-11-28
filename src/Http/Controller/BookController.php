<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\EditBook;
use Library\Application\Query\BookQuery;
use Library\Application\RegisterBook;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Books: list (libros.php) and the form (libro_editar.php). */
final class BookController
{
    /** @var PageRenderer */
    private $page;

    /** @var BookQuery */
    private $query;

    /** @var RegisterBook */
    private $register;

    /** @var EditBook */
    private $edit;

    public function __construct(PageRenderer $page, BookQuery $query, RegisterBook $register, EditBook $edit)
    {
        $this->page = $page;
        $this->query = $query;
        $this->register = $register;
        $this->edit = $edit;
    }

    public function index(Request $request, Session $session): Response
    {
        return $this->page->render($request, $session, 'Libros', 'books/list.php', [
            'books' => $this->query->allWithCopyCounts(),
        ]);
    }

    public function edit(Request $request, Session $session): Response
    {
        if ($request->isPost()) {
            return $this->save($request);
        }

        $id = $request->queryInt('id');
        $book = ['id' => 0, 'isbn' => '', 'title' => '', 'author' => '', 'year' => null];
        if ($id) {
            $found = $this->query->find($id);
            if ($found === null) {
                return Response::redirectWithMessage('libros.php', 'El libro no existe.', 'error');
            }
            $book = $found;
        }

        $heading = $id ? 'Editar libro' : 'Nuevo libro';

        return $this->page->render($request, $session, $heading, 'books/form.php', [
            'heading' => $heading,
            'book' => $book,
        ]);
    }

    private function save(Request $request): Response
    {
        $id = $request->postInt('id');
        $isbn = $request->postString('isbn');
        $title = $request->postString('titulo');
        $author = $request->postString('autor');
        $yearText = $request->postString('anio');
        $year = $yearText !== '' ? (int) $yearText : null;

        $result = $id
            ? $this->edit->handle($id, $isbn, $title, $author, $year)
            : $this->register->handle($isbn, $title, $author, $year);

        if (!$result->isSuccess()) {
            return Response::redirectWithMessage('libro_editar.php' . ($id ? '?id=' . $id : ''), 'El título y el autor son obligatorios.', 'error');
        }
        if ($id) {
            return Response::redirectWithMessage('libros.php', 'Libro actualizado.');
        }

        return Response::redirectWithMessage('ejemplares.php?libro_id=' . $result->getId(), 'Libro registrado. Ahora puedes agregar sus ejemplares.');
    }
}
