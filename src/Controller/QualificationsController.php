<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use MajesticDev\CommandNet\Entity\Enum\QualificationTier;
use MajesticDev\CommandNet\Repository\QualificationRepository;

class QualificationsController extends AbstractController
{
    public function __construct(
        private readonly QualificationRepository $qualificationRepository,
    ) {
    }

    #[Route('/qualifications', name: 'qualifications')]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('command-net.qualifications.view');

        $byTier = $this->qualificationRepository->findAllForBoard();
        $tiers = [];
        foreach (QualificationTier::cases() as $tier) {
            if (!empty($byTier[$tier->value])) {
                $tiers[] = ['tier' => $tier, 'qualifications' => $byTier[$tier->value]];
            }
        }

        return $this->render('@CommandNetPlugin/frontend/qualifications/list.html.twig', [
            'tiers' => $tiers,
        ]);
    }
}
