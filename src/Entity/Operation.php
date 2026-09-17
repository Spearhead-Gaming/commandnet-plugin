<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Calendar\Entity\Calendar;
use Forumify\Calendar\Entity\CalendarEvent;
use MajesticDev\CommandNet\Repository\OperationRepository;

/**
 * A scheduled operation, training, or meeting. The "content" field is the OPORD/WARNORD
 * body, written with the same rich text editor used for forum posts.
 *
 * Declared twice, like MILHQ's Mission entity, so the calendar/calendarEvent relations only
 * exist when the optional calendar plugin is actually installed - a Doctrine mapping can't
 * reference a class that might not exist at all. See OperationCalendarListener.
 */
if (class_exists(\Forumify\Calendar\ForumifyCalendarPlugin::class)) {
    #[ORM\Entity(OperationRepository::class)]
    class Operation
    {
        use OperationFields;

        #[ORM\ManyToOne(targetEntity: Calendar::class)]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?Calendar $calendar = null;

        #[ORM\OneToOne(targetEntity: CalendarEvent::class)]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?CalendarEvent $calendarEvent = null;

        public function getCalendar(): ?Calendar
        {
            return $this->calendar;
        }

        public function setCalendar(?Calendar $calendar): void
        {
            $this->calendar = $calendar;
        }

        public function getCalendarEvent(): ?CalendarEvent
        {
            return $this->calendarEvent;
        }

        public function setCalendarEvent(?CalendarEvent $calendarEvent): void
        {
            $this->calendarEvent = $calendarEvent;
        }
    }
} else {
    #[ORM\Entity(OperationRepository::class)]
    // phpcs:ignore
    class Operation
    {
        use OperationFields;
    }
}
