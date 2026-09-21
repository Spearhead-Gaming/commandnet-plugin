<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use DateTime;
use DomainException;
use MajesticDev\CommandNet\Entity\Enum\DischargeKind;
use MajesticDev\CommandNet\Entity\SoldierProfile;
use MajesticDev\CommandNet\Service\DischargeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/command-net/personnel/{id}/discharge', 'command_net_personnel_discharge', requirements: ['id' => '\d+'])]
class DischargeController extends AbstractController
{
    public function __invoke(Request $request, SoldierProfile $soldier, DischargeService $dischargeService): Response
    {
        $this->denyAccessUnlessGranted('command-net.admin.personnel.discharge');

        if (!$soldier->isEnlisted()) {
            $this->addFlash('error', 'This soldier has already been discharged.');
            return $this->redirectToRoute('forumify_admin_command_net_personnel_list');
        }

        $form = $this->createFormBuilder(['kind' => DischargeKind::GENERAL, 'date' => new DateTime('today')])
            ->add('kind', ChoiceType::class, [
                'label' => 'Type',
                'choices' => DischargeKind::cases(),
                'choice_label' => fn (DischargeKind $kind) => $kind->label(),
                'choice_value' => fn (?DischargeKind $kind) => $kind?->value,
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Effective date',
            ])
            ->add('reason', TextareaType::class, [
                'required' => false,
                'label' => 'Reason',
                'help' => 'Optional. Shown in the soldier\'s service record.',
            ])
            ->getForm()
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{kind: DischargeKind, date: DateTime, reason: ?string} $data */
            $data = $form->getData();
            $reason = trim((string)$data['reason']);

            try {
                $dischargeService->discharge($soldier, $data['kind'], $reason !== '' ? $reason : null, $data['date']);
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('forumify_admin_command_net_personnel_list');
            }

            $this->addFlash('success', $soldier->getUser()->getDisplayName() . ' has been discharged.');
            return $this->redirectToRoute('forumify_admin_command_net_personnel_list');
        }

        return $this->render('@CommandNetPlugin/admin/discharge/discharge.html.twig', [
            'form' => $form->createView(),
            'soldier' => $soldier,
        ]);
    }
}
