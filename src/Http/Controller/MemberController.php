<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\Query\MemberQuery;
use Library\Domain\Clock;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** Members: list (socios.php), detail (socio_ver.php) and the form (socio_editar.php). */
final class MemberController
{
    /** @var PageRenderer */
    private $page;

    /** @var MemberQuery */
    private $query;

    /** @var Clock */
    private $clock;

    public function __construct(PageRenderer $page, MemberQuery $query, Clock $clock)
    {
        $this->page = $page;
        $this->query = $query;
        $this->clock = $clock;
    }

    public function index(Request $request, Session $session): Response
    {
        return $this->page->render($request, $session, 'Socios', 'members/list.php', [
            'members' => $this->query->all(),
        ]);
    }

    public function show(Request $request, Session $session): Response
    {
        $id = $request->queryInt('id');
        $member = $this->query->find($id);
        if ($member === null) {
            return Response::redirectWithMessage('socios.php', 'El socio no existe.', 'error');
        }

        return $this->page->render($request, $session, 'Socio ' . $member['name'], 'members/show.php', [
            'member' => $member,
            'loans' => $this->query->loansOf($id),
            'unpaidFines' => $this->query->unpaidFineTotal($id),
            'today' => $this->clock->today()->format('Y-m-d'),
        ]);
    }
}
