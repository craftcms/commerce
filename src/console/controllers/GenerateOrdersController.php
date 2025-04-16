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
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\enums\LineItemType;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\models\LineItem;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\elements\Address;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\records\Element;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Throwable;
use yii\console\ExitCode;
use yii\helpers\VarDumper;

/**
 * Allows you to generate fake order data including manual payment transactions.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class GenerateOrdersController extends Controller
{
    /**
     * @var int The number of orders to generate
     */
    public int $count = 10;

    /**
     * @var int The number of days back to generate orders for
     */
    public int $days = 30;

    /**
     * @var bool Whether to include manual payment transactions
     */
    public bool $withTransactions = true;
    
    /**
     * @var User[]|null Users for order generation
     */
    private ?array $_users = null;
    
    /**
     * @var Generator Faker instance
     */
    private Generator $_faker;

    /**
     * @var \craft\commerce\models\Store Store to use
     */
    private $store;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'count';
        $options[] = 'days';
        $options[] = 'withTransactions';
        
        return $options;
    }
    
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        
        // Check if Faker is available
        if (!class_exists(Factory::class)) {
            throw new Exception('Faker library is required. Run "composer require fakerphp/faker" to install it.');
        }
        
        $this->_faker = Factory::create();
        $this->store = Plugin::getInstance()->getStores()->getPrimaryStore();
    }

    /**
     * Generate fake orders with optional manual payment transactions.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        if ($this->count <= 0) {
            $this->stderr('Count must be greater than 0.' . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->days <= 0) {
            $this->stderr('Days must be greater than 0.' . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $this->stdout('Generating ' . $this->count . ' orders...' . PHP_EOL, Console::FG_GREEN);
            $this->stdout('Using date range: ' . $this->days . ' days back from now' . PHP_EOL, Console::FG_GREEN);
            
            if ($this->withTransactions) {
                $this->stdout('Including manual payment transactions' . PHP_EOL, Console::FG_GREEN);
            }
            
            $this->stdout(PHP_EOL);
            
            // Load available purchasable variants
            $variants = $this->_getAvailableVariants();
            
            if (empty($variants)) {
                $this->stderr('No available variants to add to orders. Please create products with variants first.' . PHP_EOL, Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            
            $this->stdout('Found ' . count($variants) . ' variants to use in orders.' . PHP_EOL, Console::FG_GREEN);
            
            // Get payment gateway 
            $gateway = Plugin::getInstance()->getGateways()->getGatewayByHandle('manual');
            
            if (!$gateway && $this->withTransactions) {
                $this->stderr('Manual gateway not found but transactions were requested. Please create the manual gateway first.' . PHP_EOL, Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            
            // Create orders
            $success = 0;
            $failed = 0;
            
            for ($i = 0; $i < $this->count; $i++) {
                $percentComplete = floor(($i / $this->count) * 100);
                $this->stdout("\rGenerating order " . ($i + 1) . " of " . $this->count . " ({$percentComplete}%)", Console::FG_GREEN);
                
                try {
                    // Create the order
                    $order = $this->_createFakeOrder($variants);
                    
                    // Add manual transaction if requested
                    if ($this->withTransactions && $gateway) {
                        $this->_createManualTransaction($order, $gateway);
                    }
                    
                    $success++;
                } catch (Throwable $e) {
                    $this->stderr(PHP_EOL . 'Error creating order: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
                    $failed++;
                }
            }
            
            $this->stdout(PHP_EOL . PHP_EOL);
            $this->stdout('Successfully generated ' . $success . ' orders.' . PHP_EOL, Console::FG_GREEN);
            
            if ($failed > 0) {
                $this->stderr('Failed to generate ' . $failed . ' orders.' . PHP_EOL, Console::FG_RED);
            }
            
        } catch (Throwable $e) {
            $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }
    
    /**
     * Get available users for order customer assignment
     * 
     * @return User[]
     */
    private function _getUsers(): array
    {
        if ($this->_users === null) {
            $this->_users = User::find()
                ->limit(100)
                ->all();
            
            // Create a default user if needed
            if (empty($this->_users)) {
                $this->stdout('No users found. Creating a default user for orders...' . PHP_EOL, Console::FG_YELLOW);
                
                $user = new User();
                $user->username = 'customer' . StringHelper::randomString(8);
                $user->email = 'customer' . StringHelper::randomString(8) . '@example.com';
                
                if (Craft::$app->getElements()->saveElement($user)) {
                    $this->_users = [$user];
                }
            }
        }
        
        return $this->_users;
    }
    
    /**
     * Get available variants for order line items
     * 
     * @return Variant[]
     */
    private function _getAvailableVariants(): array
    {
        return Variant::find()
            ->limit(50)
            ->all();
    }
    
    /**
     * Create a fake address for an order
     * 
     * @return Address
     */
    private function _createFakeAddress(): Address
    {
        $address = new Address();
        $address->fullName = $this->_faker->name();
        $address->addressLine1 = $this->_faker->streetAddress();
        $address->locality = $this->_faker->city();
        $address->administrativeArea = $this->_faker->state();
        $address->postalCode = $this->_faker->postcode();
        $address->countryCode = 'US';
        $address->title = $this->_faker->title();
        $address->firstName = $this->_faker->firstName();
        $address->lastName = $this->_faker->lastName();
        
        return $address;
    }
    
    /**
     * Create a fake order with random data
     * 
     * @param Variant[] $variants Available variants to add to the order
     * @return Order The created order
     * @throws Throwable
     */
    private function _createFakeOrder(array $variants): Order
    {
        // Create order
        $order = new Order();
        $order->storeId = $this->store->id;
        $order->number = Plugin::getInstance()->getCarts()->generateCartNumber();
        $order->orderLanguage = 'en-US';
        
        // Set the customer
        $users = $this->_getUsers();
        
        if (!empty($users)) {
            $customer = $this->_faker->randomElement($users);
            $order->setCustomer($customer);
        } else {
            $order->email = $this->_faker->email();
        }
        
        // Create and set addresses
        $shippingAddress = $this->_createFakeAddress();
        $billingAddress = $this->_createFakeAddress();
        
        // We need to save the addresses first
        Craft::$app->getElements()->saveElement($shippingAddress);
        Craft::$app->getElements()->saveElement($billingAddress);
        
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        
        // Currency settings
        $order->currency = $this->store->getCurrency()->getCode();
        $order->paymentCurrency = $order->currency;
        
        // Set order as complete
        $order->isCompleted = true;
        
        // Set random order date within the specified range
        $randomTimestamp = $this->_faker->dateTimeBetween('-' . $this->days . ' days', 'now')->getTimestamp();
        $randomDate = DateTimeHelper::toDateTime($randomTimestamp);
        $order->dateOrdered = $randomDate;
        
        // Save the order to get an ID
        if (!Craft::$app->getElements()->saveElement($order)) {
            throw new Exception('Could not save order: ' . VarDumper::dumpAsString($order->getErrors()));
        }
        
        // Add random line items (1-5)
        $itemCount = $this->_faker->numberBetween(1, 5);
        
        for ($i = 0; $i < $itemCount; $i++) {
            // Pick a random variant
            $variant = $this->_faker->randomElement($variants);
            
            // Create line item
            $lineItem = new LineItem();
            $lineItem->purchasableId = $variant->id;
            $lineItem->qty = $this->_faker->numberBetween(1, 3);
            $lineItem->type = LineItemType::Purchasable;
            
            // Add line item to order
            $order->addLineItem($lineItem);
        }
        
        // Update totals
        $order->recalculate();
        
        // Save the order with the line items
        if (!Craft::$app->getElements()->saveElement($order)) {
            throw new Exception('Could not save order with line items: ' . VarDumper::dumpAsString($order->getErrors()));
        }
        
        // Complete the order
        $orderStatus =Plugin::getInstance()->getOrderStatuses()->getDefaultOrderStatus($this->store->id);
        
        if ($orderStatus) {
            $order->orderStatusId = $orderStatus->id;
            
            if (!Craft::$app->getElements()->saveElement($order)) {
                throw new Exception('Could not save order with status: ' . VarDumper::dumpAsString($order->getErrors()));
            }
        }
        
        return $order;
    }
    
    /**
     * Create a manual transaction for an order
     * 
     * @param Order $order The order to create a transaction for
     * @param \craft\commerce\base\Gateway $gateway The manual gateway
     * @return Transaction The created transaction
     * @throws Throwable
     */
    private function _createManualTransaction(Order $order, $gateway): Transaction
    {
        // Create an authorize transaction
        $transaction = Plugin::getInstance()->getTransactions()->createTransaction($order);
        $transaction->type = TransactionRecord::TYPE_AUTHORIZE;
        $transaction->status = TransactionRecord::STATUS_SUCCESS;
        $transaction->reference = 'MANUAL-' . StringHelper::randomString(12);
        
        // Save the transaction
        if (!Plugin::getInstance()->getTransactions()->saveTransaction($transaction)) {
            throw new Exception('Could not save authorize transaction: ' . VarDumper::dumpAsString($transaction->getErrors()));
        }
        
        // Create a capture transaction
        $childTransaction = Plugin::getInstance()->getTransactions()->createTransaction($order, $transaction, TransactionRecord::TYPE_CAPTURE);
        $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
        $childTransaction->reference = 'MANUAL-CAPT-' . StringHelper::randomString(10);
        
        // Save the child transaction
        if (!Plugin::getInstance()->getTransactions()->saveTransaction($childTransaction)) {
            throw new Exception('Could not save capture transaction: ' . VarDumper::dumpAsString($childTransaction->getErrors()));
        }
        
        return $transaction;
    }
}