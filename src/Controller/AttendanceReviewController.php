<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Repository\UserRepository;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Repository\OperationRSVPRepository;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\AttendanceCalculator;
use MajesticDev\CommandNet\Service\AttendanceScope;
use MajesticDev\CommandNet\Service\OperationAttendanceService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Attendance review for leadership (filtered roster, per-soldier history, corrections) and, read
 * only, for a member's own history. Who sees and changes what is AttendanceScope's call.
 */
class AttendanceReviewController extends AbstractController
{
    public function __construct(
        private readonly AttendanceScope $scope,
        private readonly AttendanceCalculator $attendanceCalculator,
        private readonly OperationAttendanceService $attendanceService,
        private readonly OperationRSVPRepository $rsvpRepository,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/attendance/review', name: 'attendance_review', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->scope->hasLeadershipAccess()) {
            throw $this->createAccessDeniedException();
        }

        $unitId = $request->query->getInt('unit');
        $minRate = (float)$request->query->get('min_rate', 0);
        $minMiss = $request->query->getInt('min_miss');

        $soldiers = $this->scope->soldiers();
        if ($unitId > 0) {
            $soldiers = array_values(array_filter(
                $soldiers,
                static fn (SoldierProfile $s): bool => $s->getPrimaryAssignment()?->getUnit()->getId() === $unitId,
            ));
        }

        $stats = $this->attendanceCalculator->calculateMany($soldiers);
        $rows = [];
        foreach ($soldiers as $soldier) {
            $soldierStats = $stats[$soldier->getId()];
            if ($minRate > 0 && ($soldierStats->noShowRate ?? 0.0) < $minRate) {
                continue;
            }
            if ($minMiss > 0 && $soldierStats->currentMissStreak < $minMiss) {
                continue;
            }
            $rows[] = ['soldier' => $soldier, 'stats' => $soldierStats];
        }

        return $this->render('@CommandNetPlugin/frontend/attendance/review.html.twig', [
            'rows' => $rows,
            'units' => $this->scope->units(),
            'filters' => ['unit' => $unitId, 'min_rate' => $minRate > 0 ? $minRate : '', 'min_miss' => $minMiss > 0 ? $minMiss : ''],
        ]);
    }

    #[Route('/attendance/review/{username}', name: 'attendance_review_soldier', methods: ['GET'])]
    public function soldier(string $username): Response
    {
        $soldier = $this->findSoldier($username);
        if (!$this->scope->canReview($soldier)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('@CommandNetPlugin/frontend/attendance/review_soldier.html.twig', [
            'soldier' => $soldier,
            'stats' => $this->attendanceCalculator->calculate($soldier),
            'history' => $this->rsvpRepository->findAttendanceHistory($soldier),
            'canCorrect' => $this->scope->canCorrect($soldier),
        ]);
    }

    #[Route('/attendance/review/{username}/rsvp/{id}', name: 'attendance_review_correct', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function correct(string $username, int $id, Request $request): RedirectResponse
    {
        $soldier = $this->findSoldier($username);
        if (!$this->scope->canCorrect($soldier)) {
            throw $this->createAccessDeniedException();
        }

        $back = $this->redirectToRoute('command_net_attendance_review_soldier', ['username' => $username]);
        if (!$this->isCsrfTokenValid('attendance_correct_' . $id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $back;
        }

        // The id in the URL must be one of this soldier's own records.
        $rsvp = $this->rsvpRepository->find($id);
        if ($rsvp === null || $rsvp->getSoldier()->getId() !== $soldier->getId()) {
            throw $this->createNotFoundException();
        }

        $attended = match ($request->request->getString('attended')) {
            '1' => true,
            '0' => false,
            default => null,
        };
        $before = $rsvp->getAttended();
        $this->attendanceService->mark($rsvp->getOperation(), $soldier, $attended);
        // Corrections move AWOL status and combat credit, so leave a trail of who changed what.
        $this->logger->info('Attendance corrected', [
            'actor' => $this->getUser()?->getUserIdentifier(),
            'soldier' => $soldier->getId(),
            'operation' => $rsvp->getOperation()->getId(),
            'from' => $before,
            'to' => $attended,
        ]);
        $this->addFlash('success', 'Attendance updated.');

        return $back;
    }

    private function findSoldier(string $username): SoldierProfile
    {
        // Nothing to say about who exists to someone with no business on these pages.
        if (!$this->scope->mayUseReview()) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->userRepository->findOneBy(['username' => $username]);
        $soldier = $user !== null ? $this->soldierProfileRepository->findOneBy(['user' => $user]) : null;
        if ($soldier === null) {
            throw $this->createNotFoundException();
        }

        return $soldier;
    }
}
