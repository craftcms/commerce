<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m180601_161904_fix_orderLanguage migration.
 */
class m201005_169999_add_orderSiteId extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {

        if (!$this->db->columnExists('{{%commerce_orders}}', 'orderSiteId')) {
            $this->addColumn('{{%commerce_orders}}', 'orderSiteId', $this->integer());
        }

        $this->update('{{%commerce_orders}}', [
            'orderSiteId' => Craft::$app->getSites()->getPrimarySite()->id,
        ], [
            'orderSiteId' => null,
        ], [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180601_161904_fix_orderLanguage cannot be reverted.\n";
        return false;
    }
}
