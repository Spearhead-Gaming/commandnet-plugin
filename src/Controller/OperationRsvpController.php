<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Entity\OperationRSVP;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

class OperationRsvpController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly OperationRSVPRepository $rsvpRepository,
    ) {
    }

    #[Route('/operations/{id}/rsvp', name: 'operation_rsvp', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function __invoke(Operation $operation, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.operations.rsvp');

        /** @var User $user */
        $user = $this->getUser();
        $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]);
        if ($profile === null) {
            $this->addFlash('error', 'Only enlisted personnel can RSVP.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('operation_rsvp_' . $operation->getId(), $token)) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
        }

        $statusValue = $request->request->getString('status');
        $status = RsvpStatus::tryFrom($statusValue);
        if ($status === null) {
            $this->addFlash('error', 'Invalid RSVP status.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
        }

        $rsvp = $operation->getRsvpFor($profile);
        if ($rsvp === null) {
            $rsvp = new OperationRSVP($operation, $profile);
        }
        $rsvp->setStatus($status);
        $this->rsvpRepository->save($rsvp);

        $this->addFlash('success', 'RSVP updated.');
        return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
    }
}
