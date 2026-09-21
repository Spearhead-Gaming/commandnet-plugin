<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use MajesticDev\CommandNet\Entity\Rank;
use MajesticDev\CommandNet\Entity\Unit;
use MajesticDev\CommandNet\Repository\RankRepository;
use MajesticDev\CommandNet\Repository\UnitRepository;
use MajesticDev\CommandNet\Service\EnlistmentSettings;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/command-net/enlistment-settings', 'command_net_enlistment_settings')]
class EnlistmentSettingsController extends AbstractController
{
    public function __invoke(
        Request $request,
        EnlistmentSettings $settings,
        RankRepository $rankRepository,
        UnitRepository $unitRepository,
    ): Response {
        $this->denyAccessUnlessGranted('command-net.admin.enlistment.manage');

        // EntityType needs entity instances as initial data, but the settings store plain ids.
        $data = $settings->all();
        $data['defaultRank'] = $data['defaultRank'] !== null ? $rankRepository->find($data['defaultRank']) : null;
        $data['defaultUnit'] = $data['defaultUnit'] !== null ? $unitRepository->find($data['defaultUnit']) : null;

        $form = $this->createFormBuilder($data)
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Accept applications',
                'help' => 'Shows the /enlist page. Signed-in members with a verified email can apply.',
            ])
            ->add('defaultRank', EntityType::class, [
                'class' => Rank::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'name',
                'label' => 'Starting rank',
                'help' => 'Given to accepted applicants who have no rank yet.',
            ])
            ->add('defaultUnit', EntityType::class, [
                'class' => Unit::class,
                'required' => false,
                'placeholder' => 'None',
                'choice_label' => 'name',
                'label' => 'Starting unit',
                'help' => 'Accepted applicants are posted here as their primary assignment.',
            ])
            ->add('instructions', TextareaType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'Instructions',
                'help' => 'Shown above the application form.',
            ])
            ->getForm()
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $formData['defaultRank'] = $formData['defaultRank']?->getId();
            $formData['defaultUnit'] = $formData['defaultUnit']?->getId();
            $settings->save($formData);
            $this->addFlash('success', 'Enlistment settings saved.');
            return $this->redirectToRoute('forumify_admin_command_net_enlistment_settings');
        }

        return $this->render('@CommandNetPlugin/admin/enlistment_settings.html.twig', ['form' => $form->createView()]);
    }
}
