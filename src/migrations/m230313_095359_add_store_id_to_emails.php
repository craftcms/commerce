<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230313_095359_add_store_id_to_emails migration.
 */
class m230313_095359_add_store_id_to_emails extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::EMAILS, 'storeId', $this->integer());

        $primaryStore = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->one();

        $this->update(Table::EMAILS, ['storeId' => $primaryStore['id']], ['storeId' => null], [], false);

        $this->addForeignKey(null, Table::EMAILS, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::EMAILS, ['storeId'], false);

        $projectConfig = Craft::$app->getProjectConfig();

        $emails = $projectConfig->get('commerce.emails') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($emails as $emailUid => $email) {
            $email['store'] = $primaryStore['uid'];
            $projectConfig->set("commerce.emails.$emailUid", $email);
        }

        $projectConfig->muteEvents = $muteEvents;


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230313_095359_add_store_id_to_emails cannot be reverted.\n";
        return false;
    }
}
