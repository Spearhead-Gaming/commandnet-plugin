<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateTimeInterface;
use DomainException;
use Forumify\Core\Entity\Notification;
use Forumify\Core\Entity\User;
use Forumify\Core\Notification\GenericNotificationType;
use Forumify\Core\Notification\NotificationService;
use MajesticDev\CommandNet\Entity\Enum\ApplicationStatus;
use MajesticDev\CommandNet\Entity\Enum\FormFieldType;
use MajesticDev\CommandNet\Entity\FormDefinition;
use MajesticDev\CommandNet\Entity\FormSubmission;
use MajesticDev\CommandNet\Repository\FormSubmissionRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filling in and reviewing forms. Answers are saved as text next to the question label, so a
 * form can be edited later without changing what people already submitted.
 */
class FormSubmissionService
{
    public function __construct(
        private readonly FormSchema $schema,
        private readonly FormSubmissionRepository $submissionRepository,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Adds one input per field to a Symfony form builder, named by the field key.
     *
     * @param array<FormFieldDefinition> $fields
     */
    public function addFields(FormBuilderInterface $builder, array $fields): void
    {
        foreach ($fields as $field) {
            $constraints = $field->required && $field->type !== FormFieldType::BOOLEAN ? [new Assert\NotBlank()] : [];

            match ($field->type) {
                FormFieldType::TEXT => $builder->add($field->key, TextType::class, [
                    'label' => $field->label,
                    'required' => $field->required,
                    'constraints' => [...$constraints, new Assert\Length(max: 255)],
                ]),
                FormFieldType::TEXTAREA => $builder->add($field->key, TextareaType::class, [
                    'label' => $field->label,
                    'required' => $field->required,
                    'constraints' => [...$constraints, new Assert\Length(max: 5000)],
                ]),
                FormFieldType::NUMBER => $builder->add($field->key, NumberType::class, [
                    'label' => $field->label,
                    'required' => $field->required,
                    'constraints' => $constraints,
                ]),
                FormFieldType::BOOLEAN => $builder->add($field->key, CheckboxType::class, [
                    'label' => $field->label,
                    'required' => false,
                    'constraints' => $field->required ? [new Assert\IsTrue(message: 'This box must be ticked.')] : [],
                ]),
                FormFieldType::DATE => $builder->add($field->key, DateType::class, [
                    'label' => $field->label,
                    'required' => $field->required,
                    'widget' => 'single_text',
                    'constraints' => $constraints,
                ]),
                FormFieldType::SELECT => $builder->add($field->key, ChoiceType::class, [
                    'label' => $field->label,
                    'required' => $field->required,
                    'placeholder' => $field->required ? false : 'None',
                    'choices' => array_combine($field->options, $field->options),
                    'constraints' => $constraints,
                ]),
            };
        }
    }

    /**
     * @param array<FormFieldDefinition> $fields
     * @param array<string, mixed> $data submitted values by field key
     * @return array<int, array{label: string, value: string}>
     */
    public function snapshot(array $fields, array $data): array
    {
        $answers = [];
        foreach ($fields as $field) {
            $answers[] = ['label' => $field->label, 'value' => $this->format($data[$field->key] ?? null)];
        }

        return $answers;
    }

    /**
     * @param array<string, mixed> $data submitted values by field key
     */
    public function submit(FormDefinition $form, User $user, array $data): FormSubmission
    {
        if (!$form->isEnabled()) {
            throw new DomainException('This form is not open.');
        }

        $submission = new FormSubmission($form, $user, $this->snapshot($this->schema->parse($form->getFieldList()), $data));
        $this->submissionRepository->save($submission);

        return $submission;
    }

    public function decide(FormSubmission $submission, bool $accept, ?User $reviewer, ?string $note): void
    {
        if (!$submission->isPending()) {
            throw new DomainException('This submission has already been reviewed.');
        }

        $submission->decide($accept ? ApplicationStatus::ACCEPTED : ApplicationStatus::DECLINED, $reviewer, $note);
        $this->submissionRepository->save($submission);

        $this->notificationService->sendNotification(new Notification(
            GenericNotificationType::TYPE,
            $submission->getUser(),
            [
                'title' => $submission->getFormName() . ($accept ? ': accepted' : ': declined'),
                'description' => 'Your submission was ' . ($accept ? 'accepted.' : 'declined.') . ($note ? " $note" : ''),
                'url' => $this->urlGenerator->generate('command_net_forms'),
            ],
        ));
    }

    private function format(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_scalar($value) => (string)$value,
            default => '',
        };
    }
}
