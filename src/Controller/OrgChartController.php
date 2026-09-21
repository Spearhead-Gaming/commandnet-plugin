<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Repository\UnitRepository;

class OrgChartController extends AbstractController
{
    public function __construct(private readonly UnitRepository $unitRepository)
    {
    }

    #[Route('/units', name: 'units')]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('command-net.roster.view');

        return $this->render('@CommandNetPlugin/frontend/units/org_chart.html.twig', [
            // Unit::$children is already ordered by position, so only the top level
            // (no parent) needs its own ordering here.
            'topLevelUnits' => $this->unitRepository->findBy(['parent' => null], ['position' => 'ASC']),
        ]);
    }
}
