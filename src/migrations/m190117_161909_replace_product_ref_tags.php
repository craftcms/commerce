<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\queue\jobs\FindAndReplace;

/**
 * m190117_161909_replace_product_ref_tags migration.
 */
class m190117_161909_replace_product_ref_tags extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        Craft::$app->getQueue()->push(new FindAndReplace([
            'find' => '{commerce_product:',
            'replace' => '{product:',
        ]));

        Craft::$app->getQueue()->push(new FindAndReplace([
            'find' => '{commerce_variant:',
            'replace' => '{variant:',
        ]));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190117_161909_replace_product_ref_tags cannot be reverted.\n";
        return false;
    }
}
