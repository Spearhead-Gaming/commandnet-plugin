<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Twig;

use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Forumify\Core\Entity\User;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Mirrors the 5-minute activity window Forumify\Admin\OnlineUsers uses, so the
 * count shown outside that live component always agrees with it.
 */
class CommandNetRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getOnlineCount(): int
    {
        $min = (new DateTime())->sub(new DateInterval('PT5M'));

        return (int)$this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.lastActivity > :min')
            ->setParameter('min', $min)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
