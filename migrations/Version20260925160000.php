<?php

declare(strict_types=1);

namespace CommandNetPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The fields of the community's AAR template (tasking, callsigns, casualties) and the map and
 * intel images a patrol's AAR must include. All nullable: existing reports, and reports for events
 * that are not patrols, do not have them.
 */
final class Version20260925160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the AAR template fields and the map and intel images to operation_aar.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation_aar ADD tasking VARCHAR(255) DEFAULT NULL, ADD callsigns LONGTEXT DEFAULT NULL, ADD friendly_casualties VARCHAR(100) DEFAULT NULL, ADD enemy_kia VARCHAR(100) DEFAULT NULL, ADD map_images JSON DEFAULT NULL, ADD intel_images JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE operation_aar DROP tasking, DROP callsigns, DROP friendly_casualties, DROP enemy_kia, DROP map_images, DROP intel_images');
    }
}
