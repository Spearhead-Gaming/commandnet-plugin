<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Calendar\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Forumify\Calendar\Entity\CalendarEvent;
use Forumify\Calendar\Repository\CalendarEventRepository;
use MajesticDev\CommandNet\Entity\Enum\OperationStatus;
use MajesticDev\CommandNet\Entity\Operation;

/**
 * Mirrors an Operation into a CalendarEvent, the same shape as MILHQ's own
 * Calendar\EventSubscriber\MissionListener for its Mission entity: only registered at all
 * when the calendar plugin is installed (see config/calendar.php, imported conditionally
 * from CommandNetPlugin::loadExtension()), so this file is never even compiled into the
 * container - let alone instantiated - when that plugin is absent.
 */
#[AsEntityListener(Events::postPersist, 'postSave', entity: Operation::class)]
#[AsEntityListener(Events::postUpdate, 'postSave', entity: Operation::class)]
#[AsEntityListener(Events::postRemove, 'postRemove', entity: Operation::class)]
class OperationCalendarListener
{
    public function __construct(
        private readonly CalendarEventRepository $calendarEventRepository,
    ) {
    }

    public function postSave(Operation $operation): void
    {
        $calendar = $operation->getCalendar();
        // Mission has no cancelled-status equivalent, but Operation does - a cancelled
        // operation shouldn't stay on the calendar even if its calendar field is still set.
        if ($calendar === null || $operation->getStatus() === OperationStatus::CANCELLED) {
            $this->postRemove($operation);
            return;
        }

        $event = $operation->getCalendarEvent() ?? new CalendarEvent();
        $event->setCalendar($calendar);
        $event->setTitle($operation->getTitle());
        $event->setStart($operation->getStartDateTime());
        $event->setEnd($operation->getEndDateTime());
        $event->setContent($operation->getContent() ?? '');

        $operation->setCalendarEvent($event);
        $this->calendarEventRepository->save($event);
    }

    public function postRemove(Operation $operation): void
    {
        $event = $operation->getCalendarEvent();
        if ($event !== null) {
            $this->calendarEventRepository->remove($event);
        }
    }
}
