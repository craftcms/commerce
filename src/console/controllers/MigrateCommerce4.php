<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use craft\commerce\console\Controller;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\test\Craft;

/**
 * Command to be run once upgraded to Commerce 4
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class MigrateCommerce4 extends Controller{
    /**
     * Reset Commerce data.
     */
    public function actionIndex(): int
    {
        $this->stdout("This command will move data from Commerce 3 to Commerce 4.\n");
        $this->stdout("\n");

        $customers = (new Query())
            ->select('*')
            ->from(['{{%commerce_customers}}']);

        $usersAddressesQuery = (new Query())
            ->select('*')
            ->from([Table::USERS_ADDRESSES])
            ->where(['type' => 'shipping']);


        $addressesQuery = (new Query())
            ->select('*')
            ->from([Table::ADDRESSES]);


        foreach ($addressesQuery->each() as $address){

        };


        $this->stdout("Done.\n");

        return 0;
    }
}