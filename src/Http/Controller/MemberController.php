<?php

declare(strict_types=1);

namespace Library\Http\Controller;

use Library\Application\EditMember;
use Library\Application\Query\MemberQuery;
use Library\Application\RegisterMember;
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

    /** @var RegisterMember */
    private $register;

    /** @var EditMember */
    private $edit;

    /** @var Clock */
    private $clock;

    public function __construct(PageRenderer $page, MemberQuery $query, RegisterMember $register, EditMember $edit, Clock $clock)
    {
        $this->page = $page;
        $this->query = $query;
        $this->register = $register;
        $this->edit = $edit;
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

    public function edit(Request $request, Session $session): Response
    {
        $id = $request->queryInt('id');

        if ($request->isPost()) {
            return $this->save($request);
        }

        $member = ['id' => 0, 'document' => '', 'name' => '', 'email' => '', 'phone' => '', 'active' => true];
        if ($id) {
            $found = $this->query->find($id);
            if ($found === null) {
                return Response::redirectWithMessage('socios.php', 'El socio no existe.', 'error');
            }
            $member = $found;
        }

        $heading = $id ? 'Editar socio' : 'Nuevo socio';

        return $this->page->render($request, $session, $heading, 'members/form.php', [
            'heading' => $heading,
            'member' => $member,
        ]);
    }

    private function save(Request $request): Response
    {
        $id = $request->postInt('id');
        $document = $request->postString('documento');
        $name = $request->postString('nombre');
        $email = $request->postString('correo');
        $phone = $request->postString('telefono');
        $active = $request->hasPost('activo');

        $result = $id
            ? $this->edit->handle($id, $document, $name, $email, $phone, $active)
            : $this->register->handle($document, $name, $email, $phone, $active);

        if (!$result->isSuccess()) {
            $messages = [
                'required_fields' => 'El documento y el nombre son obligatorios.',
                'document_taken' => 'Ya existe un socio con ese documento.',
            ];

            return Response::redirectWithMessage('socio_editar.php' . ($id ? '?id=' . $id : ''), $messages[(string) $result->getErrorCode()], 'error');
        }

        return Response::redirectWithMessage('socios.php', $id ? 'Socio actualizado.' : 'Socio registrado.');
    }
}
