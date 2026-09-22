<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use MajesticDev\CommandNet\Entity\Deployment;
use MajesticDev\CommandNet\Service\DeploymentOperationGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Generate Operations": shows which Wednesday and Saturday 2000 Eastern Operations a Deployment
 * is missing and, on confirmation, creates them. A GET only previews; creating needs a POST.
 */
#[Route('/command-net/deployments/{id}/generate-operations', 'command_net_deployment_generate', requirements: ['id' => '\d+'])]
class DeploymentGenerateController extends AbstractController
{
    public function __construct(private readonly DeploymentOperationGenerator $generator)
    {
    }

    public function __invoke(Deployment $deployment, Request $request): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.deployments.manage');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('deployment_generate_' . $deployment->getId(), $request->request->getString('_token'))) {
                $this->addFlash('error', 'Your session expired, please try again.');
                return $this->redirectToRoute('forumify_admin_command_net_deployment_generate', ['id' => $deployment->getId()]);
            }

            $created = $this->generator->generate($deployment);
            $this->addFlash('success', sprintf('Created %d operation(s).', $created));
            return $this->redirectToRoute('forumify_admin_command_net_deployments_list');
        }

        return $this->render('@CommandNetPlugin/admin/deployment_generate.html.twig', [
            'deployment' => $deployment,
            'operations' => $this->generator->build($deployment),
        ]);
    }
}
