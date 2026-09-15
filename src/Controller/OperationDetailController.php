<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Forumify\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Enum\RsvpStatus;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

class OperationDetailController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
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

        return $this->render('@CommandNetPlugin/frontend/operations/detail.html.twig', [
            'operation' => $operation,
            'myProfile' => $myProfile,
            'myRsvp' => $myProfile !== null ? $operation->getRsvpFor($myProfile) : null,
            'rsvpStatuses' => RsvpStatus::cases(),
        ]);
    }
}
