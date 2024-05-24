<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\commerce\base\Gateway;
use craft\commerce\base\Purchasable as PurchasableElement;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\LineItemType;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\errors\RefundException;
use craft\commerce\errors\TransactionException;
use craft\commerce\events\ModifyPurchasablesTableQueryEvent;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\helpers\LineItem;
use craft\commerce\helpers\Locale;
use craft\commerce\helpers\PaymentForm;
use craft\commerce\helpers\Purchasable;
use craft\commerce\models\inventory\InventoryFulfillMovement;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\Pdf;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\stripe\gateways\PaymentIntents;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\commerce\web\assets\commerceui\CommerceOrderAsset;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\Address;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\InvalidElementException;
use craft\errors\UnsupportedSiteException;
use craft\helpers\AdminTable;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\MoneyHelper;
use craft\helpers\Number;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\assets\money\MoneyAsset;
use craft\web\Controller;
use craft\web\View;
use DateTime;
use DateTimeZone;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Order Editor Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class OrdersController extends Controller
{
    /**
     * @event Event The event that’s triggered when retrieving the purchasables for the add line item table on the order edit page.
     * @since 4.3.0
     *
     * ---
     * ```php
     * use craft\commerce\controllers\OrdersController;
     * use craft\commerce\events\ModifyPurchasablesQueryEvent;
     * use yii\base\Event;
     *
     * Event::on(
     *     OrdersController::class,
     *     OrdersController::EVENT_MODIFY_PURCHASABLES_TABLE_QUERY,
     *     function(ModifyCartInfoEvent $e) {
     *         $e->query->andWhere(['sku' => 'foo']);
     *     }
     * );
     * ```
     */
    public const EVENT_MODIFY_PURCHASABLES_TABLE_QUERY = 'modifyPurchasablesTableQuery';

    /**
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('commerce-manageOrders');
    }

    /**
     * Index of orders
     *
     * @throws Throwable
     */
    public function actionOrderIndex(string $orderStatusHandle = ''): Response
    {
        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

        $site = Cp::requestedSite();
        /** @var StoreBehavior $site */
        $store = $site->getStore();

        Craft::$app->getView()->registerJs('window.orderEdit = {};', View::POS_BEGIN);
        $permissions = [
            'commerce-manageOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-manageOrders'),
            'commerce-editOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-editOrders'),
            'commerce-deleteOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-deleteOrders'),
        ];

        Craft::$app->getView()->registerJs('window.orderEdit.currentUserPermissions = ' . Json::encode($permissions) . ';', View::POS_BEGIN);

        return $this->renderTemplate('commerce/orders/_index', compact('orderStatusHandle', 'store'));
    }

    /**
     * Create an order
     *
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws Throwable
     */
    public function actionCreate(string $storeHandle): Response
    {
        $this->requirePermission('commerce-manageOrders');

        $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);

        if (!$store) {
            throw new InvalidArgumentException('Invalid store handle: ' . $storeHandle);
        }

        $userId = $this->request->getParam('customerId');
        $user = $userId ? Craft::$app->getUsers()->getUserById($userId) : null;

        if ($userId && !$user) {
            throw new BadRequestHttpException("Invalid user ID: $userId");
        }

        $attributes = [
            'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
            'origin' => Order::ORIGIN_CP,
            'storeId' => $store->id,
        ];
        if ($user) {
            $attributes['customer'] = $user;
        }

        $order = Craft::createObject([
            'class' => Order::class,
            'attributes' => $attributes,
        ]);

        if ($user) {
            // Try to set defaults
            $order->autoSetAddresses();
            $order->autoSetShippingMethod();
        }

        if (!Craft::$app->getElements()->saveElement($order, false)) {
            throw new Exception(Craft::t('commerce', 'Can not create a new order'));
        }

        return $this->redirect('commerce/orders/' . $order->id);
    }

    /**
     * @param Order|null $order
     * @param null $paymentForm
     * @throws CurrencyException
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws InvalidConfigException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function actionEditOrder(int $orderId, Order $order = null, $paymentForm = null): Response
    {
        $plugin = Plugin::getInstance();
        $variables = [];

        if ($order === null && $orderId) {
            $order = $plugin->getOrders()->getOrderById($orderId);

            if (!$order) {
                throw new HttpException(404, Craft::t('commerce', 'Can not find order.'));
            }
        }

        $this->enforceManageOrderPermissions($order);

        $variables['order'] = $order;

        DebugPanel::prependOrAppendModelTab(model: $order, prepend: true);

        $variables['paymentForm'] = $paymentForm;
        $variables['orderId'] = $order->id;

        $transactions = $order->getTransactions();

        $variables['orderTransactions'] = $this->_getTransactionsWithLevelsTableArray($transactions);

        $this->_updateTemplateVariables($variables);
        $this->_registerJavascript($variables);

        return $this->renderTemplate('commerce/orders/_edit', $variables);
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     * @throws \yii\web\MethodNotAllowedHttpException
     * @since 5.0.0
     */
    public function actionFulfill(): Response
    {
        $this->requirePostRequest();

        $fulfillments = $this->request->getBodyParam('fulfillment');
        $movements = [];
        foreach ($fulfillments as $fulfillment) {
            $qty = (int)$fulfillment['quantity'];
            if ($qty != 0) {
                $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($fulfillment['inventoryLocationId']);
                $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($fulfillment['inventoryItemId']);
                $movement = new InventoryFulfillMovement();
                $movement->fromInventoryLocation = $inventoryLocation;
                $movement->inventoryItem = $inventoryItem;
                $movement->toInventoryLocation = $inventoryLocation;
                $movement->fromInventoryTransactionType = InventoryTransactionType::COMMITTED;
                $movement->toInventoryTransactionType = InventoryTransactionType::FULFILLED;
                $movement->lineItemId = $fulfillment['lineItemId'];
                $movement->quantity = $qty;
                $movement->userId = Craft::$app->getUser()->getId();
                $movements[] = $movement;
            }
        }

        foreach ($movements as $movement) {
            if (!$movement->isValid()) {
                return $this->asFailure(Craft::t('commerce', 'Invalid inventory movements.'),
                    [
                        'errors' => ['fulfillment' => $movement->getErrors() ],
                    ]);
            }
        }

        /** @var InventoryMovementCollection $movements */
        $movements = InventoryMovementCollection::make($movements);

        if (!Plugin::getInstance()->getInventory()->executeInventoryMovements($movements)) {
            return $this->asFailure(Craft::t('commerce', 'Invalid inventory movements.'));
        }

        return $this->asSuccess(Craft::t('commerce', 'Updated committed stock successfully.'));
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws \craft\errors\DeprecationException
     * @since 5.0.0
     */
    public function actionFulfillmentModal(): Response
    {
        $this->requireAcceptsJson();

        $orderId = $this->request->getRequiredParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        $inventoryFulfillmentLevels = Plugin::getInstance()->getInventory()->getInventoryFulfillmentLevels($order)->groupBy('inventoryLocationId');

        /** @phpstan-ignore-next-line */
        $response = $this->asCpModal()
            ->action('commerce/orders/fulfill')
            ->submitButtonLabel(Craft::t('commerce', 'Update'))
            ->contentTemplate('commerce/orders/modals/_fulfillmentModal', [
                'inventoryFulfillmentLevels' => $inventoryFulfillmentLevels,
                'order' => $order,
            ])->prepareModal(function() {
                $view = Craft::$app->getView();
                $view->registerJsWithVars(fn() => <<<JS
document.querySelector('input.fulfillment-quantity').addEventListener('input', e=>{
  const el = e.target || e
  if(el.type == "number" && el.max && el.min ){
    let value = parseInt(el.value)
    el.value = value // for 000 like input cleanup to 0
    let max = parseInt(el.max)
    let min = parseInt(el.min)
    if ( value > max ) el.value = el.max
    if ( value < min ) el.value = el.min
  }
});
JS, []);
            });

        return $response;
    }

    /**
     * @throws BadRequestHttpException
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws HttpException
     * @throws InvalidConfigException
     * @throws OrderStatusException
     * @throws Throwable
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $data = $this->request->getBodyParam('orderData');

        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            throw new HttpException(400, Craft::t('commerce', 'Invalid Order ID'));
        }

        $this->enforceManageOrderPermissions($order);

        // Set custom field values
        $order->setFieldValuesFromRequest('fields');

        $alreadyCompleted = $order->isCompleted;
        // Set data from request to the order
        $this->_updateOrder($order, $orderRequestData, false);
        $markAsComplete = !$alreadyCompleted && $order->isCompleted;

        // We don't want to save it as completed yet since we will markAsComplete() after saving the cart
        if ($markAsComplete) {
            $order->isCompleted = false;
            $order->dateOrdered = null;
            $order->orderStatusId = null;
        }

        $order->setScenario(Element::SCENARIO_LIVE);
        $valid = $order->validate(null, false);

        if (!$valid || !Craft::$app->getElements()->saveElement($order, false)) {
            // Recalculation mode should always return to none, unless it is still a cart
            $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
            if (!$order->isCompleted) {
                $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
            }

            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save order.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'order' => $order,
            ]);

            return null;
        }

        // This request is marking the order as complete
        if ($markAsComplete) {
            $order->markAsComplete();
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes an order.
     *
     * @throws Exception if you try to edit a non-existent ID.
     * @throws Throwable
     */
    public function actionDeleteOrder(): ?Response
    {
        $this->requirePostRequest();

        $orderId = (int)$this->request->getRequiredBodyParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new HttpException(404, Craft::t('commerce', 'Can not find order.'));
        }

        if (!Craft::$app->getElements()->canDelete($order)) {
            throw new ForbiddenHttpException('User not authorized to view this address.');
        }

        if (!Craft::$app->getElements()->deleteElementById($order->id)) {
            return $this->asFailure();
        }

        return $this->asSuccess(Craft::t('commerce', 'Order deleted.'));
    }

    /**
     * The refresh action accepts a json representation of an order, recalculates it depending on the mode submitted,
     * and returns the order as json with any validation errors.
     *
     * @throws Exception
     */
    public function actionRefresh(): Response
    {
        $data = $this->request->getRawBody();
        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            return $this->asFailure(Craft::t('commerce', 'Invalid Order ID'));
        }

        $this->enforceManageOrderPermissions($order);

        $this->_updateOrder($order, $orderRequestData);

        if ($order->validate(null, false) && $order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
            $order->recalculate(); // dont save, just recalculate
        }

        // Recalculation mode should always return to none, unless it is still a cart
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        if (!$order->isCompleted) {
            $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        }

        if ($order->hasErrors()) {
            return $this->asModelFailure(
                $order,
                Craft::t('commerce', 'The order is not valid.'),
                'order',
                [
                    'order' => $this->_orderToArray($order),
                ]
            );
        }

        return $this->asSuccess(data: [
            'order' => $this->_orderToArray($order),
        ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionUserOrdersTable(): Response
    {
        $this->requirePermission('commerce-manageOrders');
        $this->requireAcceptsJson();

        $page = $this->request->getParam('page', 1);
        $sort = $this->request->getParam('sort');
        $limit = $this->request->getParam('per_page', 10);
        $search = $this->request->getParam('search');
        $offset = ($page - 1) * $limit;

        $customerId = $this->request->getQueryParam('customerId');

        if (!$customerId) {
            return $this->asFailure(Craft::t('commerce', 'Customer ID is required.'));
        }

        $customer = Craft::$app->getUsers()->getUserById($customerId);

        if (!$customer) {
            return $this->asFailure(Craft::t('commerce', 'Unable to retrieve customer.'));
        }

        $orderQuery = Order::find()
            ->customer($customer)
            ->withAll() // eager-load all related data
            ->isCompleted();

        if ($search) {
            $orderQuery->search($search);
        }

        if ($sort) {
            [$field, $direction] = explode('|', $sort);

            if ($field && $direction) {
                $orderQuery->orderBy($field . ' ' . $direction);
            }
        }

        $total = $orderQuery->count();

        $orderQuery->offset($offset);
        $orderQuery->limit($limit);
        $orderQuery->orderBy('dateOrdered DESC');
        $orders = $orderQuery->all();

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                'id' => $order->id,
                'title' => $order->reference,
                'url' => $order->getCpEditUrl(),
                'date' => $order->dateOrdered->format('D jS M Y'),
                'total' => $order->totalAsCurrency,
                'orderStatus' => $order->getOrderStatusHtml(),
            ];
        }

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $rows,
        ]);
    }


    /**
     * @param Order $order
     * @return array
     */
    private function _orderToArray(Order $order): array
    {
        // Remove custom fields
        $orderFields = array_keys($order->fields());

        sort($orderFields);

        // Remove unneeded fields
        $removeProps = [
            'hasDescendants',
            'makePrimaryShippingAddress',
            'shippingSameAsBilling',
            'billingSameAsShipping',
            'tempId',
            'resaving',
            'duplicateOf',
            'totalDescendants',
            'fieldLayoutId',
            'contentId',
            'trashed',
            'structureId',
            'url',
            'ref',
            'title',
            'slug',
        ];
        foreach ($removeProps as $removeProp) {
            ArrayHelper::removeValue($orderFields, $removeProp);
        }

        if (($fieldLayout = $order->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                /** @var Field $field */
                ArrayHelper::removeValue($orderFields, $field->handle);
            }
        }

        $extraFields = [
            'lineItems.snapshot',
            'availableShippingMethodOptions',
            'billingAddress',
            'shippingAddress',
            'orderSite',
            'notices',
            'loadCartUrl',
            'store',
            'totalCommittedStock',
        ];

        $lineItems = $order->getLineItems();
        $purchasableCpEditUrlByPurchasableId = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->type === LineItemType::Custom) {
                continue;
            }

            /** @var Purchasable|PurchasableElement|null $purchasable */
            $purchasable = $lineItem->getPurchasable();
            if (!$purchasable || isset($purchasableCpEditUrlByPurchasableId[$purchasable->id])) {
                continue;
            }

            $purchasableCpEditUrlByPurchasableId[$purchasable->id] = $purchasable->getCpEditUrl();
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $subUnit = Plugin::getInstance()->getCurrencies()->getSubunitFor($order->currency);

        $orderArray = $order->toArray($orderFields, $extraFields);

        if ($orderArray['customer'] && $orderArray['customer']['id'] && $customer = Craft::$app->getUsers()->getUserById($orderArray['customer']['id'])) {
            $orderArray['customer'] = $this->_customerToArray($customer);
        }

        if ($billingAddress) {
            $orderArray['billingAddressHtml'] = Cp::elementCardHtml($billingAddress);
        }

        if ($shippingAddress) {
            $orderArray['shippingAddressHtml'] = Cp::elementCardHtml($shippingAddress);
        }

        if (!empty($orderArray['lineItems'])) {
            foreach ($orderArray['lineItems'] as &$lineItem) {
                $lineItem['price'] = $lineItem['price'] !== null ? Craft::$app->getFormatter()->asDecimal($lineItem['price'], $subUnit) : null;
                $lineItem['promotionalPrice'] = $lineItem['promotionalPrice'] !== null ? Craft::$app->getFormatter()->asDecimal($lineItem['promotionalPrice'], $subUnit) : null;

                $lineItem['showForm'] = ArrayHelper::isAssociative($lineItem['options']) || (is_array($lineItem['options']) && empty($lineItem['options']));
                $lineItem['purchasableCpEditUrl'] = $purchasableCpEditUrlByPurchasableId[$lineItem['purchasableId']] ?? null;
            }
            unset($lineItem);
        }

        return $orderArray;
    }

    /**
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     */
    public function actionPurchasablesTable(): Response
    {
        $this->requirePermission('commerce-manageOrders');
        $this->requireAcceptsJson();

        $page = $this->request->getParam('page', 1);
        $sort = $this->request->getParam('sort');
        $limit = $this->request->getParam('per_page', 10);
        $search = $this->request->getParam('search');
        $siteId = $this->request->getQueryParam('siteId');
        $customerId = $this->request->getQueryParam('customerId', false);

        if (!$siteId) {
            throw new InvalidArgumentException('siteId is required');
        }

        $store = Plugin::getInstance()->getStores()->getStoreBySiteId($siteId);
        if (!$store) {
            throw new InvalidArgumentException('Store not found');
        }

        $offset = ($page - 1) * $limit;

        // Prepare purchasables query
        $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = (new Query())
            ->select(['purchasables.id', 'pstores.basePrice', 'purchasables.description', 'purchasables.sku', 'elements.type'])
            ->leftJoin(['elements' => CraftTable::ELEMENTS], [
                'and',
                '[[elements.id]] = [[purchasables.id]]',
            ])
            ->innerJoin(Table::PURCHASABLES_STORES . ' pstores', '[[purchasables.id]] = [[pstores.purchasableId]]')
            ->where(['elements.enabled' => true])
            ->andWhere(['pstores.storeId' => $store->id])
            ->from(['purchasables' => Table::PURCHASABLES]);

        // Are they searching for a SKU or purchasable description?
        if ($search) {
            $sqlQuery->andwhere([
                'or',
                [$likeOperator, 'purchasables.description', '%' . str_replace(' ', '%', $search) . '%', false],
                [$likeOperator, 'purchasables.sku', $search],
            ]);
        }

        // Do not return any purchasables with temp SKUs
        $sqlQuery->andWhere(new Expression("LEFT([[purchasables.sku]], " . strlen(Purchasable::TEMPORARY_SKU_PREFIX) . ") != '" . Purchasable::TEMPORARY_SKU_PREFIX . "'"));

        // Do not return soft deleted purchasables
        $sqlQuery->andWhere(['elements.dateDeleted' => null]);

        // Apply sorting if required
        if ($sort && strpos($sort, '|')) {
            [$column, $direction] = explode('|', $sort);
            if ($column && in_array($direction, ['asc', 'desc'], true)) {
                $sqlQuery->orderBy([$column => $direction == 'asc' ? SORT_ASC : SORT_DESC]);
            }
        } else {
            $sqlQuery->orderBy(['id' => 'asc']);
        }

        // Trigger event before working out the total and limiting the results for pagination
        if ($this->hasEventHandlers(self::EVENT_MODIFY_PURCHASABLES_TABLE_QUERY)) {
            $event = new ModifyPurchasablesTableQueryEvent([
                'query' => $sqlQuery,
                'search' => $search,
            ]);
            $this->trigger(self::EVENT_MODIFY_PURCHASABLES_TABLE_QUERY, $event);
            $sqlQuery = $event->query;
        }

        $total = $sqlQuery->count();

        $sqlQuery->limit($limit);
        $sqlQuery->offset($offset);

        $result = $sqlQuery->all();

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $this->_addLivePurchasableInfo($result, $siteId, $customerId),
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 4.0
     */
    public function actionCustomerSearch(): Response
    {
        $this->requireAcceptsJson();

        $query = $this->request->getQueryParam('query');

        $limit = 30;
        $customers = [];

        if ($query === null) {
            return $this->asJson($customers);
        }

        $userQuery = User::find()->status(null)->limit($limit);

        if ($query) {
            $userQuery->search(urldecode($query));
        }

        $customers = $userQuery->collect()->map(function(User $user) {
            return $this->_customerToArray($user);
        });

        return $this->asSuccess(data: compact('customers'));
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 4.0
     */
    public function actionGetCustomerAddresses(): Response
    {
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredParam('id');
        $page = $this->request->getParam('page', 1);
        $limit = $this->request->getParam('per_page', 10);
        $offset = ($page - 1) * $limit;

        $user = Craft::$app->getUsers()->getUserById($id);

        if (!$user) {
            return $this->asFailure(message: Craft::t('commerce', 'User not found.'));
        }

        $addressElements = Address::find()
            ->ownerId($user->id)
            ->limit($limit)
            ->offset($offset)
            ->collect();

        $total = $addressElements->count();

        $addresses = $addressElements->map(function(Address $address) {
            return $address->toArray() + [
                    'html' => Cp::elementCardHtml($address),
                ];
        });

        return $this->asSuccess(data: compact('addresses', 'total'));
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 4.0
     */
    public function actionGetOrderAddress(): Response
    {
        $this->requireAcceptsJson();

        $orderId = $this->request->getRequiredParam('orderId');
        $addressId = $this->request->getRequiredParam('addressId');

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            return $this->asFailure(message: Craft::t('commerce', 'Order not found.'));
        }

        /** @var Address|null $address */
        $address = Address::find()
            ->ownerId($order->id)
            ->id($addressId)
            ->one();

        if (!$address) {
            return $this->asFailure(message: Craft::t('commerce', 'Address not found.'));
        }

        return $this->asSuccess(data: [
            'address' => $address->toArray() + [
                    'html' => Cp::elementCardHtml($address),
                ],
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @since 4.0
     */
    public function actionValidateAddress(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $requestAddress = $this->request->getRequiredParam('address');

        $address = Craft::createObject(Address::class, ['config' => ['attributes' => $requestAddress]]);

        if (!$address->validate()) {
            return $this->asModelFailure(model: $address, message: Craft::t('commerce', 'Unable to validate address.'), modelName: 'address');
        }

        return $this->asSuccess();
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionCreateCustomer(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $email = $this->request->getRequiredParam('email');

        try {
            $user = Craft::$app->getUsers()->ensureUserByEmail($email);
            $user = $this->_customerToArray($user);
        } catch (\Exception $e) {
            return $this->asFailure(message: $e->getMessage());
        }

        return $this->asSuccess(data: compact('user'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function actionSendEmail(): Response
    {
        $this->requireAcceptsJson();

        $id = $this->request->getParam('id');
        $orderId = $this->request->getParam('orderId');

        if ($id === null || $orderId === null) {
            return $this->asFailure(Craft::t('commerce', 'Bad Request'));
        }

        $order = Order::find()->id($orderId)->one();
        if ($order === null) {
            return $this->asFailure(Craft::t('commerce', 'Can not find order'));
        }

        $email = Plugin::getInstance()->getEmails()->getEmailById($id, $order->storeId);
        if ($email === null || !$email->enabled) {
            return $this->asFailure(Craft::t('commerce', 'Can not find enabled email.'));
        }

        $originalLanguage = Craft::$app->language;
        $originalFormattingLocale = Craft::$app->formattingLocale;

        // Set language by email's set locale
        $language = $email->getRenderLanguage($order);
        Locale::switchAppLanguage($language);

        $orderData = $order->toArray();

        $success = true;
        $error = '';
        try {
            if (!Plugin::getInstance()->getEmails()->sendEmail($email, $order, null, $orderData, $error)) {
                $success = false;
            }
        } catch (\Exception) {
            $success = false;
        }

        // Set previous language back
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale);

        if (!$success) {
            $error = $error ?: Craft::t('commerce', 'Could not send email');
            return $this->asFailure($error);
        }

        return $this->asSuccess();
    }

    /**
     * Updates an order address
     *
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws BadRequestHttpException
     */
    public function actionUpdateOrderAddress(): Response
    {
        $this->requireAcceptsJson();

        $orderId = $this->request->getParam('orderId');
        $addressId = $this->request->getParam('addressId');
        $type = $this->request->getParam('addressType');

        // Validate Address Type
        if (!in_array($type, ['shippingAddress', 'billingAddress'], true)) {
            $this->asFailure(Craft::t('commerce', 'Not a valid address type'));
        }

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order) {
            $this->asFailure(Craft::t('commerce', 'Bad order ID.'));
        }

        // Return early if the address is already set.
        if ($order->{$type . 'Id'} == $addressId) {
            return $this->asSuccess();
        }

        // Validate Address Id
        $address = $addressId ? Address::find()->id($addressId)->one() : null;
        if (!$address) {
            return $this->asFailure(Craft::t('commerce', 'Bad address ID.'));
        }

        $order->{$type . 'Id'} = $address->id;

        if (!Craft::$app->getElements()->saveElement($order)) {
            return $this->asFailure(Craft::t('commerce', 'Could not update orders address.'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @since 3.0.11
     */
    public function actionGetIndexSourcesBadgeCounts(): Response
    {
        $this->requireAcceptsJson();

        /** @var Site|StoreBehavior|null $site */
        $site = Cp::requestedSite();
        $storeId = $site?->getStore()->id ?? null;

        $counts = Plugin::getInstance()->getOrderStatuses()->getOrderCountByStatus($storeId);

        $total = array_reduce($counts, static function($sum, $thing) {
            return $sum + (int)$thing['orderCount'];
        }, 0);

        return $this->asSuccess(data: compact('counts', 'total'));
    }

    /**
     * Returns Payment Modal
     *
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function actionGetPaymentModal(): Response
    {
        $this->requireAcceptsJson();
        $view = $this->getView();

        $orderId = $this->request->getParam('orderId');
        $paymentFormData = $this->request->getParam('paymentForm');

        $plugin = Plugin::getInstance();
        $order = $plugin->getOrders()->getOrderById($orderId);
        $gateways = $plugin->getGateways()->getAllGateways();

        if ($paymentAmount = $this->request->getParam('paymentAmount')) {
            $order->setPaymentAmount($paymentAmount);
        }
        if ($paymentCurrency = $this->request->getParam('paymentCurrency')) {
            $order->setPaymentCurrency($paymentCurrency);
        }

        $formHtml = '';
        /** @var Gateway $gateway */
        foreach ($gateways as $key => $gateway) {
            // If gateway adapter does no support backend cp payments.
            if ($gateway->availableForUseWithOrder($order) === false || !$gateway->cpPaymentsEnabled() || $gateway instanceof MissingGateway) {
                unset($gateways[$key]);
                continue;
            }

            // Add the errors and data back to the current form model.
            if ($gateway->id == $order->gatewayId) {
                $paymentFormModel = $gateway->getPaymentFormModel();

                if ($paymentFormData) {
                    // Re-add submitted data to payment form model
                    if (isset($paymentFormData['attributes'])) {
                        $paymentFormModel->attributes = $paymentFormData['attributes'];
                    }

                    // Re-add errors to payment form model
                    if (isset($paymentFormData['errors'])) {
                        $paymentFormModel->addErrors($paymentFormData['errors']);
                    }
                }
            } else {
                $paymentFormModel = $gateway->getPaymentFormModel();
            }

            // For backend stripe payments we cant use the 3D secure form.
            /** @TODO remove at next breaking change */
            /** @phpstan-ignore-next-line */
            if ($gateway instanceof PaymentIntents) {
                /** @phpstan-ignore-next-line */
                $paymentFormHtml = $gateway->getOldPaymentFormHtml([
                    'paymentForm' => $paymentFormModel,
                    'order' => $order,
                ]);
            } else {
                $paymentFormHtml = $gateway->getPaymentFormHtml([
                    'paymentForm' => $paymentFormModel,
                    'order' => $order,
                ]);
            }

            $paymentFormHtml = Html::namespaceInputs($paymentFormHtml, PaymentForm::getPaymentFormNamespace($gateway->handle));

            $paymentFormHtml = $view->renderTemplate('commerce/_components/gateways/_modalWrapper', [
                'formHtml' => $paymentFormHtml,
                'gateway' => $gateway,
                'paymentForm' => $paymentFormModel,
                'order' => $order,
            ]);

            $formHtml .= $paymentFormHtml;
        }

        $modalHtml = $view->renderTemplate('commerce/orders/_paymentmodal', [
            'gateways' => $gateways,
            'order' => $order,
            'paymentForms' => $formHtml,
        ]);

        return $this->asSuccess(data: [
            'modalHtml' => $modalHtml,
            'headHtml' => $view->getHeadHtml(),
            'footHtml' => $view->getBodyHtml(),
        ]);
    }

    /**
     * Captures Transaction
     *
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws TransactionException
     */
    public function actionTransactionCapture(): Response
    {
        $this->requirePermission('commerce-capturePayment');
        $this->requirePostRequest();
        $id = $this->request->getRequiredBodyParam('id');
        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById($id);

        if ($transaction->canCapture()) {
            // capture transaction and display result
            $child = Plugin::getInstance()->getPayments()->captureTransaction($transaction);

            $message = $child->message ? ' (' . $child->message . ')' : '';

            if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                $child->order->updateOrderPaidInformation();
                $this->setSuccessFlash(Craft::t('commerce', 'Transaction captured successfully: {message}', [
                    'message' => $message,
                ]));
            } else {
                $this->setFailFlash(Craft::t('commerce', 'Couldn’t capture transaction: {message}', [
                    'message' => $message,
                ]));
            }
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t capture transaction.', ['id' => $id]));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Refunds transaction.
     *
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionTransactionRefund(): Response
    {
        $this->requirePermission('commerce-refundPayment');
        $this->requirePostRequest();
        $id = $this->request->getRequiredBodyParam('id');

        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById($id);

        $amount = $this->request->getParam('amount');
        $amount = MoneyHelper::toMoney(array_merge($amount,['currency' => $transaction->paymentCurrency]));
        $amount = MoneyHelper::toDecimal($amount);

        $note = $this->request->getRequiredBodyParam('note');

        if (!$transaction) {
            $error = Craft::t('commerce', 'Can not find the transaction to refund');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($error);
            } else {
                $this->setFailFlash($error);
                return $this->redirectToPostedUrl();
            }
        }

        if (!$amount) {
            $amount = $transaction->getRefundableAmount();
        }

        if ($amount > $transaction->getRefundableAmount()) {
            $error = Craft::t('commerce', 'Can not refund amount greater than the remaining amount');
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($error);
            } else {
                $this->setFailFlash($error);
                return $this->redirectToPostedUrl();
            }
        }

        if ($transaction->canRefund()) {
            try {
                // refund transaction and display result
                $child = Plugin::getInstance()->getPayments()->refundTransaction($transaction, $amount, $note);

                $message = $child->message ? ' (' . $child->message . ')' : '';

                if ($child->status == TransactionRecord::STATUS_SUCCESS || $child->status == TransactionRecord::STATUS_PROCESSING) {
                    $child->order->updateOrderPaidInformation();
                    $this->setSuccessFlash(Craft::t('commerce', 'Transaction refunded successfully: {message}', [
                        'message' => $message,
                    ]));
                } else {
                    $this->setFailFlash(Craft::t('commerce', 'Couldn’t refund transaction: {message}', [
                        'message' => $message,
                    ]));
                }
            } catch (RefundException $exception) {
                $this->setFailFlash($exception->getMessage());
            }
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t refund transaction.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * @throws BadRequestHttpException
     * @throws CurrencyException
     */
    public function actionPaymentAmountData(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();
        $paymentCurrencies = Plugin::getInstance()->getPaymentCurrencies();
        $paymentCurrency = $this->request->getRequiredParam('paymentCurrency');
        $paymentAmount = $this->request->getRequiredParam('paymentAmount');
        $orderId = $this->request->getRequiredParam('orderId');
        /** @var Order $order */
        $order = Order::find()->id($orderId)->one();
        $baseCurrency = $order->currency;

        $baseCurrencyPaymentAmount = $paymentCurrencies->convertCurrency($paymentAmount, $paymentCurrency, $baseCurrency);
        $baseCurrencyPaymentAmountAsCurrency = Craft::t('commerce', 'Pay {amount} of {currency} on the order.', ['amount' => Currency::formatAsCurrency($baseCurrencyPaymentAmount, $baseCurrency), 'currency' => $baseCurrency]);

        $outstandingBalance = $order->outstandingBalance;
        $outstandingBalanceAsCurrency = $order->outstandingBalanceAsCurrency;

        $message = '';
        if (Currency::round($baseCurrencyPaymentAmount) > Currency::round($outstandingBalance)) {
            $baseCurrencyPaymentAmount = $outstandingBalance;
            $baseCurrencyPaymentAmountAsCurrency = Craft::t('commerce', 'Pay {amount} of {currency} on the order.', ['amount' => $outstandingBalanceAsCurrency, 'currency' => $baseCurrency]);
            $message = Craft::t('commerce', 'Order payment balance is {outstandingBalanceAsCurrency}. This is the maximum value that will be charged.', ['outstandingBalanceAsCurrency' => $outstandingBalanceAsCurrency]);
        }

        return $this->asSuccess($message, data: [
            'paymentCurrency' => $paymentCurrency,
            'paymentAmount' => $paymentAmount,
            'outstandingBalance' => $outstandingBalance,
            'outstandingBalanceAsCurrency' => $outstandingBalanceAsCurrency,
            'baseCurrencyPaymentAmountAsCurrency' => $baseCurrencyPaymentAmountAsCurrency,
            'baseCurrencyPaymentAmount' => $baseCurrencyPaymentAmount,
        ]);
    }

    /**
     * Modifies the variables of the request.
     */
    private function _updateTemplateVariables(array &$variables): void
    {
        /** @var Order $order */
        $order = $variables['order'];

        $variables['title'] = Craft::t('commerce', 'Order') . ' ' . $order->reference;

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_CP) {
            $variables['title'] = Craft::t('commerce', 'New Order');
        }

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_WEB) {
            $variables['title'] = Craft::t('commerce', 'Cart {number}', ['number' => $order->getShortNumber()]);
        }

        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Order::class);
        $staticForm = $fieldLayout->createForm($order, true, [
            'namespace' => 'static_fields',
            'tabIdPrefix' => 'static-fields',
        ]);
        $dynamicForm = $fieldLayout->createForm($order, false, [
            'tabIdPrefix' => 'fields',
        ]);

        $variables['staticFieldsHtml'] = $staticForm->render(false);
        $variables['dynamicFieldsHtml'] = $dynamicForm->render(false);

        $variables['tabs'] = [];

        $variables['tabs']['order-details'] = [
            'label' => Craft::t('commerce', 'Order Details'),
            'url' => '#orderDetailsTab',
            'class' => null,
        ];

        foreach ($staticForm->getTabMenu() as $tabId => $tab) {
            $tab['class'] .= ' custom-tab static';
            $variables['tabs'][$tabId] = $tab;
        }

        foreach ($dynamicForm->getTabMenu() as $tabId => $tab) {
            $tab['class'] .= ' custom-tab';
            $variables['tabs'][$tabId] = $tab;
        }

        $variables['tabs']['order-transactions'] = [
            'label' => Craft::t('commerce', 'Transactions'),
            'url' => '#transactionsTab',
            'class' => null,
        ];

        $variables['tabs']['order-history'] = [
            'label' => Craft::t('commerce', 'Status History'),
            'url' => '#orderHistoryTab',
            'class' => null,
        ];

        $variables['fullPageForm'] = true;


        $variables['paymentMethodsAvailable'] = false;

        if (empty($variables['paymentForm'])) {
            $gateway = $order->getGateway();

            if ($gateway && !$gateway instanceof MissingGateway) {
                $variables['paymentForm'] = $gateway->getPaymentFormModel();
            } else {
                $gateway = Plugin::getInstance()->getGateways()->getAllGateways()->first();

                if ($gateway && !$gateway instanceof MissingGateway) {
                    $variables['paymentForm'] = $gateway->getPaymentFormModel();
                }
            }

            if ($gateway instanceof MissingGateway) {
                $variables['paymentMethodsAvailable'] = false;
            }
        }
    }

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    private function _registerJavascript(array $variables): void
    {
        /** @var Order $order */
        $order = $variables['order'];
        Craft::$app->getView()->registerAssetBundle(CommerceOrderAsset::class);
        // Include the input mask asset for use in pricing fields
        Craft::$app->getView()->registerAssetBundle(MoneyAsset::class);

        Craft::$app->getView()->registerJs('window.orderEdit = {};', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.autoSetNewCartAddresses = ' . Json::encode($order->getStore()->getAutoSetNewCartAddresses()) . ';', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.orderId = ' . $order->id . ';', View::POS_BEGIN);

        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($order->storeId)->all();
        Craft::$app->getView()->registerJs('window.orderEdit.orderStatuses = ' . Json::encode($orderStatuses) . ';', View::POS_BEGIN);

        $orderSites = $order->getStore()->getSites()->all();
        Craft::$app->getView()->registerJs('window.orderEdit.orderSites = ' . Json::encode(array_values($orderSites)) . ';', View::POS_BEGIN);

        $lineItemStatuses = Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses($order->storeId)->all();
        Craft::$app->getView()->registerJs('window.orderEdit.lineItemStatuses = ' . Json::encode($lineItemStatuses) . ';', View::POS_BEGIN);

        $lineItemTypes = LineItemType::types();

        Craft::$app->getView()->registerJs('window.orderEdit.lineItemTypes = ' . Json::encode($lineItemTypes) . ';', View::POS_BEGIN);

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategoriesAsList();
        Craft::$app->getView()->registerJs('window.orderEdit.taxCategories = ' . Json::encode(ArrayHelper::toArray($taxCategories)) . ';', View::POS_BEGIN);

        $defaultTaxCategoryId = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
        Craft::$app->getView()->registerJs('window.orderEdit.defaultTaxCategoryId = ' . Json::encode($defaultTaxCategoryId) . ';', View::POS_BEGIN);

        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategoriesAsList($order->storeId);
        Craft::$app->getView()->registerJs('window.orderEdit.shippingCategories = ' . Json::encode(ArrayHelper::toArray($shippingCategories)) . ';', View::POS_BEGIN);

        $defaultShippingCategoryId = Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($order->storeId)->id;
        Craft::$app->getView()->registerJs('window.orderEdit.defaultShippingCategoryId = ' . Json::encode($defaultShippingCategoryId) . ';', View::POS_BEGIN);

        $currentUser = Craft::$app->getUser()->getIdentity();
        $permissions = [
            'commerce-manageOrders' => $currentUser->can('commerce-manageOrders'),
            'commerce-editOrders' => $currentUser->can('commerce-editOrders'),
            'commerce-deleteOrders' => $currentUser->can('commerce-deleteOrders'),
        ];
        Craft::$app->getView()->registerJs('window.orderEdit.currentUserPermissions = ' . Json::encode($permissions) . ';', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.currentUserId = ' . Json::encode($currentUser->id) . ';', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.ordersIndexUrl = "' . UrlHelper::cpUrl('commerce/orders') . '"', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.ordersIndexUrlHashed = "' . Craft::$app->getSecurity()->hashData('commerce/orders') . '"', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.continueEditingUrl = "' . $order->cpEditUrl . '"', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.userPhotoFallback = "' . Craft::$app->getAssetManager()->getPublishedUrl('@app/web/assets/cp/dist', true, 'images/user.svg') . '"', View::POS_BEGIN);

        // Pad the decimal mask with `#` to match the number of decimal places in the currency
        $subUnit = Plugin::getInstance()->getCurrencies()->getSubunitFor($order->currency);
        $formattingLocale = Craft::$app->getFormattingLocale();

        $currencyConfig = [
            'currency' => $order->currency,
            'decimals' => $subUnit,
            'decimalSeparator' => $formattingLocale->getNumberSymbol($formattingLocale::SYMBOL_DECIMAL_SEPARATOR),
            'groupSeparator' => $formattingLocale->getNumberSymbol($formattingLocale::SYMBOL_GROUPING_SEPARATOR),
        ];

        Craft::$app->getView()->registerJs('window.orderEdit.currencyConfig = ' . Json::encode($currencyConfig) , View::POS_BEGIN);

        $customer = $order->customerId ? $order->getCustomer() : null;
        if ($customer) {
            $customer = $this->_customerToArray($customer);
        }

        Craft::$app->getView()->registerJs('window.orderEdit.originalCustomer = ' . Json::encode($customer, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT), View::POS_BEGIN);

        $pdfUrls = Plugin::getInstance()->getPdfs()->getAllEnabledPdfs($order->storeId)->map(fn(Pdf $pdf) => [
            'name' => $pdf->name,
            'url' => $order->getPdfUrl(null, $pdf->handle),
        ])->all();

        Craft::$app->getView()->registerJs('window.orderEdit.pdfUrls = ' . Json::encode($pdfUrls) . ';', View::POS_BEGIN);

        $emails = Plugin::getInstance()->getEmails()->getAllEnabledEmails($order->storeId);
        Craft::$app->getView()->registerJs('window.orderEdit.emailTemplates = ' . Json::encode(ArrayHelper::toArray($emails)) . ';', View::POS_BEGIN);

        $response = [];
        $response['order'] = $this->_orderToArray($order);

        if ($order->hasErrors()) {
            $response['order']['errors'] = $order->getErrors();
            $response['error'] = Craft::t('commerce', 'The order is not valid.');
        }

        Craft::$app->getView()->registerJs('window.orderEdit.data = ' . Json::encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) . ';', View::POS_BEGIN);

        $forceEdit = ($order->hasErrors() || !$order->isCompleted);

        Craft::$app->getView()->registerJs('window.orderEdit.forceEdit = ' . Json::encode($forceEdit) . ';', View::POS_BEGIN);
    }

    /**
     * @param Order $order
     * @param $orderRequestData
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws InvalidElementException
     * @throws UnsupportedSiteException
     */
    private function _updateOrder(Order $order, $orderRequestData, bool $tryAutoSet = true): void
    {
        $order->setRecalculationMode($orderRequestData['order']['recalculationMode']);
        $order->reference = $orderRequestData['order']['reference'];

        $hasSetCustomer = false;
        $customerId = $orderRequestData['order']['customerId'] ?? null;
        if ($customerId && $customer = Craft::$app->getUsers()->getUserById($customerId)) {
            $hasSetCustomer = true;
            $order->setCustomer($customer);
        } else {
            $order->setCustomer();
        }
        $order->couponCode = $orderRequestData['order']['couponCode'];
        $order->isCompleted = $orderRequestData['order']['isCompleted'];
        $order->orderStatusId = $orderRequestData['order']['orderStatusId'];
        $order->orderSiteId = $orderRequestData['order']['orderSiteId'];
        $order->message = $orderRequestData['order']['message'];
        $order->shippingMethodHandle = $orderRequestData['order']['shippingMethodHandle'];
        $order->suppressEmails = $orderRequestData['order']['suppressEmails'] ?? false;

        $submittedBillingAddress = $orderRequestData['order']['billingAddress'] ?? null;
        $submittedShippingAddress = $orderRequestData['order']['shippingAddress'] ?? null;

        if ($tryAutoSet && $hasSetCustomer && $submittedShippingAddress === null && $submittedBillingAddress === null) {
            // Try and auto set addresses if the customer has changed and no address data is submitted
            // Remove any lingering addresses from previous saves
            if (!$order->isCompleted) {
                $order->setBillingAddress(null);
                $order->setShippingAddress(null);
            }

            $order->autoSetAddresses();
        } else {
            $getAddress = static function($address, $orderId, $title) {
                if ($address && ($address['id'] && ($address['ownerId'] != $orderId || isset($address['_copy'])))) {
                    if (isset($address['_copy'])) {
                        unset($address['_copy']);
                    }
                    $address = Craft::$app->getElements()->getElementById($address['id'], Address::class);
                    $address = Craft::$app->getElements()->duplicateElement($address, [
                        'ownerId' => $orderId,
                        'primaryOwnerId' => $orderId,
                        'title' => $title,
                    ]);
                } elseif ($address && ($address['id'] && $address['ownerId'] == $orderId)) {
                    /** @var Address|null $address */
                    $address = Address::find()->ownerId($address['ownerId'])->id($address['id'])->one();
                }

                return $address;
            };
            $billingAddress = $getAddress($submittedBillingAddress, $orderRequestData['order']['id'], Craft::t('commerce', 'Billing Address'));
            $order->setBillingAddress($billingAddress);

            $shippingAddress = $getAddress($submittedShippingAddress, $orderRequestData['order']['id'], Craft::t('commerce', 'Shipping Address'));
            $order->setShippingAddress($shippingAddress);

            if (isset($orderRequestData['order']['sourceBillingAddressId'])) {
                $order->sourceBillingAddressId = $orderRequestData['order']['sourceBillingAddressId'];
            }

            if (isset($orderRequestData['order']['sourceShippingAddressId'])) {
                $order->sourceShippingAddressId = $orderRequestData['order']['sourceShippingAddressId'];
            }
        }

        $shippingMethod = $order->shippingMethodHandle ? Plugin::getInstance()->getShippingMethods()->getShippingMethodByHandle($order->shippingMethodHandle) : null;
        $order->shippingMethodName = $shippingMethod->name ?? null;

        $order->clearNotices();

        // Create Notices on Order
        $notices = [];
        foreach ($orderRequestData['order']['notices'] as $notice) {
            $notices[] = Craft::createObject([
                'class' => OrderNotice::class,
                'attributes' => $notice,
            ]);
        }
        $order->addNotices($notices);

        $dateOrdered = $orderRequestData['order']['dateOrdered'];
        if ($dateOrdered !== null) {
            if ($orderRequestData['order']['dateOrdered']['time'] == '') {
                $dateTime = (new DateTime('now', new DateTimeZone($dateOrdered['timezone'])));
                $dateOrdered['time'] = $dateTime->format('H:i');
            }

            if ($orderRequestData['order']['dateOrdered']['date'] == '' && $orderRequestData['order']['dateOrdered']['time'] == '') {
                $order->dateOrdered = null;
            } else {
                $order->dateOrdered = DateTimeHelper::toDateTime($dateOrdered) ?: null;
            }
        }

        if ($dateOrdered === null && $order->isCompleted) {
            $order->dateOrdered = null;
        }

        // If the customer was changed, the payment source or gateway may not be valid on the order for the new customer and we should unset it.
        try {
            $order->getPaymentSource();
            $order->getGateway();
        } catch (\Exception) {
            $order->paymentSourceId = null;
            $order->gatewayId = null;
        }

        $lineItems = [];
        $adjustments = [];

        foreach ($orderRequestData['order']['lineItems'] as $lineItemData) {
            // Normalize data
            $type = $lineItemData['type'] ?? LineItemType::Purchasable;
            if (is_string($type)) {
                $type = LineItemType::from($type);
            } elseif (is_array($type) && isset($type['value'])) {
                $type = LineItemType::from($type['value']);
            }

            $description = $lineItemData['description'] ?? null;
            $sku = $lineItemData['sku'] ?? null;
            $lineItemId = $lineItemData['id'] ?? null;
            $note = $lineItemData['note'] ?? '';
            $privateNote = $lineItemData['privateNote'] ?? '';
            $purchasableId = $lineItemData['purchasableId'];
            $lineItemStatusId = $lineItemData['lineItemStatusId'];
            $options = $lineItemData['options'] ?? [];
            $qty = $lineItemData['qty'] ?? 1;
            $shippingCategoryId = $lineItemData['shippingCategoryId'] ?? null;
            $taxCategoryId = $lineItemData['taxCategoryId'] ?? null;
            $uid = $lineItemData['uid'] ?? StringHelper::UUID();

            if ($lineItemId) {
                $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);
            } else {
                try {
                    $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order, $purchasableId, $options, $qty, $note, $uid);
                } catch (\Exception $exception) {
                    $order->addError('lineItems', $exception->getMessage());
                    continue;
                }
            }

            $lineItem->type = $type;

            $lineItem->purchasableId = $purchasableId;
            $lineItem->qty = $qty;
            $lineItem->note = $note;
            $lineItem->privateNote = $privateNote;
            $lineItem->lineItemStatusId = $lineItemStatusId;
            $lineItem->setOptions($options);
            $lineItem->uid = $uid;

            $lineItem->setOrder($order);

            if ($lineItem->type === LineItemType::Custom) {
                if ($description) {
                    $lineItem->setDescription($description);
                }

                if ($sku) {
                    $lineItem->setSku($sku);
                }

                if ($shippingCategoryId) {
                    $lineItem->shippingCategoryId = $shippingCategoryId;
                }

                if ($taxCategoryId) {
                    $lineItem->taxCategoryId = $taxCategoryId;
                }
            }

            // Deleted a purchasable while we had a purchasable ID in memory on the order edit page, unset it.
            if ($lineItem->type === LineItemType::Purchasable && $purchasableId && !Plugin::getInstance()->getPurchasables()->getPurchasableById($purchasableId, $orderRequestData['order']['orderSiteId'], $orderRequestData['order']['customerId'] ?? false)) {
                $lineItem->purchasableId = null;
            }

            if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE || $lineItem->type === LineItemType::Custom) {
                $promotionalPrice = $lineItemData['promotionalPrice'] ? Localization::normalizeNumber($lineItemData['promotionalPrice']) : null;
                $price = $lineItemData['price'] ? Localization::normalizeNumber($lineItemData['price']) : 0;

                $lineItem->setPromotionalPrice($promotionalPrice);
                $lineItem->setPrice($price);
            }

            if ($qty !== null && $qty > 0) {
                $lineItems[] = $lineItem;
            }

            if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {
                foreach ($lineItemData['adjustments'] as $adjustmentData) {
                    $id = $adjustmentData['id'];

                    $adjustment = null;
                    if ($id) {
                        $adjustment = Plugin::getInstance()->getOrderAdjustments()->getOrderAdjustmentById($id);
                    }
                    if ($adjustment === null) {
                        $adjustment = new OrderAdjustment();
                    }

                    $adjustment->setOrder($order);
                    $adjustment->setLineItem($lineItem);
                    $adjustment->amount = $adjustmentData['amount'];
                    $adjustment->type = $adjustmentData['type'];
                    $adjustment->name = $adjustmentData['name'];
                    $adjustment->description = $adjustmentData['description'];
                    $adjustment->included = $adjustmentData['included'];
                    $adjustment->setSourceSnapshot($adjustmentData['sourceSnapshot']);

                    $adjustments[] = $adjustment;
                }
            }
        }

        $order->setLineItems($lineItems);

        // Only update the adjustments if the recalculation mode is none (manually updating adjustments)
        if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {
            foreach ($orderRequestData['order']['orderAdjustments'] as $adjustmentData) {
                $id = $adjustmentData['id'];

                $adjustment = null;
                if ($id) {
                    $adjustment = Plugin::getInstance()->getOrderAdjustments()->getOrderAdjustmentById($id);
                }
                if ($adjustment === null) {
                    $adjustment = new OrderAdjustment();
                }

                $adjustment->setOrder($order);
                $adjustment->amount = $adjustmentData['amount'];
                $adjustment->type = $adjustmentData['type'];
                $adjustment->name = $adjustmentData['name'];
                $adjustment->description = $adjustmentData['description'];
                $adjustment->included = $adjustmentData['included'];
                $adjustment->setSourceSnapshot($adjustmentData['sourceSnapshot']);

                $adjustments[] = $adjustment;
            }

            // add all the updated adjustments to the order
            $order->setAdjustments($adjustments);
        }
    }

    /**
     * @param Transaction[] $transactions
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws CurrencyException
     * @since 3.0
     */
    private function _getTransactionsWithLevelsTableArray(array $transactions, int $level = 0): array
    {
        $return = [];
        $user = Craft::$app->getUser()->getIdentity();
        foreach ($transactions as $transaction) {
            if (!ArrayHelper::firstWhere($return, 'id', $transaction->id)) {
                $refundCapture = '';
                if ($user->can('commerce-capturePayment') && $transaction->canCapture()) {
                    $refundCapture = Craft::$app->getView()->renderTemplate(
                        'commerce/orders/includes/_capture',
                        [
                            'currentUser' => $user,
                            'transaction' => $transaction,
                        ]
                    );
                } elseif ($user->can('commerce-refundPayment') && $transaction->canRefund()) {
                    $refundCapture = Craft::$app->getView()->renderTemplate(
                        'commerce/orders/includes/_refund',
                        [
                            'currentUser' => $user,
                            'transaction' => $transaction,
                        ]
                    );
                }

                $transactionResponse = Json::decodeIfJson($transaction->response);
                if (is_array($transactionResponse)) {
                    $transactionResponse = Json::htmlEncode($transactionResponse);
                }

                $transactionMessage = Json::decodeIfJson($transaction->message);
                $transactionMessage = Json::htmlEncode($transactionMessage);

                $return[] = [
                    'id' => $transaction->id,
                    'level' => $level,
                    'type' => [
                        'label' => Html::encode(Craft::t('commerce', StringHelper::toTitleCase($transaction->type))),
                        'level' => $level,
                    ],
                    'status' => [
                        'key' => $transaction->status,
                        'label' => Html::encode(Craft::t('commerce', StringHelper::toTitleCase($transaction->status))),
                    ],
                    'paymentAmount' => $transaction->paymentAmountAsCurrency,
                    'amount' => $transaction->amountAsCurrency,
                    'gateway' => Html::encode($transaction->gateway->name ?? Craft::t('commerce', 'Missing Gateway')),
                    'date' => $transaction->dateUpdated ? $transaction->dateUpdated->format('H:i:s (jS M Y)') : '',
                    'info' => [
                        ['label' => Html::encode(Craft::t('commerce', 'Transaction ID')), 'type' => 'code', 'value' => $transaction->id],
                        ['label' => Html::encode(Craft::t('commerce', 'Transaction Hash')), 'type' => 'code', 'value' => $transaction->hash],
                        ['label' => Html::encode(Craft::t('commerce', 'Gateway Reference')), 'type' => 'code', 'value' => $transaction->reference],
                        ['label' => Html::encode(Craft::t('commerce', 'Gateway Message')), 'type' => 'text', 'value' => $transactionMessage],
                        ['label' => Html::encode(Craft::t('commerce', 'Note')), 'type' => 'text', 'value' => Html::encode($transaction->note)],
                        ['label' => Html::encode(Craft::t('commerce', 'Gateway Code')), 'type' => 'code', 'value' => $transaction->code],
                        ['label' => Html::encode(Craft::t('commerce', 'Converted Price')), 'type' => 'text', 'value' => Plugin::getInstance()->getPaymentCurrencies()->convert($transaction->paymentAmount, $transaction->paymentCurrency) . ' <small class="light">(' . $transaction->currency . ')</small>' . ' <small class="light">(1 ' . $transaction->currency . ' = ' . number_format($transaction->paymentRate) . ' ' . $transaction->paymentCurrency . ')</small>'],
                        ['label' => Html::encode(Craft::t('commerce', 'Gateway Response')), 'type' => 'response', 'value' => $transactionResponse],
                    ],
                    'actions' => $refundCapture,
                ];

                if (!empty($transaction->childTransactions)) {
                    $childTransactions = $this->_getTransactionsWithLevelsTableArray($transaction->childTransactions, $level + 1);

                    foreach ($childTransactions as $childTransaction) {
                        $return[] = $childTransaction;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @throws InvalidConfigException
     */
    private function _addLivePurchasableInfo(array $results, int $siteId, int|false|null $customerId = null): array
    {
        $purchasables = [];

        foreach ($results as $row) {
            /** @var PurchasableInterface|null $purchasable */
            $purchasable = Plugin::getInstance()->getPurchasables()->getPurchasableById($row['id'], $siteId, $customerId);
            if ($purchasable) {
                $baseCurrency = $purchasable->getStore()->getCurrency();
                // @TODO revisit when updating currencies for stores
                $row['price'] = $purchasable->getSalePrice();
                $row['promotionalPrice'] = $purchasable->getPromotionalPrice();
                $row['priceAsCurrency'] = MoneyHelper::toString(MoneyHelper::toMoney(['value' => $purchasable->getSalePrice(), 'currency' => $baseCurrency]));
                $row['isAvailable'] = Plugin::getInstance()->getPurchasables()->isPurchasableAvailable($purchasable);
                $row['detail'] = [
                    'title' => Craft::t('commerce', 'Information'),
                    'content' => $purchasable->getSnapshot(),
                    'showAsList' => true,
                ];
                $row['newLineItemUid'] = StringHelper::UUID();
                $row['newLineItemOptionsSignature'] = LineItem::generateOptionsSignature([]);
                $row['description'] = Html::encode($row['description']);
                $row['sku'] = Html::encode($row['sku']);
                $row['qty'] = '';
                $purchasables[] = $row;
            }
        }

        return $purchasables;
    }


    /**
     * @param User $customer
     * @return array
     * @since 4.0
     */
    private function _customerToArray(User $customer): array
    {
        $totalAddresses = Address::find()->ownerId($customer->id)->count();

        return $customer->toArray(expand: ['photo']) + [
                'cpEditUrl' => $customer->getCpEditUrl(),
                'totalAddresses' => $totalAddresses,
                'photoThumbHtml' => $customer->getThumbHtml(100),

                // @TODO remove when update order edit to use `photoThumbHtml`
                'photoThumbUrl' => '',
            ];
    }

    /**
     * @param Order $order
     * @throws ForbiddenHttpException
     */
    protected function enforceManageOrderPermissions(Order $order): void
    {
        if (!Craft::$app->getElements()->canView($order)) {
            throw new ForbiddenHttpException('User not authorized to view this order.');
        }
    }
}
