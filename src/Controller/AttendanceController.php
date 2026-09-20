<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\AttendanceCalculator;

class AttendanceController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly AttendanceCalculator $attendanceCalculator,
    ) {
    }

    #[Route('/attendance', name: 'attendance')]
    public function __invoke(): Response
    {
        $canViewAll = $this->isGranted('command-net.attendance.view_all');
        if (!$canViewAll && !$this->isGranted('command-net.attendance.view_own')) {
            throw $this->createAccessDeniedException();
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $myProfile = $user !== null
            ? $this->soldierProfileRepository->findOneBy(['user' => $user])
            : null;

        $roster = null;
        if ($canViewAll) {
            $roster = array_map(
                fn ($soldier) => [
                    'soldier' => $soldier,
                    'stats' => $this->attendanceCalculator->calculate($soldier),
                ],
                $this->soldierProfileRepository->findRoster(),
            );
        }

        return $this->render('@CommandNetPlugin/frontend/attendance/index.html.twig', [
            'myProfile' => $myProfile,
            'myStats' => $myProfile !== null ? $this->attendanceCalculator->calculate($myProfile) : null,
            'canViewAll' => $canViewAll,
            'roster' => $roster,
        ]);
    }
}
