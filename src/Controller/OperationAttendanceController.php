<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Service\AwolService;

/**
 * Lets a leader mark who actually showed up, independent of what they RSVP'd. This is
 * the switch that turns OperationRSVP::attended from "always null" into real data, which
 * is what the AAR submission flow, the Attendance module, and AWOL detection all key off of.
 */
class OperationAttendanceController extends AbstractController
{
    public function __construct(
        private readonly OperationRSVPRepository $rsvpRepository,
        private readonly AwolService $awolService,
    ) {
    }

    #[Route('/operations/{id}/attendance', name: 'operation_attendance', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function __invoke(Operation $operation, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.admin.operations.manage');

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('operation_attendance_' . $operation->getId(), $token)) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
        }

        $rsvpId = $request->request->getInt('rsvp_id');
        $rsvp = $operation->getRsvps()->filter(fn ($r) => $r->getId() === $rsvpId)->first() ?: null;
        if ($rsvp === null) {
            $this->addFlash('error', 'Unknown RSVP.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
        }

        $attendedValue = $request->request->getString('attended');
        $attended = match ($attendedValue) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $rsvp->setAttended($attended);
        $this->rsvpRepository->save($rsvp);
        $this->awolService->checkAfterAttendanceChange($rsvp->getSoldier());

        $this->addFlash('success', 'Attendance updated.');
        return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
    }
}
