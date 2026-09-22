<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Enum\AarStatus;
use MajesticDev\CommandNet\Entity\Operation;

/**
 * The default PatrolReminderNotifier binding - see that interface for why this exists.
 */
class NullPatrolReminderNotifier implements PatrolReminderNotifier
{
    public function notify(Operation $patrol, AarStatus $status): void
    {
    }
}
