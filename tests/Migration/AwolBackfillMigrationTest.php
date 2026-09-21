<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Migration;

use CommandNetPluginMigrations\Version20260920140000;
use CommandNetPluginMigrations\Version20260920260000;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Migrations\AbstractMigration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Runs the real SQL of the AWOL backfill migrations against rows shaped like the old code wrote
 * them, in a scratch database of their own. An empty database proves the migration runs; this
 * proves it does the right thing to the data that already exists.
 *
 * Before these migrations the plugin filed AWOL changes as "assignment" records titled "Flagged
 * AWOL" or "Returned to Active", and had no way to tell an AWOL set by detection from one set by
 * an admin. The scratch tables carry only the columns the migrations read.
 */
class AwolBackfillMigrationTest extends TestCase
{
    private Connection $connection;
    private string $database;

    protected function setUp(): void
    {
        if ($this->databaseUrl() === '') {
            $this->markTestSkipped('DATABASE_URL is not set.');
        }

        require_once dirname(__DIR__, 2) . '/migrations/Version20260920140000.php';
        require_once dirname(__DIR__, 2) . '/migrations/Version20260920260000.php';

        $params = $this->serverParams();
        $this->database = 'command_net_backfill_' . bin2hex(random_bytes(4));
        $server = DriverManager::getConnection($params);
        $server->executeStatement('CREATE DATABASE `' . $this->database . '`');
        $server->close();

        $this->connection = DriverManager::getConnection([...$params, 'dbname' => $this->database]);
        $this->connection->executeStatement('CREATE TABLE soldier_profile (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->connection->executeStatement('CREATE TABLE service_record (id INT AUTO_INCREMENT NOT NULL, soldier_id INT NOT NULL, type VARCHAR(20) NOT NULL, date DATE NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection)) {
            return;
        }

        $this->connection->close();
        $server = DriverManager::getConnection($this->serverParams());
        $server->executeStatement('DROP DATABASE IF EXISTS `' . $this->database . '`');
        $server->close();
    }

    public function testTheBackfillRetypesTheOldRecordsAndMarksSoldiersFlaggedByDetection(): void
    {
        $soldiers = $this->seedLegacyData();

        $this->migrate(Version20260920140000::class);

        $this->assertSame(
            [
                'flagged, still AWOL' => 1,
                'admin-set, no history' => 0,
                'flagged, returned, then set AWOL by an admin' => 1, // wrong, put right by the next migration
                'active after a flag and return' => 0,
                'flagged, returned, flagged again' => 1,
                'AWOL with only an unrelated record' => 0,
            ],
            $this->autoFlags($soldiers),
        );
        $this->assertSame('awol', $this->recordType($soldiers['flagged, still AWOL'], 'Flagged AWOL'));
        $this->assertSame('awol', $this->recordType($soldiers['active after a flag and return'], 'Returned to Active'));
        $this->assertSame('assignment', $this->recordType($soldiers['AWOL with only an unrelated record'], 'Assigned to Alpha'), 'A real assignment record keeps its type.');
        $this->assertSame('note', $this->recordType($soldiers['admin-set, no history'], 'Flagged AWOL'), 'Only assignment records are retyped.');
    }

    public function testTheCorrectionKeepsTheMarkOnlyWhereTheLatestEntryIsAFlag(): void
    {
        $soldiers = $this->seedLegacyData();

        $this->migrate(Version20260920140000::class);
        $this->migrate(Version20260920260000::class);

        $this->assertSame(
            [
                'flagged, still AWOL' => 1,
                'admin-set, no history' => 0,
                'flagged, returned, then set AWOL by an admin' => 0,
                'active after a flag and return' => 0,
                'flagged, returned, flagged again' => 1,
                'AWOL with only an unrelated record' => 0,
            ],
            $this->autoFlags($soldiers),
        );
    }

