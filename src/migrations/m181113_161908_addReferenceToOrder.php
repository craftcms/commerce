<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use yii\db\Expression;

/**
 * m181113_161908_addReferenceToOrder migration.
 */
class m181113_161908_addReferenceToOrder extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableName = '{{%commerce_orders}}';
        $this->addColumn($tableName, 'reference', $this->string());
        $this->createIndex(null, $tableName, 'reference', false); // unique constraint validated in application logic

        // default the reference to the short order number to match the default order reference format setting
        Craft::$app->getDb()->createCommand()->update($tableName, ['reference' => new Expression('LEFT([[number]], 7)')], '[[isCompleted]] = true')->execute();
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181113_161908_addReferenceToOrder cannot be reverted.\n";
        return false;
    }
}
