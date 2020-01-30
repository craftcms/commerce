<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;

/**
 * m190222_161405_permissions_to_uid migration.
 */
class m190222_161405_permissions_to_uid extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $permissions = (new Query())
            ->select(['id', 'name'])
            ->from([Table::USERPERMISSIONS])
            ->pairs();

        $productTypeMap = (new Query())
            ->select(['id', 'uid'])
            ->from('{{%commerce_producttypes}}')
            ->pairs();

        $relations = [
            'commerce-manageProductType' => $productTypeMap,
        ];

        foreach ($permissions as $id => $permission) {
            if (
                preg_match('/([\w]+)(:|-)([\d]+)/i', $permission, $matches) &&
                array_key_exists(strtolower($matches[1]), $relations) &&
                !empty($relations[strtolower($matches[1])][$matches[3]])
            ) {
                $permission = $matches[1] . $matches[2] . $relations[strtolower($matches[1])][$matches[3]];
                $this->update(Table::USERPERMISSIONS, ['name' => $permission], ['id' => $id]);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190222_161405_permissions_to_uid cannot be reverted.\n";

        return false;
    }
}
