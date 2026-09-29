<?php

declare(strict_types=1);

use Forumify\Testing\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::boot();

// The private Discord plugin isn't installable in CI; use stand-ins so the Discord commands load.
if (!interface_exists(MajesticDev\Discord\Discord\DiscordCommandInterface::class)) {
    require __DIR__ . '/PhpstanStubs/MajesticDevDiscord.php';
}
