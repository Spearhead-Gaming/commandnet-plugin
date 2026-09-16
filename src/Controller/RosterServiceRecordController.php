<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Repository\ServiceRecordRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

/**
 * Service records are mostly written automatically by the award/qualification/assignment/AAR
 * flows, each of which has its own delete action for the record it owns. This one covers
 * removing a wrong entry directly from the timeline - most usefully a manual note or
 * disciplinary entry a leader added by hand. Gated on the broad personnel-manage permission
 * rather than a per-type one, since a timeline entry isn't owned by a single module.
 */
class RosterServiceRecordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ServiceRecordRepository $serviceRecordRepository,
    ) {
    }

    #[Route('/roster/{username}/service-record/{id}/delete', name: 'roster_service_record_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(string $username, int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.admin.personnel.manage');

        $user = $this->userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            throw $this->createNotFoundException();
        }
        $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]);
        if ($profile === null) {
            throw $this->createNotFoundException('This user has no personnel file.');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('roster_service_record_delete_' . $id, $token)) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_roster_profile', ['username' => $username]);
        }

        $record = $this->serviceRecordRepository->find($id);
        if ($record !== null && $record->getSoldier() === $profile) {
            $this->serviceRecordRepository->remove($record);
            $this->addFlash('success', 'Service record removed.');
        }

        return $this->redirectToRoute('command_net_roster_profile', ['username' => $username]);
    }
}
