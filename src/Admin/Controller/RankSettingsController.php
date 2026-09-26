<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use MajesticDev\CommandNet\Service\RankSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/command-net/rank-settings', 'command_net_rank_settings')]
class RankSettingsController extends AbstractController
{
    public function __invoke(Request $request, RankSettings $settings): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.ranks.manage');

        $form = $this->createFormBuilder($settings->all())
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Use ranks',
                'help' => 'Shows rank throughout Command Net (roster, personnel files, promotions, documents, ORBAT, Discord commands). Existing rank data is kept and nothing is deleted when this is off.',
            ])
            ->getForm()
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->save($form->getData());
            $this->addFlash('success', 'Rank settings saved.');
            return $this->redirectToRoute('forumify_admin_command_net_rank_settings');
        }

        return $this->render('@CommandNetPlugin/admin/rank_settings.html.twig', ['form' => $form->createView()]);
    }
}
