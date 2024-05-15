<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230317_102521_add_store_id_to_pdfs migration.
 */
class m230317_102521_add_store_id_to_pdfs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::PDFS, 'storeId', $this->integer());

        $primaryStore = (new Query())
            ->select(['id', 'uid'])
            ->from(Table::STORES)
            ->where(['primary' => true])
            ->one();

        $this->update(Table::PDFS, ['storeId' => $primaryStore['id']], ['storeId' => null], [], false);

        $this->addForeignKey(null, Table::PDFS, ['storeId'], Table::STORES, ['id'], 'CASCADE', null);
        $this->createIndex(null, Table::PDFS, ['storeId'], false);

        $projectConfig = Craft::$app->getProjectConfig();

        $pdfs = $projectConfig->get('commerce.pdfs') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($pdfs as $uid => $pdf) {
            $pdf['store'] = $primaryStore['uid'];
            $projectConfig->set("commerce.pdfs.$uid", $pdf);
        }

        $projectConfig->muteEvents = $muteEvents;


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230317_102521_add_store_id_to_pdfs cannot be reverted.\n";
        return false;
    }
}
