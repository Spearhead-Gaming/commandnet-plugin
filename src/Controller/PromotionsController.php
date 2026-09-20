<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Service\PromotionEligibility;

class PromotionsController extends AbstractController
{
    public function __construct(private readonly PromotionEligibility $promotionEligibility)
    {
    }

    #[Route('/promotions', name: 'promotions')]
    public function __invoke(): Response
    {
        if (!$this->isGranted('command-net.promotions.view')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('@CommandNetPlugin/frontend/promotions/index.html.twig', [
            'rows' => $this->promotionEligibility->evaluateRoster(),
        ]);
    }
}
