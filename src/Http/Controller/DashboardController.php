<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\Query\DashboardQuery;
use Library\Domain\Clock;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;

/** The home screen (index.php). */
final class DashboardController
{
    /** @var PageRenderer */
    private $page;

    /** @var DashboardQuery */
    private $query;

    /** @var Clock */
    private $clock;

    public function __construct(PageRenderer $page, DashboardQuery $query, Clock $clock)
    {
        $this->page = $page;
        $this->query = $query;
        $this->clock = $clock;
    }

    public function show(Request $request, Session $session): Response
    {
        $summary = $this->query->summary($this->clock->today()->format('Y-m-d'));

        return $this->page->render($request, $session, 'Inicio', 'dashboard/index.php', [
            'activeLoans' => $summary['active_loans'],
            'overdueCount' => $summary['overdue_count'],
            'pendingCount' => $summary['pending_count'],
            'overdue' => $summary['overdue'],
            'pending' => $summary['pending'],
        ]);
    }
}
