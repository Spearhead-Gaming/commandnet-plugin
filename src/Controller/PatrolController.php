<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use DateTime;
use DateTimeImmutable;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Form\PatrolType;
use MajesticDev\CommandNet\Repository\OperationRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\EventRules;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Patrols are member-led events: anyone with patrols.create can post one and becomes its leader,
 * and the leader (or staff) can edit or cancel it. Attendance and the AAR are handled by the
 * shared operation controllers, which ask EventRules who may do what.
 */
class PatrolController extends AbstractController
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly EventRules $eventRules,
    ) {
    }

    #[Route('/patrols/new', name: 'patrol_new')]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.patrols.create');

        /** @var User $user */
        $user = $this->getUser();
        $patrol = new Operation();
        $patrol->setType(OperationType::PATROL);
        $patrol->setLeader($user);
        // Suggest tomorrow at the top of the current hour rather than the moment the form opened.
        $patrol->setStartDateTime(DateTime::createFromImmutable(
            (new DateTimeImmutable('+1 day'))->setTime((int)date('G'), 0),
        ));

        $form = $this->createForm(PatrolType::class, $patrol);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // The leader is on their own roster from the start (when they are enlisted).
            $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]);
            if ($profile !== null && $profile->isEnlisted()) {
                $rsvp = new OperationRSVP($patrol, $profile);
                $rsvp->setStatus(RsvpStatus::ATTENDING);
                $patrol->getRsvps()->add($rsvp);
            }
            $this->operationRepository->save($patrol);

            $this->addFlash('success', 'Patrol posted.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $patrol->getId()]);
        }

        return $this->render('@CommandNetPlugin/frontend/patrols/form.html.twig', [
            'form' => $form,
            'patrol' => null,
        ]);
    }

    #[Route('/patrols/{id}/edit', name: 'patrol_edit', requirements: ['id' => '\d+'])]
    public function edit(Operation $patrol, Request $request): Response
    {
        $this->denyUnlessCanManage($patrol);

        $form = $this->createForm(PatrolType::class, $patrol);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->operationRepository->save($patrol);

            $this->addFlash('success', 'Patrol updated.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $patrol->getId()]);
        }

        return $this->render('@CommandNetPlugin/frontend/patrols/form.html.twig', [
            'form' => $form,
            'patrol' => $patrol,
        ]);
    }

    #[Route('/patrols/{id}/cancel', name: 'patrol_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Operation $patrol, Request $request): RedirectResponse
    {
        $this->denyUnlessCanManage($patrol);

        if (!$this->isCsrfTokenValid('patrol_cancel_' . $patrol->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $patrol->getId()]);
        }

        $patrol->setStatus(OperationStatus::CANCELLED);
        $this->operationRepository->save($patrol);

        $this->addFlash('success', 'Patrol cancelled.');
        return $this->redirectToRoute('command_net_operation_detail', ['id' => $patrol->getId()]);
    }

    /**
     * The leader's own page: every patrol they have led, with its AAR state.
     */
    #[Route('/patrols/mine', name: 'patrols_mine')]
    public function mine(): Response
    {
        $this->denyAccessUnlessGranted('command-net.operations.view');

        /** @var User $user */
        $user = $this->getUser();
        $now = new DateTimeImmutable();
        $rows = array_map(fn (Operation $patrol): array => [
            'patrol' => $patrol,
            'aarStatus' => $this->eventRules->aarStatus($patrol, $now),
            'dueAt' => $this->eventRules->aarDueAt($patrol),
        ], $this->operationRepository->findPatrolsLedBy($user));

        return $this->render('@CommandNetPlugin/frontend/patrols/mine.html.twig', ['rows' => $rows]);
    }

    private function denyUnlessCanManage(Operation $patrol): void
    {
        if ($patrol->getType() !== OperationType::PATROL) {
            throw $this->createNotFoundException();
        }
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$this->isGranted('command-net.admin.operations.manage') && !$this->eventRules->isLeader($patrol, $user)) {
            throw $this->createAccessDeniedException();
        }
    }
}
