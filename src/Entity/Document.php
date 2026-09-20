<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\DocumentRepository;

/**
 * A reusable rich-text template with {placeholders}, such as an award citation or promotion
 * order. Attach one to a service record when issuing an award, a qualification or an
 * assignment, and the personnel file shows it filled in for that soldier and record
 * (see DocumentRenderer).
 */
#[ORM\Entity(DocumentRepository::class)]
class Document
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
