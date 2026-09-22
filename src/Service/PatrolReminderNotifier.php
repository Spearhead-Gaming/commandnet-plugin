<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use MajesticDev\CommandNet\Entity\Enum\AarStatus;
use MajesticDev\CommandNet\Entity\Operation;

/**
 * A second channel for the AAR due/overdue reminder PatrolAarReminders already sends as a
 * forum notification. This plugin has no Discord code of its own (that's the optional
 * Discord plugin's job), so it only declares the interface and binds it to a no-op by
 * default; the Discord plugin decorates this service id when it's installed, the same way
 * BlameActorProvider decorates stof_doctrine_extensions.tool.actor_provider.
 */
interface PatrolReminderNotifier
{
    /** $status is always AarStatus::DUE or AarStatus::OVERDUE. */
    public function notify(Operation $patrol, AarStatus $status): void;
}
