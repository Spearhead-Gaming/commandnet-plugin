<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Core\Entity\Role;
use Forumify\Core\Repository\RoleRepository;
use MajesticDev\CommandNet\Service\AwolSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, IntegerType};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/command-net/awol-settings', 'command_net_awol_settings')]
class AwolSettingsController extends AbstractController
{
    public function __invoke(Request $request, AwolSettings $settings, RoleRepository $roleRepository): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.awol.manage');

        // EntityType needs an actual Role instance as its initial data, but the setting
        // stores a plain role id - convert both directions around the plain form array.
        $data = $settings->all();
        $data['role'] = $data['role'] !== null ? $roleRepository->find($data['role']) : null;

        $form = $this->createFormBuilder($data)
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Enable AWOL detection',
                'help' => 'Automatically flags a soldier AWOL after too many consecutive missed operations, and reverts them to Active the next time they attend one.',
            ])
            ->add('missThreshold', IntegerType::class, [
                'label' => 'Consecutive missed operations before AWOL',
                'constraints' => [new Assert\Range(min: 1, max: 20)],
            ])
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'title',
                'label' => 'AWOL role',
                'help' => 'Granted while a soldier is flagged AWOL, revoked once they return. Map it to a Discord role in the Discord plugin\'s own settings to sync Discord automatically.',
            ])
            ->getForm()
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $formData['role'] = $formData['role']?->getId();
            $settings->save($formData);
            $this->addFlash('success', 'AWOL settings saved.');
            return $this->redirectToRoute('forumify_admin_command_net_awol_settings');
        }

        return $this->render('@CommandNetPlugin/admin/awol_settings.html.twig', ['form' => $form->createView()]);
    }
}
