<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use MajesticDev\CommandNet\Service\ReportInSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\{CheckboxType, IntegerType};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/command-net/report-in-settings', 'command_net_report_in_settings')]
class ReportInSettingsController extends AbstractController
{
    public function __invoke(Request $request, ReportInSettings $settings): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.reportin.manage');

        $form = $this->createFormBuilder($settings->all())
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'Enforce Report In',
                'help' => 'A daily check flags active soldiers AWOL when they have not reported in within the period, and restores Active when they report in again. The AWOL role from AWOL Settings is used.',
            ])
            ->add('periodDays', IntegerType::class, [
                'label' => 'Days allowed between report ins',
                'constraints' => [new Assert\Range(min: 1, max: 365)],
            ])
            ->add('warningDays', IntegerType::class, [
                'label' => 'Warn this many days before the deadline',
                'help' => '0 disables warnings.',
                'constraints' => [new Assert\Range(min: 0, max: 365)],
            ])
            ->getForm()
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->save($form->getData());
            $this->addFlash('success', 'Report In settings saved.');
            return $this->redirectToRoute('forumify_admin_command_net_report_in_settings');
        }

        return $this->render('@CommandNetPlugin/admin/report_in_settings.html.twig', ['form' => $form->createView()]);
    }
}
