<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Command Net admin section's own landing page - a Forumify core dashboard, rebuilt for
 * this plugin (see the Admin/Components/Dashboard/* tiles), since core's own /admin dashboard
 * only knows about forumify users/topics, not personnel or units.
 */
#[Route('/command-net', 'command_net_overview', methods: ['GET'])]
class OverviewController extends AbstractController
{
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.personnel.view');

        return $this->render('@CommandNetPlugin/admin/overview.html.twig');
    }
}
