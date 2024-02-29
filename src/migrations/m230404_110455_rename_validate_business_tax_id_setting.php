<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230404_110455_rename_validate_business_tax_id_setting migration.
 */
class m230404_110455_rename_validate_business_tax_id_setting extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->renameColumn(Table::STORES, 'validateBusinessTaxIdAsVatId', 'validateOrganizationTaxIdAsVatId');

        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);

        if (version_compare($schemaVersion, '5.0.38', '<')) {
            $stores = $projectConfig->get('commerce.stores') ?? [];
            $muteEvents = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;

            foreach ($stores as $uid => $store) {
                if (!array_key_exists('validateBusinessTaxIdAsVatId', $store)) {
                    continue;
                }
                $store['validateOrganizationTaxIdAsVatId'] = $store['validateBusinessTaxIdAsVatId'];
                unset($store['validateBusinessTaxIdAsVatId']);
                $projectConfig->set("commerce.stores.$uid", $store);
            }

            $projectConfig->muteEvents = $muteEvents;
        }


        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230404_110455_rename_validate_business_tax_id_setting cannot be reverted.\n";
        return false;
    }
}
