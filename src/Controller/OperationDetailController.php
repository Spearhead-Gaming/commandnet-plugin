<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use DateTimeImmutable;
use Forumify\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\EventRules;
use MajesticDev\CommandNet\Service\OperationAttendanceService;

class OperationDetailController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly OperationAttendanceService $attendanceService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EventRules $eventRules,
    ) {
    }

    #[Route('/operations/{id}', name: 'operation_detail', requirements: ['id' => '\d+'])]
    public function __invoke(Operation $operation): Response
    {
        $this->denyAccessUnlessGranted('command-net.operations.view');

        /** @var User|null $user */
        $user = $this->getUser();
        $myProfile = $user !== null
            ? $this->soldierProfileRepository->findOneBy(['user' => $user])
            : null;

        $isStaff = $this->isGranted('command-net.admin.operations.manage');
        $canMarkAttendance = $this->eventRules->canMarkAttendance($operation, $user, $isStaff);

        return $this->render('@CommandNetPlugin/frontend/operations/detail.html.twig', [
            'operation' => $operation,
            'myProfile' => $myProfile,
            'myRsvp' => $myProfile !== null ? $operation->getRsvpFor($myProfile) : null,
            'canMarkAttendance' => $canMarkAttendance,
            'canFileAar' => $this->eventRules->canFileAar($operation, $user, $isStaff, $this->isGranted('command-net.operations.submit_aar')),
            // Staff and a patrol's leader manage it: edit and cancel.
            'canManagePatrol' => $operation->getType() === OperationType::PATROL && ($isStaff || $this->eventRules->isLeader($operation, $user)),
            'aarStatus' => $this->eventRules->aarStatus($operation, new DateTimeImmutable()),
            'aarDueAt' => $this->eventRules->aarDueAt($operation),
            // Whoever can mark attendance sees everyone expected; everyone else just sees RSVPs.
            'attendanceRows' => $canMarkAttendance
                ? $this->attendanceService->rows($operation)
                : array_map(
                    static fn ($rsvp) => ['soldier' => $rsvp->getSoldier(), 'rsvp' => $rsvp],
                    $operation->getRsvps()->toArray(),
                ),
            'rsvpStatuses' => RsvpStatus::cases(),
            'briefingUrl' => $this->briefingUrl($operation),
        ]);
    }

    /**
     * Link to the Command Net S3 plugin's briefing page. That plugin is optional, so the link
     * only exists when its route is registered and the user may view briefings.
     */
    private function briefingUrl(Operation $operation): ?string
    {
        try {
            $url = $this->urlGenerator->generate('command_net_s3_briefing', ['id' => $operation->getId()]);
        } catch (RouteNotFoundException) {
            return null;
        }

        return $this->isGranted('command-net-s3.briefing.view') ? $url : null;
    }
}
