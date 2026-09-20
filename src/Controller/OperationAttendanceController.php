<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\OperationAttendanceService;

/**
 * Lets a leader mark who actually showed up, independent of what they RSVP'd (or whether
 * they did at all). This is
 * the switch that turns OperationRSVP::attended from "always null" into real data, which
 * is what the AAR submission flow, the Attendance module, and AWOL detection all key off of.
 */
class OperationAttendanceController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly OperationAttendanceService $attendanceService,
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

        $soldier = $this->soldierProfileRepository->find($request->request->getInt('soldier_id'));
        if ($soldier === null) {
            $this->addFlash('error', 'Unknown soldier.');
            return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
        }

        $attendedValue = $request->request->getString('attended');
        $attended = match ($attendedValue) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $this->attendanceService->mark($operation, $soldier, $attended);

        $this->addFlash('success', 'Attendance updated.');
        return $this->redirectToRoute('command_net_operation_detail', ['id' => $operation->getId()]);
    }
}
