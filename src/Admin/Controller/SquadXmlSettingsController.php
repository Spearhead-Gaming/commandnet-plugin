<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Core\Form\UploadType;
use MajesticDev\CommandNet\Controller\SquadXmlController;
use MajesticDev\CommandNet\Service\SquadXmlSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/command-net/squad-xml-settings', 'command_net_squad_xml_settings')]
class SquadXmlSettingsController extends AbstractController
{
    public function __invoke(Request $request, SquadXmlSettings $settings, CacheInterface $cache): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.squadxml.manage');

        $form = $this->createFormBuilder($settings->all())
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Publish squad.xml',
                'help' => 'Serves /squad.xml, /squad.dtd and /logo.paa publicly. It lists every enlisted soldier with a Steam ID, their name, callsign and unit.',
            ])
            ->add('nick', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'Squad tag',
                'help' => 'The short tag shown in game, for example "SHG".',
                'constraints' => [new Assert\Length(max: 50)],
            ])
            ->add('name', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'Squad name',
                'help' => 'Leave empty to use the community title.',
            ])
            ->add('title', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'help' => 'Leave empty to use the squad name.',
            ])
            ->add('web', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'Web address',
                'help' => 'Leave empty to use this site.',
                'constraints' => [new Assert\Url(requireTld: true)],
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'empty_data' => '',
                'help' => 'Leave empty to publish N/A.',
            ])
            ->add('picture', UploadType::class, [
                'label' => 'Logo',
                'required' => false,
                'filesystem' => 'asset.storage',
                'asset_package' => 'forumify.asset',
                'help' => 'Arma expects a .paa texture, served as /logo.paa.',
            ])
            ->getForm()
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->save($form->getData());
            $cache->delete(SquadXmlController::CACHE_KEY);
            $this->addFlash('success', 'Squad XML settings saved.');
            return $this->redirectToRoute('forumify_admin_command_net_squad_xml_settings');
        }

        return $this->render('@CommandNetPlugin/admin/squad_xml_settings.html.twig', ['form' => $form->createView()]);
    }
}
