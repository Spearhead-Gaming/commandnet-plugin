<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\TimestampableEntityTrait;
use Forumify\Core\Entity\User;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Repository\FormSubmissionRepository;

/**
 * One filled-in form. The answers are stored as a snapshot of the question label and the
 * answer as text, so editing or deleting the form later never changes what was submitted.
 * The status reuses ApplicationStatus: pending until a reviewer accepts or declines it.
 */
#[ORM\Entity(FormSubmissionRepository::class)]
class FormSubmission
{
    use IdentifiableEntityTrait;
    use TimestampableEntityTrait;

    #[ORM\ManyToOne(targetEntity: FormDefinition::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?FormDefinition $form;

    #[ORM\Column(length: 150)]
    private string $formName;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * @var array<int, array{label: string, value: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $answers = [];

    #[ORM\Column(length: 20, enumType: ApplicationStatus::class)]
    private ApplicationStatus $status = ApplicationStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $reviewedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $decisionNote = null;

    /**
     * @param array<int, array{label: string, value: string}> $answers
     */
    public function __construct(FormDefinition $form, User $user, array $answers)
    {
        $this->form = $form;
        $this->formName = $form->getName();
        $this->user = $user;
        $this->answers = $answers;
    }

    public function getForm(): ?FormDefinition
    {
        return $this->form;
    }

    public function getFormName(): string
    {
        return $this->formName;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::PENDING;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function getReviewedAt(): ?DateTime
    {
        return $this->reviewedAt;
    }

    public function getDecisionNote(): ?string
    {
        return $this->decisionNote;
    }

    public function decide(ApplicationStatus $status, ?User $reviewer, ?string $note): void
    {
        $this->status = $status;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = new DateTime();
        $this->decisionNote = $note;
    }

    /**
     * The answers as plain text, one question per line, for showing to a reviewer.
     */
    public function getAnswersAsText(): string
    {
        return implode("\n", array_map(
            static fn (array $answer): string => $answer['label'] . ': ' . $answer['value'],
            $this->answers,
        ));
    }
}
