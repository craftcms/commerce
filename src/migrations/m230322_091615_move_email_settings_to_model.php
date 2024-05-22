<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230322_091615_move_email_settings_to_model migration.
 */
class m230322_091615_move_email_settings_to_model extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::EMAILS, 'senderName', $this->string()->after('name'));
        $this->addColumn(Table::EMAILS, 'senderAddress', $this->string()->after('name'));

        $commerceConfig = Craft::$app->getConfig()->getConfigFromFile('commerce');

        if (empty($commerceConfig)) {
            return true;
        }

        $senderAddress = $commerceConfig['emailSenderAddress'] ?? null;
        $senderName = $commerceConfig['emailSenderName'] ?? null;

        $this->update(Table::EMAILS, [
            'senderAddress' => $senderAddress,
            'senderName' => $senderName,
        ]);

        $projectConfig = Craft::$app->getProjectConfig();

        $emails = $projectConfig->get('commerce.emails') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($emails as $uid => $email) {
            $email['senderAddress'] = $senderAddress;
            $email['senderName'] = $senderName;
            $projectConfig->set("commerce.emails.$uid", $email);
        }

        $projectConfig->muteEvents = $muteEvents;

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230322_091615_move_email_settings_to_model cannot be reverted.\n";
        return false;
    }
}
