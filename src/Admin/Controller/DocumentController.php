<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Admin\Controller;

use Forumify\Admin\Crud\AbstractCrudController;
use MajesticDev\CommandNet\Admin\Form\DocumentType;
use MajesticDev\CommandNet\Entity\Document;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends AbstractCrudController<Document>
 */
#[Route('/command-net/documents', 'command_net_documents')]
class DocumentController extends AbstractCrudController
{
    protected ?string $permissionView = 'command-net.admin.documents.view';
    protected ?string $permissionCreate = 'command-net.admin.documents.manage';
    protected ?string $permissionEdit = 'command-net.admin.documents.manage';
    protected ?string $permissionDelete = 'command-net.admin.documents.manage';

    protected function getEntityClass(): string
    {
        return Document::class;
    }

    protected function getTableName(): string
    {
        return 'DocumentTable';
    }

    protected function getForm(?object $data): FormInterface
    {
        return $this->createForm(DocumentType::class, $data);
    }
}
