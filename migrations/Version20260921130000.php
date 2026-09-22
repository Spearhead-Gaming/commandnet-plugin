<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Any member may post a patrol, so the patrols.create permission goes on Forumify's built-in
 * "user" role (given to every logged-in user). Staff can still take it away, or move it to a
 * narrower role, in the role admin. Permissions are a comma-separated list on the role.
 */
final class Version20260921130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant the patrols.create permission to all members by default.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE role SET permissions = IF(permissions IS NULL OR permissions = '', 'command-net.patrols.create', CONCAT(permissions, ',command-net.patrols.create'))"
            . " WHERE slug = 'user' AND FIND_IN_SET('command-net.patrols.create', COALESCE(permissions, '')) = 0",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE role SET permissions = NULLIF(TRIM(BOTH ',' FROM REPLACE(CONCAT(',', permissions, ','), ',command-net.patrols.create,', ',')), '')"
            . " WHERE slug = 'user' AND FIND_IN_SET('command-net.patrols.create', COALESCE(permissions, '')) > 0",
        );
    }
}
