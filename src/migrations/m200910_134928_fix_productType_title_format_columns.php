<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\ArrayHelper;

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
            foreach ($projectConfig->get('commerce.productTypes') ?? [] as $uid => $productType) {
                if (in_array($uid, $productTypeUids, false)) {
                    $productType['hasProductTitleField'] = true;
                    $productType['productTitleFormat'] = '';
                    $projectConfig->set("commerce.productTypes.{$uid}", $productType);
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
