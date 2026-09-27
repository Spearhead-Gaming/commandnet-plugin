<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Unit\Service;

use MajesticDev\CommandNet\Entity\Deployment;
use MajesticDev\CommandNet\Repository\DeploymentRepository;
use MajesticDev\CommandNet\Service\DeploymentLookup;
use PHPUnit\Framework\TestCase;

class DeploymentLookupTest extends TestCase
{
    private function deployment(string $name): Deployment
    {
        $deployment = new Deployment();
        $deployment->setName($name);
        return $deployment;
    }

    /**
     * @param list<Deployment> $deployments newest first, as the repository returns them
     */
    private function lookup(array $deployments): DeploymentLookup
    {
        $repository = $this->createStub(DeploymentRepository::class);
        $repository->method('findBy')->willReturnCallback(
            static fn (array $criteria, ?array $orderBy = null, ?int $limit = null) => array_slice($deployments, 0, $limit),
        );

        return new DeploymentLookup($repository);
    }

    public function testFindsADeploymentByItsNameIgnoringCaseAndSpaces(): void
    {
        $october = $this->deployment('Operation October');
        $lookup = $this->lookup([$october, $this->deployment('Operation September')]);

        self::assertSame($october, $lookup->findByName('  operation OCTOBER '));
    }

    public function testAnUnknownNameFindsNothing(): void
    {
        $lookup = $this->lookup([$this->deployment('Operation October')]);

        self::assertNull($lookup->findByName('Operation November'));
    }

    public function testABlankNameMeansNoDeploymentNotAMatch(): void
    {
        $lookup = $this->lookup([$this->deployment('')]);

        self::assertNull($lookup->findByName(''));
        self::assertNull($lookup->findByName('   '));
    }

    public function testRecentNamesAreNewestFirstAndLimited(): void
    {
        $lookup = $this->lookup([
            $this->deployment('C'),
            $this->deployment('B'),
            $this->deployment('A'),
        ]);

        self::assertSame(['C', 'B'], $lookup->recentNames(2));
        self::assertSame([], $this->lookup([])->recentNames());
    }
}
