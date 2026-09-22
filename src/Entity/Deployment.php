<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use MajesticDev\CommandNet\Repository\DeploymentRepository;

/**
 * A named period, normally a month, that events belong to. Events point at it optionally, so
 * trainings between deployments simply belong to none.
 */
#[ORM\Entity(DeploymentRepository::class)]
class Deployment
{
    use IdentifiableEntityTrait;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'date')]
    private DateTime $startDate;

    #[ORM\Column(type: 'date')]
    private DateTime $endDate;

    public function __construct()
    {
        $this->startDate = new DateTime('today');
        $this->endDate = new DateTime('today');
    }

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

    public function getStartDate(): DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
