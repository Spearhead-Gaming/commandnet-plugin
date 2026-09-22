<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use MajesticDev\CommandNet\Entity\Deployment;
use MajesticDev\CommandNet\Entity\Enum\OperationType;
use MajesticDev\CommandNet\Entity\Operation;
use MajesticDev\CommandNet\Repository\OperationRepository;

/**
 * Creates the recurring Operations for a Deployment: every Wednesday and Saturday at 2000 US
 * Eastern between its start and end dates (inclusive). Running it twice does not duplicate:
 * a slot that already has an Operation in this Deployment is skipped.
 *
 * The times are built in America/New_York, so they follow daylight saving, then converted to
 * PHP's default timezone, which is what Operation start times are stored in.
 */
class DeploymentOperationGenerator
{
    private const string TIMEZONE = 'America/New_York';
    private const string START_TIME = '20:00';
    private const int DURATION_HOURS = 3;
    /** ISO weekdays: Wednesday and Saturday. */
    private const array WEEKDAYS = [3, 6];

    public function __construct(private readonly OperationRepository $operationRepository)
    {
    }

    /**
     * Every slot the Deployment's dates cover, in order.
     *
     * @return list<DateTimeImmutable>
     */
    public function slots(Deployment $deployment): array
    {
        $eastern = new DateTimeZone(self::TIMEZONE);
        $local = new DateTimeZone(date_default_timezone_get());
        $day = DateTimeImmutable::createFromInterface($deployment->getStartDate())->setTime(0, 0);
        $last = DateTimeImmutable::createFromInterface($deployment->getEndDate())->setTime(0, 0);

        $slots = [];
        for (; $day <= $last; $day = $day->add(new DateInterval('P1D'))) {
            if (!in_array((int)$day->format('N'), self::WEEKDAYS, true)) {
                continue;
            }
            $slots[] = (new DateTimeImmutable($day->format('Y-m-d') . ' ' . self::START_TIME, $eastern))
                ->setTimezone($local);
        }

        return $slots;
    }

    /**
     * Slots with no Operation in this Deployment yet, as unsaved Operations.
     *
     * @return list<Operation>
     */
    public function build(Deployment $deployment): array
    {
        $existing = [];
        foreach ($this->operationRepository->findBy(['deployment' => $deployment, 'type' => OperationType::OPERATION]) as $operation) {
            $existing[$operation->getStartDateTime()->format('Y-m-d H:i')] = true;
        }

        $eastern = new DateTimeZone(self::TIMEZONE);
        $operations = [];
        foreach ($this->slots($deployment) as $slot) {
            if (isset($existing[$slot->format('Y-m-d H:i')])) {
                continue;
            }

            $operation = new Operation();
            $operation->setTitle(sprintf('%s - %s', $deployment->getName(), $slot->setTimezone($eastern)->format('l M j')));
            $operation->setType(OperationType::OPERATION);
            $operation->setDeployment($deployment);
            $operation->setStartDateTime(DateTime::createFromImmutable($slot));
            $operation->setEndDateTime(DateTime::createFromImmutable($slot->add(new DateInterval('PT' . self::DURATION_HOURS . 'H'))));
            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * @return int how many Operations were created
     */
    public function generate(Deployment $deployment): int
    {
        $operations = $this->build($deployment);
        foreach ($operations as $operation) {
            $this->operationRepository->save($operation, false);
        }
        $this->operationRepository->flush();

        return count($operations);
    }
}