    public function testTheCorrectionLeavesSoldiersFlaggedAfterTheBackfillAlone(): void
    {
        $this->migrate(Version20260920140000::class);
        // What the plugin writes once the backfill has run: the typed record, and the mark set.
        $this->connection->insert('soldier_profile', ['status' => 'awol', 'awol_auto_flagged' => 1]);
        $id = (int)$this->connection->lastInsertId();
        $this->connection->insert('service_record', ['soldier_id' => $id, 'type' => 'awol', 'title' => 'Flagged AWOL', 'date' => '2026-01-01']);

        $this->migrate(Version20260920260000::class);

        $this->assertSame(1, (int)$this->connection->fetchOne('SELECT awol_auto_flagged FROM soldier_profile WHERE id = ?', [$id]));
    }

    public function testRollingTheBackfillBackRestoresTheOldRecordTypes(): void
    {
        $soldiers = $this->seedLegacyData();
        $this->migrate(Version20260920140000::class);

        $this->migrate(Version20260920140000::class, 'down');

        $this->assertSame('assignment', $this->recordType($soldiers['flagged, still AWOL'], 'Flagged AWOL'));
        $columns = array_map(
            static fn (Column $column) => $column->getName(),
            $this->connection->createSchemaManager()->listTableColumns('soldier_profile'),
        );
        $this->assertNotContains('awol_auto_flagged', $columns, 'The column is dropped again.');
    }

    private function databaseUrl(): string
    {
        $url = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

        return is_string($url) ? $url : '';
    }

    /**
     * Connection parameters for the server, without a database, from the same URL the app uses.
     *
     * @return array<string, mixed>
     */
    private function serverParams(): array
    {
        $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($this->databaseUrl());
        unset($params['dbname']);

        return $params;
    }

    /**
     * @return array<string, int> soldier ids by what happened to them
     */
    private function seedLegacyData(): array
    {
        $soldiers = [
            'flagged, still AWOL' => $this->soldier('awol', ['Flagged AWOL']),
            'admin-set, no history' => $this->soldier('awol', []),
            'flagged, returned, then set AWOL by an admin' => $this->soldier('awol', ['Flagged AWOL', 'Returned to Active']),
            'active after a flag and return' => $this->soldier('active', ['Flagged AWOL', 'Returned to Active']),
            'flagged, returned, flagged again' => $this->soldier('awol', ['Flagged AWOL', 'Returned to Active', 'Flagged AWOL']),
            'AWOL with only an unrelated record' => $this->soldier('awol', ['Assigned to Alpha']),
        ];
        // A note that happens to share the title is not an AWOL entry.
        $this->connection->insert('service_record', ['soldier_id' => $soldiers['admin-set, no history'], 'type' => 'note', 'title' => 'Flagged AWOL', 'date' => '2026-01-01']);

        return $soldiers;
    }

    /**
     * @param array<string> $titles records filed as "assignment", the way the old code filed them, oldest first
     */
    private function soldier(string $status, array $titles): int
    {
        $this->connection->insert('soldier_profile', ['status' => $status]);
        $id = (int)$this->connection->lastInsertId();
        foreach ($titles as $title) {
            $this->connection->insert('service_record', ['soldier_id' => $id, 'type' => 'assignment', 'title' => $title, 'date' => '2026-01-01']);
        }

        return $id;
    }

    /**
     * @param array<string, int> $soldiers
     * @return array<string, int>
     */
    private function autoFlags(array $soldiers): array
    {
        return array_map(
            fn (int $id) => (int)$this->connection->fetchOne('SELECT awol_auto_flagged FROM soldier_profile WHERE id = ?', [$id]),
            $soldiers,
        );
    }

    private function recordType(int $soldierId, string $title): string
    {
        return (string)$this->connection->fetchOne('SELECT type FROM service_record WHERE soldier_id = ? AND title = ? ORDER BY id LIMIT 1', [$soldierId, $title]);
    }

    /**
     * @param class-string<AbstractMigration> $migration
     */
    private function migrate(string $migration, string $direction = 'up'): void
    {
        $instance = new $migration($this->connection, new NullLogger());
        $instance->$direction(new Schema());
        foreach ($instance->getSql() as $query) {
            $this->connection->executeStatement($query->getStatement());
        }
    }
}
