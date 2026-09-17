<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Forumify\Core\Event\EntityPostRemoveEvent;
use Forumify\Core\Event\EntityPostSaveEvent;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Operation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mirrors an Operation into a Forumify\Calendar\Entity\CalendarEvent on save, and removes
 * that mirror when the operation is cancelled or deleted - so operations show up on the
 * community calendar with no calendar-specific code anywhere else in this plugin.
 *
 * Entirely optional: the calendar classes are resolved by string name and guarded with
 * class_exists(), the same shape MilhqCardProvider/CommandNetCardProvider already use in
 * the sibling id-card plugin for an optional dependency, so this plugin never hard-requires
 * forumify-calendar-plugin - if it isn't installed, every method here is a no-op.
 */
class OperationCalendarSyncer implements EventSubscriberInterface
{
    private const CALENDAR_EVENT = 'Forumify\\Calendar\\Entity\\CalendarEvent';
    private const CALENDAR = 'Forumify\\Calendar\\Entity\\Calendar';

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityPostSaveEvent::getName(Operation::class) => 'onSave',
            EntityPostRemoveEvent::getName(Operation::class) => 'onRemove',
        ];
    }

    public function onSave(EntityPostSaveEvent $event): void
    {
        $operation = $event->getEntity();
        if (!$this->isAvailable() || !$operation instanceof Operation) {
            return;
        }

        if ($operation->getStatus() === OperationStatus::CANCELLED) {
            $this->removeCalendarEvent($operation);
            return;
        }

        $calendarEventClass = self::CALENDAR_EVENT;
        $calendarEvent = $operation->getCalendarEventId() !== null
            ? $this->registry->getRepository($calendarEventClass)->find($operation->getCalendarEventId())
            : null;

        $isNew = $calendarEvent === null;
        if ($isNew) {
            $calendar = $this->registry->getRepository(self::CALENDAR)->findOneBy([]);
            if ($calendar === null) {
                // No calendar exists yet for operations to sync into - nothing to do until
                // an admin creates one in the Calendar plugin.
                return;
            }

            $calendarEvent = new $calendarEventClass();
            $calendarEvent->setCalendar($calendar);
        }

        $calendarEvent->setTitle($operation->getTitle());
        $calendarEvent->setStart($operation->getStartDateTime());
        $calendarEvent->setEnd($operation->getEndDateTime());
        $calendarEvent->setContent($operation->getContent() ?? '');

        $this->entityManager->persist($calendarEvent);
        $this->entityManager->flush();

        if ($isNew) {
            // Setting a field and flushing directly (not repository->save(), which would
            // re-dispatch EntityPostSaveEvent for Operation and recurse into this listener).
            $operation->setCalendarEventId($calendarEvent->getId());
            $this->entityManager->flush();
        }
    }

    public function onRemove(EntityPostRemoveEvent $event): void
    {
        $operation = $event->getEntity();
        if ($this->isAvailable() && $operation instanceof Operation) {
            $this->removeCalendarEvent($operation);
        }
    }

    private function isAvailable(): bool
    {
        return class_exists(self::CALENDAR_EVENT) && $this->registry->getManagerForClass(self::CALENDAR_EVENT) !== null;
    }

    private function removeCalendarEvent(Operation $operation): void
    {
        if ($operation->getCalendarEventId() === null) {
            return;
        }

        $calendarEvent = $this->registry->getRepository(self::CALENDAR_EVENT)->find($operation->getCalendarEventId());
        if ($calendarEvent !== null) {
            $this->entityManager->remove($calendarEvent);
            $this->entityManager->flush();
        }
    }
}
