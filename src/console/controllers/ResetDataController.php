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
use craft\db\Table as CraftTable;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Allows you to reset Commerce data.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.8
 */
class ResetDataController extends Controller
{
    /**
     * Reset Commerce data.
     *
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $reset = $this->prompt('Resetting Commerce data will permanently delete all orders, subscriptions, payment sources, customers, addresses and reset discount usages ... do you wish to continue?', [
            'required' => true,
            'default' => 'no',
            'validator' => function($input) {
                if (!in_array($input, ['yes', 'no'])) {
                    $this->stderr('You must answer either "yes" or "no".' . PHP_EOL, Console::FG_RED);
                    return false;
                }

                return true;
            }
        ]);

        if ($reset == 'yes') {
            $transaction = Craft::$app->getDb()->beginTransaction();

            try {
                $this->stdout('Resetting Commerce data ...' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                // Orders
                $this->stdout('Deleting orders ...' . PHP_EOL, Console::FG_GREEN);
                $ids = (new Query())
                    ->select(['orders.id'])
                    ->from(['orders' => Table::ORDERS])
                    ->column();

                $count = Craft::$app->getDb()->createCommand()
                    ->delete(CraftTable::ELEMENTS, ['id' => $ids])
                    ->execute();

                $this->stdout($count . ' orders deleted.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                // Subscriptions
                $this->stdout('Deleting subscriptions ...' . PHP_EOL, Console::FG_GREEN);
                $subscriptionIds = (new Query())
                    ->select(['subscriptions.id'])
                    ->from(['subscriptions' => Table::SUBSCRIPTIONS])
                    ->column();

                $count = Craft::$app->getDb()->createCommand()
                    ->delete(CraftTable::ELEMENTS, ['id' => $subscriptionIds])
                    ->execute();

                $this->stdout($count . ' subscriptions deleted.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                // These should really be deleted with a cascade
                Craft::$app->getDb()->createCommand()
                    ->delete(Table::SUBSCRIPTIONS)
                    ->execute();

                // Payment Sources
                $this->stdout('Deleting payment sources ...' . PHP_EOL, Console::FG_GREEN);
                $count = Craft::$app->getDb()->createCommand()
                    ->delete(Table::PAYMENTSOURCES)
                    ->execute();

                $this->stdout($count . ' payment sources deleted.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                // Customers
                $this->stdout('Deleting customers ...' . PHP_EOL, Console::FG_GREEN);
                $count = Craft::$app->getDb()->createCommand()
                    ->delete(Table::CUSTOMERS, ['userId' => null])
                    ->execute();

                $this->stdout($count . ' customers deleted.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                // Address
                $this->stdout('Deleting addresses ...' . PHP_EOL, Console::FG_GREEN);
                $count = Craft::$app->getDb()->createCommand()
                    ->delete(Table::ADDRESSES, ['not', ['isStoreLocation' => true]])
                    ->execute();

                $this->stdout($count . ' addresses deleted.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                // Discount usage
                $this->stdout('Resetting discount usage data ...' . PHP_EOL, Console::FG_GREEN);
                Craft::$app->getDb()->createCommand()
                    ->delete(Table::CUSTOMER_DISCOUNTUSES)
                    ->execute();
                Craft::$app->getDb()->createCommand()
                    ->delete(Table::EMAIL_DISCOUNTUSES)
                    ->execute();
                Craft::$app->getDb()->createCommand()
                    ->update(Table::DISCOUNTS, ['totalDiscountUses' => 0], '', [], false)
                    ->execute();

                $this->stdout('  - per customer discount counter cleared.' . PHP_EOL, Console::FG_GREEN);
                $this->stdout('  - per email discount counter cleared.' . PHP_EOL, Console::FG_GREEN);
                $this->stdout('  - total discount uses counter cleared.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                $this->stdout('Finished.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

                $transaction->commit();
            } catch (\Exception $e) {
                $this->stdout($e->getmessage() . PHP_EOL, Console::FG_RED);
                $transaction->rollBack();
            }
        } else {
            $this->stdout('Skipping data reset.' . PHP_EOL . PHP_EOL, Console::FG_GREEN);
        }

        return ExitCode::OK;
    }
}
