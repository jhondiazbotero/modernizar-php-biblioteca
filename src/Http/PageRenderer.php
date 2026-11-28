<?php

declare(strict_types=1);

namespace Library\Http;

/** Puts a page body inside the common layout (header, menu, message banner and footer). */
final class PageRenderer
{
    /** @var View */
    private $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * @param string               $template template of the page body, e.g. "members/list.php"
     * @param array<string, mixed> $data     variables of that template
     */
    public function render(Request $request, Session $session, string $title, string $template, array $data = []): Response
    {
        $content = $this->view->render($template, $data);

        $html = $this->view->render('layout.php', [
            'title' => $title,
            'content' => $content,
            'signedIn' => $session->has(Session::LIBRARIAN_ID),
            'librarianName' => (string) $session->get(Session::LIBRARIAN_NAME, ''),
            'message' => $request->queryString('msg'),
            'messageClass' => $request->queryString('tipo') === 'error' ? 'error' : 'ok',
        ]);

        return Response::html($html);
    }
}
