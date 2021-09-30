<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use yii\base\NotSupportedException;
use yii\db\Expression;

/**
 * m210906_072542_convert_customers_to_user_elements migration.
 */
class m210906_072542_convert_customers_to_user_elements extends Migration
{
    /**
     * @inheritdoc
     * @throws NotSupportedException
     */
    public function safeUp()
    {
        $allUsersByCustomerId = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['email', 'userId', 'customerId'])
            ->innerJoin('{{%commerce_customers}} customers', 'customers.id = orders.customerId')
            ->where(['not' , ['email' => null]])
            ->andWhere(['not' , ['email' => '']])
            ->indexBy('customerId')
            ->orderBy('customerId ASC')
            ->distinct()
            ->all();

        $userIdsByEmail = [];
        foreach ($allUsersByCustomerId as &$row) {
            if ($row['userId']) {
                $userIdsByEmail[$row['email']] = $row['userId'];
                continue;
            }

            if (isset($userIdsByEmail[$row['email']])) {
                $row['userId'] = $userIdsByEmail[$row['email']];
                continue;
            }

            $user = Craft::$app->getUsers()->ensureUserByEmail($row['email']);
            $row['userId'] = $user->id;
            $userIdsByEmail[$row['email']] = $user->id;
        }
        unset($row);

        $allUserIdsByCustomerId = ArrayHelper::getColumn($allUsersByCustomerId, 'userId', true);
        // Remove `customerId` fk
        $this->dropForeignKeyIfExists('{{%commerce_orders}}', 'customerId');

        // Orders
        $this->_batchUpdateUserId($allUserIdsByCustomerId, '{{%commerce_orders}}', 'customerId');

        // Drop all customer IDs with no data
        $this->update('{{%commerce_orders}}', ['customerId' => null], ['email' => null]);
        $this->update('{{%commerce_orders}}', ['customerId' => null], ['email' => '']);

        // Add fk to `customerId`
        $this->addForeignKey(null, '{{%commerce_orders}}', ['customerId'], '{{%users}}', ['id'], 'RESTRICT', 'CASCADE');

        $customersPrimaryAddresses = (new Query())->from('{{%commerce_customers}}')
            ->select(['id', 'primaryBillingAddressId', 'primaryShippingAddressId'])
            ->where(['not', ['primaryBillingAddressId' => null]])
            ->orWhere(['not', ['primaryShippingAddressId' => null]])
            ->indexBy('id')
            ->all();

        // Create Commerce users optimisation table
        $this->createTable('{{%commerce_users}}', [
            'userId' => $this->primaryKey(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $batchInsertUserIds = $allUserIdsByCustomerId;
        array_walk($batchInsertUserIds, function(&$row) { $row = [$row]; });
        $this->batchInsert('{{%commerce_users}}', ['userId'], $batchInsertUserIds);

        $this->createIndex(null, '{{%commerce_users}}', 'userId', true);
        $this->addForeignKey(null, '{{%commerce_users}}', ['userId'], '{{%users}}', ['id'], 'CASCADE', 'CASCADE');

        // Order Histories
        $this->_switchCustomerIdToUserId('{{%commerce_orderhistories}}', $allUserIdsByCustomerId);

        // Customer's Addresses
        // Rename customer addresses -> user addresses
        $this->renameTable('{{%commerce_customers_addresses}}', '{{%commerce_users_addresses}}');
        $this->addColumn('{{%commerce_users_addresses}}', 'isPrimaryBillingAddress', $this->boolean()->defaultValue(false));
        $this->addColumn('{{%commerce_users_addresses}}', 'isPrimaryShippingAddress', $this->boolean()->defaultValue(false));

        $this->_switchCustomerIdToUserId('{{%commerce_users_addresses}}', $allUserIdsByCustomerId);

        // Update primary address data
        $this->update('{{%commerce_users_addresses}}',
            ['isPrimaryBillingAddress' => true],
            ['addressId' => array_filter(ArrayHelper::getColumn($customersPrimaryAddresses, 'primaryBillingAddressId'))],
            [],
            false
        );

        $this->update('{{%commerce_users_addresses}}',
            ['isPrimaryShippingAddress' => true],
            ['addressId' => array_filter(ArrayHelper::getColumn($customersPrimaryAddresses, 'primaryShippingAddressId'))],
            [],
            false
        );

        // Customer discount uses
        $this->renameTable('{{%commerce_customer_discountuses}}', '{{%commerce_user_discountuses}}');

        $this->_switchCustomerIdToUserId('{{%commerce_user_discountuses}}', $allUserIdsByCustomerId);

        $this->dropIndexIfExists('{{%commerce_user_discountuses}}', 'discountId', true);
        $this->createIndex(null, '{{%commerce_user_discountuses}}', ['userId', 'discountId'], true);

        // Remove customers table
        $this->dropTableIfExists('{{%commerce_customers}}');
    }

    /**
     * @param string $table
     * @param array $customers
     * @throws NotSupportedException
     */
    private function _switchCustomerIdToUserId(string $table, array $customers): void
    {
        // Add `userId` column
        if (!$this->db->columnExists($table, 'userId')) {
            $this->addColumn($table, 'userId', $this->integer());
        }

        // Update `userId` column data
        $this->_batchUpdateUserId($customers, $table);

        // Add foreign key constraint
        $this->addForeignKey(null, $table, ['userId'], '{{%users}}', ['id'], 'RESTRICT', 'CASCADE');

        $this->dropForeignKeyIfExists($table, 'customerId');

        // Drop `customerId` column
        if ($this->db->columnExists($table, 'customerId')) {
            $this->dropColumn($table, 'customerId');
        }
    }

    /**
     * @param array $customers
     * @param string $table
     * @param string $updateColumn
     */
    private function _batchUpdateUserId(array $customers, string $table, string $updateColumn = 'userId'): void
    {
        $batches = array_chunk($customers, 500, true);
        foreach ($batches as $batch) {
            $cases = '';
            foreach ($batch as $customerId => $userId) {
                $cases .= '
                WHEN [[customerId]] = ' . $customerId . ' THEN ' . $userId;
            }
            $cases .= '
            ';

            $this->update($table, [
                $updateColumn => new Expression('CASE ' . $cases . ' END')
            ], ['customerId' => array_keys($batch)]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210906_072542_convert_customers_to_user_elements cannot be reverted.\n";
        return false;
    }
}
