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
        // TODO It would be advisable to consolidate guest orders before starting this process

        // Store data from customers/customers addresses table
        $guestCustomers = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['email'])
            ->innerJoin('{{%commerce_customers}} customers', 'customers.id = orders.customerId')
            ->where(['customers.userId' => null])
            ->andWhere(['not' , ['email' => null]])
            ->andWhere(['not' , ['email' => '']])
            ->indexBy('customerId')
            ->distinct()
            ->column();

        foreach ($guestCustomers as &$row) {
            $user = Craft::$app->getUsers()->ensureUserByEmail($row);
            $row = $user->id;
        }
        unset($row);

        $currentUserCustomersById = (new Query())->from('{{%commerce_customers}}')
            ->select(['userId'])
            ->where(['not', ['userId' => null]])
            ->indexBy('id')
            ->column();

        $allUserIdsByCustomerId = array_replace($guestCustomers, $currentUserCustomersById);

        // Orders
        $this->_switchCustomerIdToUserId('{{%commerce_orders}}', $allUserIdsByCustomerId);

        $userIdsByEmail = (new Query())->from('{{%commerce_orders}} orders')
            ->select(['userId'])
            ->where(['not', ['email' => null]])
            ->andWhere(['not', ['email' => '']])
            ->indexBy('email')
            ->column();

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

        $userDiscountUses = (new Query())->from('{{%commerce_user_discountuses}}')
            ->select(['id', 'discountId', 'userId', 'uses', 'dateCreated', 'dateUpdated', 'uid'])
            ->indexBy('id')
            ->all();

        $emailDiscountUses = (new Query())->from('{{%commerce_email_discountuses}}')
            ->select(['discountId', 'email', 'uses', 'dateCreated', 'dateUpdated', 'uid'])
            ->all();

        if (!empty($emailDiscountUses)) {
            foreach ($emailDiscountUses as $emailDiscountUse) {
                $userId = $userIdsByEmail[$emailDiscountUse['email']] ?? null;
                if (!$userId) {
                    continue;
                }

                $discountId = $emailDiscountUse['discountId'];

                $useRow = ArrayHelper::firstWhere($userDiscountUses, function($udu) use ($discountId, $userId) {
                    return $udu['discountId'] == $discountId && $udu['userId'] == $userId;
                });

                if ($useRow) {
                    $userDiscountUses[$useRow['id']]['uses'] += $emailDiscountUse['uses'];

                    $userDiscountUses[$useRow['id']]['dateCreated'] = $useRow['dateCreated'] < $emailDiscountUse['dateCreated'] ? $useRow['dateCreated'] : $emailDiscountUse['dateCreated'];
                    $userDiscountUses[$useRow['id']]['dateUpdated'] = $useRow['dateUpdated'] > $emailDiscountUse['dateUpdated'] ? $useRow['dateUpdated'] : $emailDiscountUse['dateUpdated'];
                } else {
                    $userDiscountUses[] = [
                        null,
                        $emailDiscountUse['discountId'],
                        $userId,
                        $emailDiscountUse['uses'],
                        $emailDiscountUse['dateCreated'],
                        $emailDiscountUse['dateUpdated'],
                        $emailDiscountUse['uid'],
                    ];
                }
            }
        }

        $this->truncateTable('{{%commerce_user_discountuses}}');
        $this->dropIndexIfExists('{{%commerce_user_discountuses}}', 'discountId', true);
        $this->batchInsert('{{%commerce_user_discountuses}}', ['id', 'discountId', 'userId', 'uses', 'dateCreated', 'dateUpdated', 'uid'], $userDiscountUses, false);
        $this->createIndex(null, '{{%commerce_user_discountuses}}', ['userId', 'discountId'], true);

        $this->dropTableIfExists('{{%commerce_email_discountuses}}');

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
     */
    private function _batchUpdateUserId(array $customers, string $table): void
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
                'userId' => new Expression('CASE ' . $cases . ' END')
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
