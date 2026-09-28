<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MajesticDev\CommandNet\Entity\Squad;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Form\OrbatOutlineType;
use MajesticDev\CommandNet\Form\SquadSelfServiceType;
use MajesticDev\CommandNet\Form\UnitSelfServiceType;
use MajesticDev\CommandNet\Repository\SquadRepository;
use MajesticDev\CommandNet\Repository\UnitRepository;
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
 * Self-service unit management for a unit's own commander (see UnitAuthorizationChecker),
 * separate from the full admin CRUD at /admin/command-net/units - a platoon leader can shape
 * their own platoon's sub-tree here without needing broader admin panel access.
 */
class MyUnitController extends AbstractController
{
    public function __construct(
        private readonly UnitAuthorizationChecker $authChecker,
        private readonly UnitRepository $units,
        private readonly SquadRepository $squads,
        private readonly OrbatImporter $importer,
        private readonly PositionCatalog $positionCatalog,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Replaces a unit's positions with exactly the titles typed into the self-service form's
     * unmapped "positions" field, one per line - see UnitSelfServiceType.
     */
    private function syncPositions(Unit $unit, string $lines): void
    {
        $unit->getPositions()->clear();
        foreach ($this->positionCatalog->resolve(explode("\n", $lines)) as $position) {
            $unit->addPosition($position);
        }
    }

    #[Route('/command-net/my-units', name: 'my_units', methods: ['GET'])]
    public function list(): Response
    {
        $this->denyAccessUnlessGranted('command-net.units.manage_own');

        $soldier = $this->authChecker->currentSoldier();
        $units = $soldier !== null ? $this->units->findBy(['commander' => $soldier], ['name' => 'ASC']) : [];

        return $this->render('@CommandNetPlugin/frontend/units/my_units.html.twig', [
            'units' => $units,
        ]);
    }

    #[Route('/command-net/my-units/{id}', name: 'my_unit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function manage(int $id): Response
    {
        $unit = $this->findManageable($id);
        $squads = $this->squads->findBy(['unit' => $unit, 'parent' => null], ['name' => 'ASC']);

        return $this->render('@CommandNetPlugin/frontend/units/manage.html.twig', [
            'unit' => $unit,
            'squads' => $squads,
        ]);
    }

    #[Route('/command-net/my-units/{id}/create-squad', name: 'my_unit_create_squad', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function createSquad(int $id, Request $request): Response
    {
        $unit = $this->findManageable($id);

        $squad = new Squad($unit);
        $form = $this->createForm(SquadSelfServiceType::class, $squad);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($this->positionCatalog->resolve(explode("\n", (string)$form->get('positions')->getData())) as $position) {
                $squad->addPosition($position);
            }
            $this->em->persist($squad);
            $this->em->flush();
            $this->addFlash('success', 'Squad created.');
            return $this->redirectToRoute('command_net_my_unit', ['id' => $unit->getId()]);
        }

        return $this->render('@CommandNetPlugin/frontend/squads/edit.html.twig', [
            'squad' => $squad,
            'unit' => $unit,
            'form' => $form,
            'creating' => true,
        ]);
    }

    #[Route('/command-net/my-units/{id}/edit', name: 'my_unit_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $unit = $this->findManageable($id);

        $form = $this->createForm(UnitSelfServiceType::class, $unit);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncPositions($unit, (string)$form->get('positions')->getData());
            $this->em->flush();
            $this->addFlash('success', 'Unit updated.');
            return $this->redirectToRoute('command_net_my_unit', ['id' => $unit->getId()]);
        }

        return $this->render('@CommandNetPlugin/frontend/units/edit.html.twig', [
            'unit' => $unit,
            'form' => $form,
            'creating' => false,
            'presets' => PositionCatalog::PRESETS,
        ]);
    }

    #[Route('/command-net/my-units/{id}/create-child', name: 'my_unit_create_child', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function createChild(int $id, Request $request): Response
    {
        $parent = $this->findManageable($id);

        $child = new Unit();
        $child->setParent($parent);
        $form = $this->createForm(UnitSelfServiceType::class, $child);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncPositions($child, (string)$form->get('positions')->getData());
            $this->em->persist($child);
            $this->em->flush();
            $this->addFlash('success', 'Unit created.');
            return $this->redirectToRoute('command_net_my_unit', ['id' => $parent->getId()]);
        }

        return $this->render('@CommandNetPlugin/frontend/units/edit.html.twig', [
            'unit' => $parent,
            'form' => $form,
            'creating' => true,
            'presets' => PositionCatalog::PRESETS,
        ]);
    }

    #[Route('/command-net/my-units/{id}/import', name: 'my_unit_import', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function import(int $id, Request $request): Response
    {
        $unit = $this->findManageable($id);

        $form = $this->createForm(OrbatOutlineType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $result = $this->importer->import((string)$form->get('outline')->getData(), $unit);
                $this->addFlash('success', "Imported {$result['units']} unit(s), {$result['squads']} squad/team(s), and linked {$result['positions']} position(s).");
                return $this->redirectToRoute('command_net_my_unit', ['id' => $unit->getId()]);
            } catch (\DomainException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('@CommandNetPlugin/frontend/units/import.html.twig', [
            'unit' => $unit,
            'form' => $form,
            'presets' => PositionCatalog::PRESETS,
        ]);
    }

    #[Route('/command-net/my-units/{id}/delete', name: 'my_unit_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $unit = $this->findManageable($id);

        if (!$this->isCsrfTokenValid('my_unit_delete_' . $unit->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_my_unit', ['id' => $unit->getId()]);
        }

        // Deleting a unit with children would orphan them (Unit::parent is onDelete SET
        // NULL) and deleting one with assignments would silently wipe soldiers' assignment
        // history (Assignment::unit is onDelete CASCADE) - both require the admin CRUD's
        // more deliberate delete flow instead of a one-click self-service action.
        if (!$unit->getChildren()->isEmpty() || !$unit->getAssignments()->isEmpty()) {
            $this->addFlash('error', "Remove this unit's own child units and assignments before deleting it.");
            return $this->redirectToRoute('command_net_my_unit', ['id' => $unit->getId()]);
        }

        $parent = $unit->getParent();
        $this->units->remove($unit);
        $this->addFlash('success', 'Unit deleted.');

        return $parent !== null
            ? $this->redirectToRoute('command_net_my_unit', ['id' => $parent->getId()])
            : $this->redirectToRoute('command_net_my_units');
    }

    private function findManageable(int $id): Unit
    {
        $this->denyAccessUnlessGranted('command-net.units.manage_own');

        $unit = $this->units->find($id);
        if ($unit === null) {
            throw $this->createNotFoundException();
        }
        if (!$this->authChecker->canManage($unit)) {
            throw $this->createAccessDeniedException();
        }

        return $unit;
    }
}
