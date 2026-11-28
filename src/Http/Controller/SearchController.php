<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\Query\SearchQuery;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Search of books by title or author (buscar.php). */
final class SearchController
{
    /** @var PageRenderer */
    private $page;

    /** @var SearchQuery */
    private $query;

    public function __construct(PageRenderer $page, SearchQuery $query)
    {
        $this->page = $page;
        $this->query = $query;
    }

    public function index(Request $request, Session $session): Response
    {
        $text = $request->queryString('q');

        return $this->page->render($request, $session, 'Buscar libros', 'search/index.php', [
            'text' => $text,
            'books' => $text !== '' ? $this->query->booksMatching($text) : null,
        ]);
    }
}
