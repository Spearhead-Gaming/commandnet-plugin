<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MajesticDev\CommandNet\Entity\Squad;
use MajesticDev\CommandNet\Form\OrbatOutlineType;
use MajesticDev\CommandNet\Form\SquadSelfServiceType;
use MajesticDev\CommandNet\Repository\AssignmentRepository;
use MajesticDev\CommandNet\Repository\SquadRepository;
use MajesticDev\CommandNet\Service\OrbatImporter;
use MajesticDev\CommandNet\Service\PositionCatalog;
use MajesticDev\CommandNet\Service\UnitAuthorizationChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Self-service squad/team management, reached from the owning Unit's own manage page (see
 * MyUnitController) - a squad/team has no commander of its own, so there's no separate "my
 * squads" landing list the way "My Units" has one.
 */
class MySquadController extends AbstractController
{
    public function __construct(
        private readonly UnitAuthorizationChecker $authChecker,
        private readonly SquadRepository $squads,
        private readonly AssignmentRepository $assignments,
        private readonly OrbatImporter $importer,
        private readonly PositionCatalog $positionCatalog,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/command-net/my-squads/{id}', name: 'my_squad', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function manage(int $id): Response
    {
        $squad = $this->findManageable($id);

        return $this->render('@CommandNetPlugin/frontend/squads/manage.html.twig', [
            'squad' => $squad,
        ]);
    }

    #[Route('/command-net/my-squads/{id}/edit', name: 'my_squad_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $squad = $this->findManageable($id);

        $form = $this->createForm(SquadSelfServiceType::class, $squad);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncPositions($squad, (string)$form->get('positions')->getData());
            $this->em->flush();
            $this->addFlash('success', 'Squad/team updated.');
            return $this->redirectToRoute('command_net_my_squad', ['id' => $squad->getId()]);
        }

        return $this->render('@CommandNetPlugin/frontend/squads/edit.html.twig', [
            'squad' => $squad,
            'form' => $form,
            'creating' => false,
        ]);
    }

    #[Route('/command-net/my-squads/{id}/create-child', name: 'my_squad_create_child', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function createChild(int $id, Request $request): Response
    {
        $parent = $this->findManageable($id);

        $child = new Squad($parent->getUnit());
        $child->setParent($parent);
        $form = $this->createForm(SquadSelfServiceType::class, $child);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncPositions($child, (string)$form->get('positions')->getData());
            $this->em->persist($child);
            $this->em->flush();
            $this->addFlash('success', 'Team created.');
            return $this->redirectToRoute('command_net_my_squad', ['id' => $parent->getId()]);
        }

        return $this->render('@CommandNetPlugin/frontend/squads/edit.html.twig', [
            'squad' => $parent,
            'form' => $form,
            'creating' => true,
        ]);
    }

    #[Route('/command-net/my-squads/{id}/import', name: 'my_squad_import', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function import(int $id, Request $request): Response
    {
        $squad = $this->findManageable($id);

        $form = $this->createForm(OrbatOutlineType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $result = $this->importer->import((string)$form->get('outline')->getData(), $squad);
                $this->addFlash('success', "Imported {$result['squads']} team(s) and linked {$result['positions']} position(s).");
                return $this->redirectToRoute('command_net_my_squad', ['id' => $squad->getId()]);
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('@CommandNetPlugin/frontend/squads/import.html.twig', [
            'squad' => $squad,
            'form' => $form,
        ]);
    }

    #[Route('/command-net/my-squads/{id}/delete', name: 'my_squad_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $squad = $this->findManageable($id);

        if (!$this->isCsrfTokenValid('my_squad_delete_' . $squad->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_my_squad', ['id' => $squad->getId()]);
        }

        if (!$squad->getChildren()->isEmpty() || $this->assignments->count(['squad' => $squad]) > 0) {
            $this->addFlash('error', "Remove this squad/team's own child teams and assignments before deleting it.");
            return $this->redirectToRoute('command_net_my_squad', ['id' => $squad->getId()]);
        }

        $parent = $squad->getParent();
        $unit = $squad->getUnit();
        $this->squads->remove($squad);
        $this->addFlash('success', 'Squad/team deleted.');

        return $parent !== null
            ? $this->redirectToRoute('command_net_my_squad', ['id' => $parent->getId()])
            : $this->redirectToRoute('command_net_my_unit', ['id' => $unit->getId()]);
    }

    private function findManageable(int $id): Squad
    {
        $this->denyAccessUnlessGranted('command-net.units.manage_own');

        $squad = $this->squads->find($id);
        if ($squad === null) {
            throw $this->createNotFoundException();
        }
        if (!$this->authChecker->canManageSquad($squad)) {
            throw $this->createAccessDeniedException();
        }

        return $squad;
    }

    private function syncPositions(Squad $squad, string $lines): void
    {
        $squad->getPositions()->clear();
        foreach ($this->positionCatalog->resolve(explode("\n", $lines)) as $position) {
            $squad->addPosition($position);
        }
    }
}
