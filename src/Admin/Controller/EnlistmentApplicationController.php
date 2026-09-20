<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Admin\Form\EnlistmentReviewType;
use MajesticDev\CommandNet\Entity\EnlistmentApplication;
use MajesticDev\CommandNet\Service\EnlistmentService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The edit screen is the review screen; there is no create, since applications come from the
 * public /enlist page.
 *
 * @extends AbstractCrudController<EnlistmentApplication>
 */
#[Route('/command-net/enlistment', 'command_net_enlistment')]
class EnlistmentApplicationController extends AbstractCrudController
{
    protected bool $allowCreate = false;

    protected ?string $permissionView = 'command-net.admin.enlistment.view';
    protected ?string $permissionEdit = 'command-net.admin.enlistment.manage';
    protected ?string $permissionDelete = 'command-net.admin.enlistment.manage';

    public function __construct(private readonly EnlistmentService $enlistmentService)
    {
    }

    protected function getEntityClass(): string
    {
        return EnlistmentApplication::class;
    }

    protected function getTableName(): string
    {
        return 'EnlistmentApplicationTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(EnlistmentReviewType::class, $data, [
            'pending' => $data instanceof EnlistmentApplication && $data->isPending(),
        ]);
    }

    protected function save(bool $isNew, FormInterface $form): object
    {
        /** @var EnlistmentApplication $application */
        $application = $form->getData();
        if (!$application->isPending()) {
            return $application;
        }

        $note = trim((string)$form->get('note')->getData()) ?: null;
        $reviewer = $this->getUser();
        $reviewer = $reviewer instanceof User ? $reviewer : null;

        if ($form->get('decision')->getData() === EnlistmentReviewType::ACCEPT) {
            $this->enlistmentService->accept($application, $reviewer, $note);
        } else {
            $this->enlistmentService->decline($application, $reviewer, $note);
        }

        return $application;
    }
}
