<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table as CraftTable;

/**
 * m250617_105249_add_email_render_site_id migration.
 */
class m250617_105249_add_email_render_site_id extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if ($this->db->columnExists(Table::EMAILS, 'renderSiteId')) {
            return true;
        }

        $this->addColumn(Table::EMAILS, 'renderSiteId', $this->integer()->after('language'));

        // Get the primary site ID
        $primarySite = Craft::$app->getSites()->getPrimarySite();

        // For all current emails set the `renderSiteId` to the primary site to keep the existing behavior
        $this->db->createCommand()->update(
            Table::EMAILS,
            ['renderSiteId' => $primarySite->id],
            ['renderSiteId' => null]
        )->execute();

        // Update the project config
        $projectConfig = Craft::$app->getProjectConfig();

        $emails = $projectConfig->get('commerce.emails') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($emails as $emailUid => $email) {
            $email['renderSite'] = $primarySite->uid;
            $projectConfig->set("commerce.emails.$emailUid", $email);
        }

        $projectConfig->muteEvents = $muteEvents;

        // Add foreign key
        $this->addForeignKey(null, Table::EMAILS, ['renderSiteId'], CraftTable::SITES, ['id'], 'SET NULL', 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250617_105249_add_email_render_site_id cannot be reverted.\n";
        return false;
    }
}
