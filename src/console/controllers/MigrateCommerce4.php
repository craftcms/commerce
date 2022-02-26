<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\commerce\console\Controller;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\elements\Address;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Command to be run once upgraded to Commerce 4
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class MigrateCommerce4 extends Controller
{
    /**
     * Reset Commerce data.
     */
    public function actionIndex(): int
    {
        $this->stdout("This command will move data from Commerce 3 to Commerce 4.\n");

        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '4.0.0', '>=')) {
            $this->stdout("You must run the `craft migrate` command first.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n");

        $this->_migrateOrderCustomerId();

        $this->_migrateStoreAddress();

        $customersQuery = (new Query())
            ->select('*')
            ->from(['{{%commerce_customers}}']);

        $usersAddressesQuery = (new Query())
            ->select('*')
            ->from([Table::USERS_ADDRESSES])
            ->where(['type' => 'shipping']);

        $addressesQuery = (new Query())
            ->select('*')
            ->from([Table::ADDRESSES]);

        // Drop Order table column `v3CustomerId`

        // Drop Customer table `v3Id` and `v3UserId` columns
        // Make primary column the customerId

        $this->stdout("Done.\n");

        return 0;
    }

    public function _migrateOrderCustomerId(): void
    {
        $emails = (new Query())
            ->select(['email'])
            ->from(['{{%commerce_orders}}'])
            ->where(['not' , ['email' => null]])
            ->andWhere(['not' , ['email' => '']])
            ->distinct()
            ->column();

        $done = 0;
        Console::startProgress($done, count($emails), 'Ensuring users exist for each customer...');
        foreach ($emails as $email){
            Console::updateProgress($done++, count($emails), $email);
            $user = Craft::$app->getUsers()->ensureUserByEmail($email);
            Craft::$app->getDb()->createCommand()->update(Table::ORDERS, ['customerId' => $user->id], ['email' => $email])->execute();
        }
        Console::endProgress(false, false);

    }

    /**
     * @return void
     */
    private function _migrateStoreAddress(): void
    {
        $storeLocationData = (new Query())
            ->select(['*'])
            ->from(['{{%commerce_addresses}}' . ' addresses'])
            ->where(['isStoreLocation' => true])
            ->one();

        if ($storeLocationData) {
            $address = new Address();
            Craft::$app->getElement()->saveElement($address, false);
        }
    }
}