<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CommandNetExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('command_net_online_count', [CommandNetRuntime::class, 'getOnlineCount']),
        ];
    }
}
