<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\Authenticate;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Sign in (login.php) and sign out (logout.php). */
final class AuthController
{
    /** @var PageRenderer */
    private $page;

    /** @var Authenticate */
    private $authenticate;

    public function __construct(PageRenderer $page, Authenticate $authenticate)
    {
        $this->page = $page;
        $this->authenticate = $authenticate;
    }

    public function login(Request $request, Session $session): Response
    {
        if ($session->has(Session::LIBRARIAN_ID)) {
            return Response::redirect('index.php');
        }

        if ($request->isPost()) {
            $result = $this->authenticate->handle($request->postString('usuario'), $request->postString('clave'));
            if (!$result->isSuccess()) {
                return Response::redirectWithMessage('login.php', 'Usuario o clave incorrectos.', 'error');
            }

            $session->regenerate();
            $session->set(Session::LIBRARIAN_ID, $result->getLibrarianId());
            $session->set(Session::LIBRARIAN_NAME, $result->getLibrarianName());

            return Response::redirect('index.php');
        }

        return $this->page->render($request, $session, 'Ingresar', 'auth/login.php');
    }

    public function logout(Request $request, Session $session): Response
    {
        $session->destroy();

        return Response::redirectWithMessage('login.php', 'Sesión cerrada. ¡Hasta pronto!');
    }
}
