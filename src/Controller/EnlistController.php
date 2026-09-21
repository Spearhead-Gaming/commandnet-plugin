<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use DomainException;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Form\EnlistmentApplicationType;
use MajesticDev\CommandNet\Repository\EnlistmentApplicationRepository;
use MajesticDev\CommandNet\Service\EnlistmentService;
use MajesticDev\CommandNet\Service\EnlistmentSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Any signed-in member can apply while enlistment is open; staff review the application in
 * the admin, and accepting it is what creates the personnel file.
 */
class EnlistController extends AbstractController
{
    public function __construct(
        private readonly EnlistmentService $enlistmentService,
        private readonly EnlistmentSettings $settings,
        private readonly EnlistmentApplicationRepository $applicationRepository,
    ) {
    }

    #[Route('/enlist', name: 'enlist')]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();
        $reason = $this->enlistmentService->ineligibleReason($user);

        $form = null;
        if ($reason === null) {
            $form = $this->createForm(EnlistmentApplicationType::class, null, ['user' => $user]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var EnlistmentApplication $application */
                $application = $form->getData();
                try {
                    $this->enlistmentService->submit($application);
                } catch (DomainException $e) {
                    $this->addFlash('error', $e->getMessage());
                    return $this->redirectToRoute('command_net_enlist');
                }

                $this->addFlash('success', 'Application submitted. You will be notified when it has been reviewed.');
                return $this->redirectToRoute('command_net_enlist');
            }
        }

        return $this->render('@CommandNetPlugin/frontend/enlistment/enlist.html.twig', [
            'form' => $form?->createView(),
            'reason' => $reason,
            'latest' => $this->applicationRepository->findLatestFor($user),
            'instructions' => $this->settings->all()['instructions'],
        ]);
    }
}
