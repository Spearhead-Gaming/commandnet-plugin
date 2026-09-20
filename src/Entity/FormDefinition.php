<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use MajesticDev\CommandNet\Repository\FormDefinitionRepository;

/**
 * A form members can fill in, such as a leave request or a transfer request. The fields are
 * kept as text, one per line, and parsed by FormSchema; see that class for the format.
 */
#[ORM\Entity(FormDefinitionRepository::class)]
class FormDefinition
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * One field per line, see FormSchema.
     */
    #[ORM\Column(type: 'text')]
    private string $fieldList = '';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

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

    public function getFieldList(): string
    {
        return $this->fieldList;
    }

    public function setFieldList(string $fieldList): void
    {
        $this->fieldList = $fieldList;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
