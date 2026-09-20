<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Controller;

use DomainException;
use Forumify\Core\Entity\User;
use InvalidArgumentException;
use MajesticDev\CommandNet\Entity\FormDefinition;
use MajesticDev\CommandNet\Repository\FormDefinitionRepository;
use MajesticDev\CommandNet\Repository\FormSubmissionRepository;
use MajesticDev\CommandNet\Service\FormSchema;
use MajesticDev\CommandNet\Service\FormSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Forms members can fill in, and the status of what they have already submitted.
 */
class FormsController extends AbstractController
{
    public function __construct(
        private readonly FormDefinitionRepository $formRepository,
        private readonly FormSubmissionRepository $submissionRepository,
        private readonly FormSchema $schema,
        private readonly FormSubmissionService $submissionService,
    ) {
    }

    #[Route('/forms', name: 'forms')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('command-net.forms.submit');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('@CommandNetPlugin/frontend/forms/index.html.twig', [
            'forms' => $this->formRepository->findEnabled(),
            'submissions' => $this->submissionRepository->findRecentFor($user),
        ]);
    }

    #[Route('/forms/{id}', name: 'form_fill', requirements: ['id' => '\d+'])]
    public function fill(FormDefinition $definition, Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.forms.submit');

        if (!$definition->isEnabled()) {
            throw $this->createNotFoundException();
        }

        try {
            $fields = $this->schema->parse($definition->getFieldList());
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'This form is not set up correctly. Please tell the staff.');
            return $this->redirectToRoute('command_net_forms');
        }

        $builder = $this->createFormBuilder();
        $this->submissionService->addFields($builder, $fields);
        $form = $builder->getForm()->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            /** @var array<string, mixed> $data */
            $data = $form->getData();

            try {
                $this->submissionService->submit($definition, $user, $data);
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('command_net_forms');
            }

            $this->addFlash('success', 'Submitted. You will be notified when it has been reviewed.');
            return $this->redirectToRoute('command_net_forms');
        }

        return $this->render('@CommandNetPlugin/frontend/forms/fill.html.twig', [
            'definition' => $definition,
            'form' => $form->createView(),
        ]);
    }
}
