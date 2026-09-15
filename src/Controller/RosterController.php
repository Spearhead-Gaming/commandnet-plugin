<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;

class RosterController extends AbstractController
{
    public function __construct(
        private readonly SoldierProfileRepository $soldierProfileRepository,
    ) {
    }

    #[Route('/roster', name: 'roster')]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('command-net.roster.view');

        return $this->render('@CommandNetPlugin/frontend/roster/list.html.twig', [
            'roster' => $this->soldierProfileRepository->findRoster(),
        ]);
    }
}
