<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m200910_134928_fix_productType_title_format_columns migration.
 */
class m200910_134928_fix_productType_title_format_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $productTypeUids = (new Query())
            ->select(['uid'])
            ->from('{{%commerce_producttypes}}')
            ->where(['hasProductTitleField' => null])
            ->column();

        if (!empty($productTypeUids)) {
            $this->update('{{%commerce_producttypes}}', ['hasProductTitleField' => true], ['uid' => $productTypeUids]);

            $projectConfig = \Craft::$app->getProjectConfig();
            $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
            if (version_compare($schemaVersion, '3.2.6', '<')) {
                foreach ($projectConfig->get('commerce.productTypes') ?? [] as $uid => $productType) {
                    if (in_array($uid, $productTypeUids, false)) {
                        $productType['hasProductTitleField'] = true;
                        $productType['productTitleFormat'] = '';
                        $projectConfig->set("commerce.productTypes.{$uid}", $productType);
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200910_134928_fix_productType_title_format_columns cannot be reverted.\n";
        return false;
    }
}
