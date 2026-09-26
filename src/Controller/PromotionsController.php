<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use MajesticDev\CommandNet\Entity\Enum\SoldierStatus;
use MajesticDev\CommandNet\Repository\SoldierProfileRepository;
use MajesticDev\CommandNet\Service\PromotionEligibility;
use MajesticDev\CommandNet\Service\RankChangeService;
use MajesticDev\CommandNet\Service\RankSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PromotionsController extends AbstractController
{
    public function __construct(
        private readonly PromotionEligibility $promotionEligibility,
        private readonly SoldierProfileRepository $soldierProfileRepository,
        private readonly RankChangeService $rankChangeService,
        private readonly RankSettings $rankSettings,
    ) {
    }

    #[Route('/promotions', name: 'promotions')]
    public function __invoke(): Response
    {
        if (!$this->rankSettings->isEnabled()) {
            throw new NotFoundHttpException();
        }

        if (!$this->isGranted('command-net.promotions.view')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('@CommandNetPlugin/frontend/promotions/index.html.twig', [
            'rows' => $this->promotionEligibility->evaluateRoster(),
        ]);
    }

    /**
     * Promotes into the next rank only while every requirement is still met, re-checked here
     * rather than trusted from the page. A promotion that skips the requirements is a rank edit
     * on the admin personnel form instead.
     */
    #[Route('/promotions/{id}/promote', name: 'promotions_promote', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function promote(int $id, Request $request): RedirectResponse
    {
        if (!$this->rankSettings->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted('command-net.admin.personnel.manage');

        if (!$this->isCsrfTokenValid('promote_' . $id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirectToRoute('command_net_promotions');
        }

        $soldier = $this->soldierProfileRepository->find($id);
        if ($soldier === null || $soldier->getStatus() !== SoldierStatus::ACTIVE) {
            $this->addFlash('error', 'Only active personnel can be promoted.');
            return $this->redirectToRoute('command_net_promotions');
        }

        $evaluation = $this->promotionEligibility->evaluateSoldier($soldier);
        if ($evaluation === null || !$evaluation['eligible']) {
            $this->addFlash('error', 'This soldier does not meet the requirements for the next rank.');
            return $this->redirectToRoute('command_net_promotions');
        }

        $this->rankChangeService->changeRank($soldier, $evaluation['nextRank']);

        $this->addFlash('success', sprintf('%s promoted to %s.', $soldier->getUser()->getDisplayName(), $evaluation['nextRank']->getName()));
        return $this->redirectToRoute('command_net_promotions');
    }
}
