<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\ReportIn;
use MajesticDev\CommandNet\Repository\ReportInRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\ReportInService;

class ReportInController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly ReportInRepository $reportInRepository,
        private readonly ReportInService $reportInService,
    ) {
    }

    #[Route('/roster/report-in', name: 'report_in', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.reportin.submit');

        /** @var User $user */
        $user = $this->getUser();
        $profile = $this->soldierProfileRepository->findOneBy(['user' => $user]);
        if ($profile === null || !$profile->isEnlisted()) {
            $this->addFlash('error', 'Only enlisted personnel can report in.');
            return $this->redirectToRoute('command_net_roster');
        }

        if (!$this->isCsrfTokenValid('report_in', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_roster');
        }

        $reportIn = new ReportIn($profile);
        $this->reportInRepository->save($reportIn);
        $profile->setLastReportIn($reportIn->getReportedAt());
        $this->soldierProfileRepository->save($profile);
        $this->reportInService->handleReportedIn($profile);

        $this->addFlash('success', 'Reported in.');
        return $this->redirectToRoute('command_net_roster');
    }

    /**
     * Corrections happen by removing the wrong entry, same as everywhere else - but this
     * one also has to recompute SoldierProfile::$lastReportIn if the entry removed was the
     * most recent one, since that column is a cache, not the source of truth.
     */
    #[Route('/roster/report-in/{id}/delete', name: 'report_in_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('command-net.admin.reportin.manage');

        if (!$this->isCsrfTokenValid('report_in_delete_' . $id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_roster');
        }

        $reportIn = $this->reportInRepository->find($id);
        if ($reportIn !== null) {
            $profile = $reportIn->getSoldier();
            $this->reportInRepository->remove($reportIn);

            $latest = $this->reportInRepository->findLatestFor($profile);
            $profile->setLastReportIn($latest?->getReportedAt());
            $this->soldierProfileRepository->save($profile);

            $this->addFlash('success', 'Report in entry removed.');
        }

        return $this->redirectToRoute('command_net_roster');
    }
}
