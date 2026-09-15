<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Repository\OperationRepository;

class OperationsController extends AbstractController
{
    public function __construct(
        private readonly OperationRepository $operationRepository,
    ) {
    }

    #[Route('/operations', name: 'operations')]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('command-net.operations.view');

        return $this->render('@CommandNetPlugin/frontend/operations/list.html.twig', [
            'upcoming' => $this->operationRepository->findUpcoming(),
            'past' => $this->operationRepository->findPast(),
        ]);
    }
}
