<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\base\Gateway;
use craft\commerce\base\Purchasable as PurchasableElement;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\db\Table;
use craft\commerce\elements\db\PurchasableQuery;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\LineItemType;
use craft\commerce\enums\OrderNoticeType;
use craft\commerce\errors\RefundException;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\helpers\Locale;
use craft\commerce\helpers\PaymentForm;
use craft\commerce\helpers\Purchasable;
use craft\commerce\models\inventory\InventoryFulfillMovement;
use craft\commerce\models\LineItemStatus;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\Pdf;
use craft\commerce\Plugin;
use craft\commerce\stripe\gateways\PaymentIntents;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\commerce\web\assets\commerceui\CommerceOrderAsset;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\db\ElementQuery;
use craft\helpers\AdminTable;
use craft\helpers\DateTimeHelper;
use craft\helpers\Localization;
use craft\helpers\MoneyHelper;
use craft\web\assets\inputmask\InputmaskAsset;
use craft\web\assets\money\MoneyAsset;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Field\Field;
use CraftCms\Cms\FieldLayout\FieldLayoutCompiler;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormHtmlRenderer;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpModalResponse;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\Events\ModifyPurchasablesTableQueryEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Records\Transaction as TransactionRecord;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery as NewPurchasableQuery;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class OrdersController
{
    use RespondsWithFlash;

    public function orderIndex(string $orderStatusHandle = ''): string
    {
        \Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

        /** @var \craft\models\Site|StoreBehavior $site */
        $site = \craft\helpers\Cp::requestedSite();
        $store = $site->getStore();

        HtmlStack::js('window.orderEdit = {};', Position::BodyBegin);
        $permissions = [
            'commerce-manageOrders' => (bool)currentUser()?->can('commerce-manageOrders'),
            'commerce-editOrders' => (bool)currentUser()?->can('commerce-editOrders'),
            'commerce-deleteOrders' => (bool)currentUser()?->can('commerce-deleteOrders'),
        ];

        HtmlStack::js('window.orderEdit.currentUserPermissions = ' . \craft\helpers\Json::encode($permissions) . ';', Position::BodyBegin);

        return pageTemplate('commerce/orders/_index', compact('orderStatusHandle', 'store'), TemplateMode::Cp);
    }

    public function create(Request $request, string $storeHandle): Response
    {
        $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
        abort_unless($store, 400, "Invalid store handle: $storeHandle");

        $userId = $request->input('customerId');
        $user = $userId ? \Craft::$app->getUsers()->getUserById((int)$userId) : null;
        abort_if($userId && !$user, 400, "Invalid user ID: $userId");

        $attributes = [
            'number' => Plugin::getInstance()->getCarts()->generateCartNumber(),
            'origin' => Order::ORIGIN_CP,
            'storeId' => $store->id,
        ];
        if ($user) {
            $attributes['customer'] = $user;
        }

        $order = \Craft::createObject([
            'class' => Order::class,
            'attributes' => $attributes,
        ]);

        if ($user) {
            // Try to set defaults
            $order->autoSetAddresses();
            $order->autoSetShippingMethod();
        }

        abort_unless(Elements::saveElement($order, false), 500, t('Can not create a new order', category: 'commerce'));

        return redirect('commerce/orders/' . $order->id);
    }

    public function editOrder(int $orderId): string
    {
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));

        $this->enforceManageOrderPermissions($order);

        $variables = [
            'order' => $order,
            'paymentForm' => null,
            'orderId' => $order->id,
        ];

        $transactions = $order->getTransactions();
        $variables['orderTransactions'] = $this->getTransactionsWithLevelsTableArray($transactions);

        $this->updateTemplateVariables($variables);
        $this->registerJavascript($variables);

        return pageTemplate('commerce/orders/_edit', $variables, TemplateMode::Cp);
    }

    public function fulfill(Request $request): Response
    {
        $fulfillments = $request->input('fulfillment');
        $movements = [];
        foreach ($fulfillments as $fulfillment) {
            $qty = (int)$fulfillment['quantity'];
            if ($qty != 0) {
                $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById((int)$fulfillment['inventoryLocationId']);

                $movement = new InventoryFulfillMovement();
                $movement->fromInventoryLocation = $inventoryLocation;
                $movement->inventoryItemId = $fulfillment['inventoryItemId'];
                $movement->toInventoryLocation = $inventoryLocation;
                $movement->fromInventoryTransactionType = InventoryTransactionType::COMMITTED;
                $movement->toInventoryTransactionType = InventoryTransactionType::FULFILLED;
                $movement->lineItemId = $fulfillment['lineItemId'];
                $movement->quantity = $qty;
                $movement->userId = currentUser()?->id;
                $movements[] = $movement;
            }
        }

        foreach ($movements as $movement) {
            if (!$movement->isValid()) {
                return $this->asFailure(t('Invalid inventory movements.', category: 'commerce'), [
                    'errors' => ['fulfillment' => $movement->getErrors()],
                ]);
            }
        }

        /** @var InventoryMovementCollection $movements */
        $movements = InventoryMovementCollection::make($movements);

        if (!Plugin::getInstance()->getInventory()->executeInventoryMovements($movements)) {
            return $this->asFailure(t('Invalid inventory movements.', category: 'commerce'));
        }

        return $this->asSuccess(t('Updated committed stock successfully.', category: 'commerce'));
    }

    public function fulfillmentModal(Request $request): CpModalResponse
    {
        abort_unless($request->expectsJson(), 400);

        $orderId = $request->input('orderId');
        abort_if(!$orderId, 400, 'Missing order id');

        $order = Plugin::getInstance()->getOrders()->getOrderById((int)$orderId);
        $inventoryFulfillmentLevels = Plugin::getInstance()->getInventory()->getInventoryFulfillmentLevels($order)->groupBy('inventoryLocationId');

        return new CpModalResponse()
            ->action('commerce/orders/fulfill')
            ->submitButtonLabel(t('Update'))
            ->contentTemplate('commerce/orders/modals/_fulfillmentModal', [
                'inventoryFulfillmentLevels' => $inventoryFulfillmentLevels,
                'order' => $order,
            ])->prepareModal(function() {
                HtmlStack::jsWithVars(fn() => <<<JS
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
    }

    public function save(Request $request): ?Response
    {
        $data = $request->input('orderData');
        $orderRequestData = \craft\helpers\Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById((int)$orderRequestData['order']['id']);
        abort_if(!$order, 400, t('Invalid Order ID', category: 'commerce'));

        $this->enforceManageOrderPermissions($order);

        // Set custom field values
        $order->setFieldValuesFromRequest('fields');

        $alreadyCompleted = $order->isCompleted;
        // Set data from request to the order
        $this->updateOrder($order, $orderRequestData, false);
        $markAsComplete = !$alreadyCompleted && $order->isCompleted;

        // We don't want to save it as completed yet since we will markAsComplete() after saving the cart
        if ($markAsComplete) {
            $order->isCompleted = false;
            $order->dateOrdered = null;
            $order->orderStatusId = null;
        }

        $order->ruleset->useScenario(ElementRules::SCENARIO_LIVE);
        $valid = $order->validate(null, false);

        if (!$valid || !Elements::saveElement($order, false)) {
            // Recalculation mode should always return to none, unless it is still a cart
            $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
            if (!$order->isCompleted) {
                $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
            }

            return $this->asFailure(t('Couldn\'t save order.', category: 'commerce'));
        }

        // This request is marking the order as complete
        if ($markAsComplete) {
            $order->markAsComplete();
        }

        return $this->redirectToPostedUrl();
    }

    public function deleteOrder(Request $request): ?Response
    {
        $orderId = (int)$request->input('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));

        abort_unless(($user = currentUserElement()) && $order->canDelete($user), 403, 'User not authorized to view this address.');

        if (!Elements::deleteElementById($order->id)) {
            return $this->asFailure();
        }

        return $this->asSuccess(t('Order deleted.', category: 'commerce'));
    }

    public function refresh(Request $request): Response
    {
        $data = $request->getContent();
        $orderRequestData = \craft\helpers\Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById((int)$orderRequestData['order']['id']);

        if (!$order) {
            return $this->asFailure(t('Invalid Order ID', category: 'commerce'));
        }

        $this->enforceManageOrderPermissions($order);

        $this->updateOrder($order, $orderRequestData);

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
                t('The order is not valid.', category: 'commerce'),
                'order',
                [
                    'order' => $this->orderToArray($order),
                ]
            );
        }

        return $this->asSuccess(data: [
            'order' => $this->orderToArray($order),
        ]);
    }

    public function getShippingMethodOptions(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $data = $request->getContent();
        $orderRequestData = \craft\helpers\Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById((int)$orderRequestData['order']['id']);

        if (!$order) {
            return $this->asFailure(t('Invalid Order ID', category: 'commerce'));
        }

        $this->enforceManageOrderPermissions($order);

        $this->updateOrder($order, $orderRequestData);

        if ($order->validate(null, false) && $order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
            $order->recalculate();
        }

        return $this->asSuccess(data: [
            'shippingMethodOptions' => $order->toArray([], ['availableShippingMethodOptions'])['availableShippingMethodOptions'],
        ]);
    }

    public function userOrdersTable(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $page = (int)$request->input('page', 1);
        $sort = $request->input('sort');
        $limit = (int)$request->input('per_page', 10);
        $search = $request->input('search');
        $offset = ($page - 1) * $limit;

        $customerId = $request->query('customerId');

        if (!$customerId) {
            return $this->asFailure(t('Customer ID is required.', category: 'commerce'));
        }

        $customer = \Craft::$app->getUsers()->getUserById((int)$customerId);

        if (!$customer) {
            return $this->asFailure(t('Unable to retrieve customer.', category: 'commerce'));
        }

        $orderQuery = Order::find()
            ->customer($customer)
            ->withAll() // eager-load all related data
            ->isCompleted();

        if ($search) {
            $orderQuery->search($search);
        }

        $orderQuery->orderBy('dateOrdered DESC');
        if ($sort) {
            if (is_array($sort)) {
                $field = $sort[0]['sortField'];
                $direction = $sort[0]['direction'];
            } else {
                [$field, $direction] = explode('|', (string)$sort);
            }

            // Validate sorting
            if (
                !in_array($direction, ['asc', 'desc']) ||
                !in_array($field, [
                    'reference',
                    'dateOrdered',
                    'totalPrice',
                ])
            ) {
                $field = null;
                $direction = null;
            }

            if ($field && $direction) {
                $orderQuery->orderBy($field . ' ' . $direction);
            }
        }

        $total = $orderQuery->count();

        $orderQuery->offset($offset);
        $orderQuery->limit($limit);
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
            'pagination' => AdminTable::paginationLinks($page, (int)$total, $limit),
            'data' => $rows,
        ]);
    }

    public function purchasablesTable(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $page = (int)$request->input('page', 1);
        $sort = $request->input('sort');
        $limit = (int)$request->input('per_page', 10);
        $search = $request->input('search');
        $siteId = $request->query('siteId');
        $customerId = $request->query('customerId', false);
        $customerId = $customerId !== false ? (int)$customerId : false;

        abort_unless($siteId, 400, 'siteId is required');
        $siteId = (int)$siteId;

        $store = Plugin::getInstance()->getStores()->getStoreBySiteId($siteId);
        abort_unless($store, 400, 'Store not found');

        $offset = ($page - 1) * $limit;

        // Prepare purchasables query
        $likeOperator = \Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = new Query()
            ->select(['purchasables.id', 'pstores.basePrice', 'purchasables.description', 'purchasables.sku', 'elements.type'])
            ->leftJoin(['elements' => CraftTable::ELEMENTS], [
                'and',
                '[[elements.id]] = [[purchasables.id]]',
            ])
            // Make sure this purchasable is enabled for the site
            ->innerJoin(['es' => CraftTable::ELEMENTS_SITES], [
                'and',
                '[[es.elementId]] = [[purchasables.id]]',
                '[[es.siteId]] = :siteId',
            ], [
                ':siteId' => $siteId,
            ])
            ->innerJoin(Table::PURCHASABLES_STORES . ' pstores', '[[purchasables.id]] = [[pstores.purchasableId]]')
            ->where(['elements.enabled' => true])
            ->andWhere(['pstores.storeId' => $store->id])
            ->andWhere(['elements.revisionId' => null])
            ->andWhere(['elements.draftId' => null])
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
        $sqlQuery->andWhere(new \yii\db\Expression("LEFT([[purchasables.sku]], " . strlen(Purchasable::TEMPORARY_SKU_PREFIX) . ") != '" . Purchasable::TEMPORARY_SKU_PREFIX . "'"));

        // Do not return soft deleted purchasables
        $sqlQuery->andWhere(['elements.dateDeleted' => null]);

        // Apply sorting if required
        if ($sort && strpos((string)$sort, '|')) {
            [$column, $direction] = explode('|', (string)$sort);

            if (!in_array($column, [
                'description',
                'sku',
                'price',
            ])) {
                $column = null;
            }

            if ($column && in_array($direction, ['asc', 'desc'], true)) {
                $sqlQuery->orderBy([$column => $direction == 'asc' ? SORT_ASC : SORT_DESC]);
            }
        } else {
            $sqlQuery->orderBy(['id' => 'asc']);
        }

        // Trigger event before working out the total and limiting the results for pagination
        $event = new ModifyPurchasablesTableQueryEvent(
            query: $sqlQuery,
            search: $search,
        );
        event($event);
        $sqlQuery = $event->query;

        $total = $sqlQuery->count();

        $sqlQuery->limit($limit);
        $sqlQuery->offset($offset);

        $result = $sqlQuery->all();

        return $this->asSuccess(data: [
            'pagination' => AdminTable::paginationLinks($page, (int)$total, $limit),
            'data' => $this->addLivePurchasableInfo($result, $siteId, $customerId),
        ]);
    }

    public function customerSearch(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $query = $request->query('query');

        $limit = 30;

        if ($query === null) {
            return response()->json([]);
        }

        $userQuery = User::find()->status(null)->limit($limit);

        if ($query) {
            $userQuery->search(urldecode((string)$query));
        }

        $customers = $userQuery->collect()->map(fn(User $user) => $this->customerToArray($user));

        return $this->asSuccess(data: compact('customers'));
    }

    public function getCustomerAddresses(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing user id');

        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('per_page', 10);
        $offset = ($page - 1) * $limit;

        $user = \Craft::$app->getUsers()->getUserById((int)$id);

        if (!$user) {
            return $this->asFailure(t('User not found.', category: 'commerce'));
        }

        $addressElements = Address::find()
            ->ownerId($user->id)
            ->limit($limit)
            ->offset($offset)
            ->collect();

        $total = $addressElements->count();

        $addresses = $addressElements->map(fn(Address $address) => $address->toArray() + [
            'html' => \craft\helpers\Cp::elementCardHtml($address),
        ]);

        return $this->asSuccess(data: compact('addresses', 'total'));
    }

    public function getOrderAddress(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $orderId = $request->input('orderId');
        $addressId = $request->input('addressId');
        abort_if(!$orderId || !$addressId, 400, 'Missing orderId or addressId');

        $order = Plugin::getInstance()->getOrders()->getOrderById((int)$orderId);

        if (!$order) {
            return $this->asFailure(t('Order not found.', category: 'commerce'));
        }

        /** @var Address|null $address */
        $address = Address::find()
            ->ownerId($order->id)
            ->id($addressId)
            ->one();

        if (!$address) {
            return $this->asFailure(t('Address not found.', category: 'commerce'));
        }

        return $this->asSuccess(data: [
            'address' => $address->toArray() + [
                'html' => \craft\helpers\Cp::elementCardHtml($address),
            ],
        ]);
    }

    public function validateAddress(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $attributes = $request->input('address');
        abort_if(!$attributes, 400, 'Missing address');

        $attributes += ['class' => Address::class];

        $address = \Craft::createObject($attributes);

        if (!$address->validate()) {
            return $this->asModelFailure(model: $address, message: t('Unable to validate address.', category: 'commerce'), modelName: 'address');
        }

        return $this->asSuccess();
    }

    public function createCustomer(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $email = $request->input('email');
        abort_if(!$email, 400, 'Missing email');

        try {
            $user = \Craft::$app->getUsers()->ensureUserByEmail($email);
            $user = $this->customerToArray($user);
        } catch (\Exception $e) {
            return $this->asFailure(message: $e->getMessage());
        }

        return $this->asSuccess(data: compact('user'));
    }

    public function getLoadCartUrl(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless(currentUser()?->can('commerce-manageOrders'), 403);

        $number = $request->input('number');
        abort_if(!$number, 400, 'Missing number');

        $cart = Order::find()->number($number)->isCompleted(false)->one();

        if (!$cart) {
            abort(404, 'Cart not found.');
        }

        return $this->asSuccess(data: [
            'url' => Plugin::getInstance()->getCarts()->getLoadCartUrl($cart),
        ]);
    }

    public function sendEmail(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        $orderId = $request->input('orderId');

        if ($id === null || $orderId === null) {
            return $this->asFailure(t('Bad Request', category: 'commerce'));
        }

        $order = Order::find()->id($orderId)->one();
        if ($order === null) {
            return $this->asFailure(t('Can not find order', category: 'commerce'));
        }

        $email = Plugin::getInstance()->getEmails()->getEmailById((int)$id, $order->storeId);
        if ($email === null || !$email->enabled) {
            return $this->asFailure(t('Can not find enabled email.', category: 'commerce'));
        }

        $originalLanguage = \Craft::$app->language;
        $originalFormattingLocale = \Craft::$app->formattingLocale;

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
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale->id);

        if (!$success) {
            $error = $error ?: t('Could not send email', category: 'commerce');
            return $this->asFailure($error);
        }

        return $this->asSuccess();
    }

    public function updateOrderAddress(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $orderId = $request->input('orderId');
        $addressId = $request->input('addressId');
        $type = $request->input('addressType');

        // Validate Address Type
        if (!in_array($type, ['shippingAddress', 'billingAddress'], true)) {
            return $this->asFailure(t('Not a valid address type', category: 'commerce'));
        }

        $order = Plugin::getInstance()->getOrders()->getOrderById((int)$orderId);
        if (!$order) {
            return $this->asFailure(t('Bad order ID.', category: 'commerce'));
        }

        // Return early if the address is already set.
        if ($order->{$type . 'Id'} == $addressId) {
            return $this->asSuccess();
        }

        // Validate Address Id
        $address = $addressId ? Address::find()->id($addressId)->one() : null;
        if (!$address) {
            return $this->asFailure(t('Bad address ID.', category: 'commerce'));
        }

        $order->{$type . 'Id'} = $address->id;

        if (!Elements::saveElement($order)) {
            return $this->asFailure(t('Could not update orders address.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function copyAddressToUser(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $addressId = $request->input('addressId');
        $userId = $request->input('userId');
        abort_if(!$addressId || !$userId, 400, 'Missing addressId or userId');

        $address = Address::find()->id($addressId)->one();

        if (!$address) {
            return $this->asFailure(t('Address not found.', category: 'commerce'));
        }

        $user = \Craft::$app->getUsers()->getUserById((int)$userId);

        if (!$user || !$user->getIsCredentialed()) {
            return $this->asFailure(t('Invalid user.', category: 'commerce'));
        }

        try {
            // Clone the address
            $newAddress = Elements::duplicateElement($address, [
                'owner' => $user,
                'primaryOwner' => $user,
            ]);
        } catch (\Exception $exception) {
            return $this->asFailure($exception->getMessage());
        }

        return $this->asSuccess(data: [
            'address' => $newAddress->toArray(),
        ]);
    }

    public function getIndexSourcesBadgeCounts(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        /** @var \craft\models\Site|StoreBehavior|null $site */
        $site = \craft\helpers\Cp::requestedSite();
        $storeId = $site?->getStore()->id ?? null;

        $counts = Plugin::getInstance()->getOrderStatuses()->getOrderCountByStatus($storeId);

        $total = array_reduce($counts, static fn($sum, $thing) => $sum + (int)$thing['orderCount'], 0);

        return $this->asSuccess(data: compact('counts', 'total'));
    }

    public function getPaymentModal(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $orderId = $request->input('orderId');
        $paymentFormData = $request->input('paymentForm');

        $plugin = Plugin::getInstance();
        $order = $plugin->getOrders()->getOrderById((int)$orderId);
        $gateways = $plugin->getGateways()->getAllGateways();

        if ($paymentAmount = $request->input('paymentAmount')) {
            $order->setPaymentAmount($paymentAmount);
        }
        if ($paymentCurrency = $request->input('paymentCurrency')) {
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
            /** @todo Remove the legacy PaymentIntents `getOldPaymentFormHtml()` branch in Commerce 6.0 */
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

            $paymentFormHtml = template('commerce/_components/gateways/_modalWrapper', [
                'formHtml' => $paymentFormHtml,
                'gateway' => $gateway,
                'paymentForm' => $paymentFormModel,
                'order' => $order,
            ], TemplateMode::Cp);

            $formHtml .= $paymentFormHtml;
        }

        \Craft::$app->getView()->registerAssetBundle(InputmaskAsset::class);

        $modalHtml = template('commerce/orders/_paymentmodal', [
            'gateways' => $gateways,
            'order' => $order,
            'paymentForms' => $formHtml,
        ], TemplateMode::Cp);

        return $this->asSuccess(data: [
            'modalHtml' => $modalHtml,
            'headHtml' => HtmlStack::headHtml(),
            'footHtml' => HtmlStack::bodyHtml(),
        ]);
    }

    public function transactionCapture(Request $request): Response
    {
        $id = $request->input('id');
        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById((int)$id);

        if ($transaction->canCapture()) {
            // capture transaction and display result
            $child = Plugin::getInstance()->getPayments()->captureTransaction($transaction);

            $message = $child->message ? ' (' . $child->message . ')' : '';

            if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                $child->order->updateOrderPaidInformation();
                return $this->asSuccess(t('Transaction captured successfully: {message}', ['message' => $message], category: 'commerce'));
            }

            return $this->asFailure(t('Couldn\'t capture transaction: {message}', ['message' => $message], category: 'commerce'));
        }

        return $this->asFailure(t('Couldn\'t capture transaction.', category: 'commerce'));
    }

    public function transactionRefund(Request $request): Response
    {
        $id = $request->input('id');

        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById((int)$id);

        if (!$transaction) {
            return $this->asFailure(t('Can not find the transaction to refund', category: 'commerce'));
        }

        $amount = $request->input('amount');
        $amount = MoneyHelper::toMoney(array_merge($amount, ['currency' => $transaction->paymentCurrency]));
        $amount = MoneyHelper::toDecimal($amount);

        $note = $request->input('note');
        abort_if($note === null, 400, 'Missing note');

        if (!$amount || $amount <= 0) {
            $amount = $transaction->getRefundableAmount();
        }

        if ($amount <= 0 || $amount > $transaction->getRefundableAmount()) {
            $error = t('Can not refund amount greater than the remaining amount', category: 'commerce');
            return $this->asFailure($error);
        }

        if ($transaction->canRefund()) {
            try {
                // refund transaction and display result
                $child = Plugin::getInstance()->getPayments()->refundTransaction($transaction, $amount, $note);

                $message = $child->message ? ' (' . $child->message . ')' : '';

                if ($child->status == TransactionRecord::STATUS_SUCCESS || $child->status == TransactionRecord::STATUS_PROCESSING) {
                    $child->order->updateOrderPaidInformation();
                    return $this->asSuccess(t('Transaction refunded successfully: {message}', ['message' => $message], category: 'commerce'));
                }

                return $this->asFailure(t('Couldn\'t refund transaction: {message}', ['message' => $message], category: 'commerce'));
            } catch (RefundException $exception) {
                return $this->asFailure($exception->getMessage());
            }
        }

        return $this->asFailure(t('Couldn\'t refund transaction.', category: 'commerce'));
    }

    public function paymentAmountData(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $paymentCurrencies = Plugin::getInstance()->getPaymentCurrencies();
        $paymentCurrency = $request->input('paymentCurrency');
        $paymentAmount = $request->input('paymentAmount');
        $locale = $request->input('locale');
        $orderId = $request->input('orderId');
        abort_if(!$paymentCurrency || !$paymentAmount || !$locale || !$orderId, 400, 'Missing required param');

        /** @var Order $order */
        $order = Order::find()->id($orderId)->one();
        $baseCurrency = $order->currency;

        $paymentAmount = MoneyHelper::toMoney(['value' => $paymentAmount, 'currency' => $baseCurrency, 'locale' => $locale]);
        $paymentAmount = MoneyHelper::toDecimal($paymentAmount);

        $baseCurrencyPaymentAmount = $paymentCurrencies->convertCurrency((float)$paymentAmount, $paymentCurrency, $baseCurrency);
        $baseCurrencyPaymentAmountAsCurrency = t('Pay {amount} of {currency} on the order.', ['amount' => Currency::formatAsCurrency($baseCurrencyPaymentAmount, $baseCurrency), 'currency' => $baseCurrency], category: 'commerce');

        $outstandingBalance = $order->outstandingBalance;
        $outstandingBalanceAsCurrency = $order->outstandingBalanceAsCurrency;

        $message = '';
        if (Currency::round($baseCurrencyPaymentAmount) > Currency::round($outstandingBalance)) {
            $baseCurrencyPaymentAmount = $outstandingBalance;
            $baseCurrencyPaymentAmountAsCurrency = t('Pay {amount} of {currency} on the order.', ['amount' => $outstandingBalanceAsCurrency, 'currency' => $baseCurrency], category: 'commerce');
            $message = t('Order payment balance is {outstandingBalanceAsCurrency}. This is the maximum value that will be charged.', ['outstandingBalanceAsCurrency' => $outstandingBalanceAsCurrency], category: 'commerce');
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

    public function reassignModal(Request $request): CpModalResponse
    {
        abort_unless($request->expectsJson(), 400);

        $oldUserIds = $request->input('oldUserIds');
        abort_if(!$oldUserIds, 400, 'Missing oldUserIds');

        return new CpModalResponse()
            ->action('commerce/orders/reassign')
            ->contentHtml(fn() => \craft\helpers\Cp::elementSelectFieldHtml([
                'label' => t('Choose a new customer', category: 'commerce'),
                'name' => 'newUserId',
                'elementType' => User::class,
                'criteria' => [
                    'id' => array_map(fn($id) => "not $id", $oldUserIds),
                ],
                'single' => true,
            ]) .
                implode('', array_map(fn($id) => \craft\helpers\Html::hiddenInput('oldUserIds[]', $id), $oldUserIds)))
            ->submitButtonLabel(t('Reassign'));
    }

    public function reassign(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $oldUserIds = array_map(fn($id) => (int)$id, $request->input('oldUserIds'));
        $newUserId = (int)$request->input('newUserId');

        if (!$newUserId) {
            return $this->asFailure(t('No new customer selected.', category: 'commerce'));
        }

        try {
            $count = Plugin::getInstance()->getOrders()->reassignOrders($oldUserIds, $newUserId);
        } catch (\Exception) {
            return $this->asFailure(t('Unable to reassign orders.', category: 'commerce'));
        }

        return $this->asSuccess(t('{type} reassigned.', [
            'type' => $count === 1 ? Order::displayName() : Order::pluralDisplayName(),
        ]));
    }

    public function removeCustomerDataModal(Request $request): CpModalResponse
    {
        abort_unless($request->expectsJson(), 400);

        $orderIds = array_map(fn($id) => (int)$id, $request->input('orderIds'));

        return new CpModalResponse()
            ->action('commerce/orders/remove-customer-data')
            ->contentHtml(fn() => \craft\helpers\Html::tag('p', t('Remove customer association and email from the {numOrders, plural, =1{order} other{orders}}. Optionally select additional customer data to remove below', [
                'numOrders' => count($orderIds),
            ], category: 'commerce')) .
                \craft\helpers\Html::beginTag('div') .
                \craft\helpers\Cp::checkboxSelectFieldHtml([
                    'label' => t('Customer data', category: 'commerce'),
                    'name' => 'customerData',
                    'options' => [
                        'billingAddressId' => t('Billing Address', category: 'commerce'),
                        'shippingAddressId' => t('Shipping Address', category: 'commerce'),
                        'orderCompletedEmail' => t('Completed Email', category: 'commerce'),
                    ],
                    'values' => null,
                    'showAllOption' => true,
                ]) .
                \craft\helpers\Html::endTag('div') .
                implode('', array_map(fn($id) => \craft\helpers\Html::hiddenInput('orderIds[]', (string)$id), $orderIds)))
            ->submitButtonLabel(t('Remove customer data', category: 'commerce'));
    }

    public function removeCustomerData(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $orderIds = array_map(fn($id) => (int)$id, $request->input('orderIds'));
        $customerData = $request->input('customerData', []);
        $customerData = $customerData === '' ? [] : $customerData;

        $customerData = $customerData === '*' ? ['billingAddressId', 'shippingAddressId', 'orderCompletedEmail'] : $customerData;

        $dataToRemove = array_merge(['customerId', 'email'], $customerData);

        try {
            Plugin::getInstance()->getOrders()->removeCustomerData($orderIds, $dataToRemove);
        } catch (\Exception) {
            return $this->asFailure(t('Unable to remove order data.', category: 'commerce'));
        }

        return $this->asSuccess(t('Order customer data removed.', category: 'commerce'));
    }

    private function orderToArray(Order $order): array
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
            \craft\helpers\ArrayHelper::removeValue($orderFields, $removeProp);
        }

        if (($fieldLayout = $order->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                /** @var Field $field */
                \craft\helpers\ArrayHelper::removeValue($orderFields, $field->handle);
            }
        }

        $extraFields = [
            'lineItems.snapshot',
            'billingAddress',
            'shippingAddress',
            'orderSite',
            'notices',
            'adminNotices',
            'loadCartUrl',
            'store',
            'totalCommittedStock',
            'lineItems.fulfilledTotalQuantity',
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

            if ($purchasable instanceof Variant) {
                $product = $purchasable->getOwner();
                $purchasableCpEditUrlByPurchasableId[$purchasable->id] = $product?->getCpEditUrl() ?? null;
            } else {
                $purchasableCpEditUrlByPurchasableId[$purchasable->id] = $purchasable->getCpEditUrl();
            }
        }

        $purchasableCpEditUrlByPurchasableId = array_filter($purchasableCpEditUrlByPurchasableId);

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $subUnit = Plugin::getInstance()->getCurrencies()->getSubunitFor($order->currency);

        $orderArray = $order->toArray($orderFields, $extraFields);

        if ($orderArray['customer'] && $orderArray['customer']['id'] && $customer = \Craft::$app->getUsers()->getUserById($orderArray['customer']['id'])) {
            $orderArray['customer'] = $this->customerToArray($customer);
        }

        if ($billingAddress) {
            $orderArray['billingAddressHtml'] = \craft\helpers\Cp::elementCardHtml($billingAddress, [
                'showEditButton' => false,
            ]);
        }

        if ($shippingAddress) {
            $orderArray['shippingAddressHtml'] = \craft\helpers\Cp::elementCardHtml($shippingAddress, [
                'showEditButton' => false,
            ]);
        }

        if (!empty($orderArray['lineItems'])) {
            foreach ($orderArray['lineItems'] as &$lineItem) {
                $lineItem['price'] = $lineItem['price'] !== null ? \Craft::$app->getFormatter()->asDecimal($lineItem['price'], $subUnit) : null;
                $lineItem['promotionalPrice'] = $lineItem['promotionalPrice'] !== null ? \Craft::$app->getFormatter()->asDecimal($lineItem['promotionalPrice'], $subUnit) : null;

                $lineItem['showForm'] = \craft\helpers\ArrayHelper::isAssociative($lineItem['options']) || (is_array($lineItem['options']) && empty($lineItem['options']));
                $lineItem['purchasableCpEditUrl'] = $purchasableCpEditUrlByPurchasableId[$lineItem['purchasableId']] ?? null;
            }
            unset($lineItem);
        }

        return $orderArray;
    }

    private function updateTemplateVariables(array &$variables): void
    {
        /** @var Order $order */
        $order = $variables['order'];

        $variables['ordersBodyClass'] = ' commerceorders-post-57';

        $variables['title'] = t('Order', category: 'commerce') . ' ' . $order->reference;

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_CP) {
            $variables['title'] = t('New Order', category: 'commerce');
        }

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_WEB) {
            $variables['title'] = t('Cart {number}', ['number' => $order->getShortNumber()], category: 'commerce');
        }

        $fieldLayout = $order->getFieldLayout();
        // The legacy FieldLayout::createForm()/FieldLayoutForm API is gone — form building now
        // goes through FieldLayoutCompiler (produces an immutable FormPayload) + FormHtmlRenderer
        // (renders that payload to HTML/tab data), matching cms-6's own EditElementController::
        // prepareEditor(). There's no more tabIdPrefix (namespace alone drives both input names
        // and DOM ids) — the static (read-only) form gets its own namespace so its tab ids never
        // collide with the dynamic form's; the dynamic (editable) form is left unnamespaced so its
        // submitted field names still match what setFieldValuesFromRequest('fields') expects.
        $renderer = app(FormHtmlRenderer::class);

        $staticPayload = app(FieldLayoutCompiler::class)->compile(
            $fieldLayout,
            $order,
            new FormContext(
                namespace: 'static_fields',
                mode: ControlMode::ReadOnly,
            ),
        );
        $dynamicPayload = app(FieldLayoutCompiler::class)->compile(
            $fieldLayout,
            $order,
            new FormContext(
                errors: $order->errors()->getMessages(),
                mode: ControlMode::Editable,
                refreshable: true,
            ),
        );

        $variables['staticFieldsHtml'] = $renderer->render($staticPayload);
        $variables['dynamicFieldsHtml'] = $renderer->render($dynamicPayload);

        $variables['tabs'] = [];

        $variables['tabs']['order-details'] = [
            'label' => t('Order Details', category: 'commerce'),
            'url' => '#orderDetailsTab',
            'class' => null,
        ];

        foreach ($renderer->tabMenu($staticPayload) as $tabId => $tab) {
            $tab['class'] .= ' custom-tab static';
            $variables['tabs'][$tabId] = $tab;
        }

        foreach ($renderer->tabMenu($dynamicPayload) as $tabId => $tab) {
            $tab['class'] .= ' custom-tab';
            $variables['tabs'][$tabId] = $tab;
        }

        $variables['tabs']['order-transactions'] = [
            'label' => t('Transactions', category: 'commerce'),
            'url' => '#transactionsTab',
            'class' => null,
        ];

        $variables['tabs']['order-history'] = [
            'label' => t('Status History', category: 'commerce'),
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

    private function registerJavascript(array $variables): void
    {
        /** @var Order $order */
        $order = $variables['order'];
        \Craft::$app->getView()->registerAssetBundle(CommerceOrderAsset::class);
        // Include the input mask asset for use in pricing fields
        \Craft::$app->getView()->registerAssetBundle(MoneyAsset::class);

        HtmlStack::js('window.orderEdit = {};', Position::BodyBegin);

        HtmlStack::js('window.orderEdit.autoSetNewCartAddresses = ' . \craft\helpers\Json::encode($order->getStore()->getAutoSetNewCartAddresses()) . ';', Position::BodyBegin);

        HtmlStack::js('window.orderEdit.orderId = ' . $order->id . ';', Position::BodyBegin);

        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($order->storeId)
            ->map(fn(OrderStatus $orderStatus) => $orderStatus->toArray(expand: ['uiLabel']))
            ->all();
        HtmlStack::js('window.orderEdit.orderStatuses = ' . \craft\helpers\Json::encode($orderStatuses) . ';', Position::BodyBegin);

        $orderSites = $order->getStore()->getSites()->all();
        HtmlStack::js('window.orderEdit.orderSites = ' . \craft\helpers\Json::encode(array_values($orderSites)) . ';', Position::BodyBegin);

        $lineItemStatuses = Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses($order->storeId)
            ->map(fn(LineItemStatus $lineItemStatus) => $lineItemStatus->toArray(expand: ['uiLabel']))
            ->all();

        HtmlStack::js('window.orderEdit.lineItemStatuses = ' . \craft\helpers\Json::encode($lineItemStatuses) . ';', Position::BodyBegin);

        $lineItemTypes = LineItemType::types();

        HtmlStack::js('window.orderEdit.lineItemTypes = ' . \craft\helpers\Json::encode($lineItemTypes) . ';', Position::BodyBegin);

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategoriesAsList();
        HtmlStack::js('window.orderEdit.taxCategories = ' . \craft\helpers\Json::encode(\craft\helpers\ArrayHelper::toArray($taxCategories)) . ';', Position::BodyBegin);

        $defaultTaxCategoryId = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
        HtmlStack::js('window.orderEdit.defaultTaxCategoryId = ' . \craft\helpers\Json::encode($defaultTaxCategoryId) . ';', Position::BodyBegin);

        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategoriesAsList($order->storeId);
        HtmlStack::js('window.orderEdit.shippingCategories = ' . \craft\helpers\Json::encode(\craft\helpers\ArrayHelper::toArray($shippingCategories)) . ';', Position::BodyBegin);

        $defaultShippingCategoryId = Plugin::getInstance()->getShippingCategories()->getDefaultShippingCategory($order->storeId)->id;
        HtmlStack::js('window.orderEdit.defaultShippingCategoryId = ' . \craft\helpers\Json::encode($defaultShippingCategoryId) . ';', Position::BodyBegin);

        $currentUser = currentUserElement();

        $permissions = \craft\helpers\ArrayHelper::map([
            'editUsers',
            'commerce-manageOrders',
            'commerce-editOrders',
            'commerce-deleteOrders',
        ], fn($permission) => $permission, fn($permission) => (bool)$currentUser?->can($permission));

        HtmlStack::js('window.orderEdit.currentUserPermissions = ' . \craft\helpers\Json::encode($permissions) . ';', Position::BodyBegin);
        HtmlStack::js('window.orderEdit.currentUserId = ' . \craft\helpers\Json::encode($currentUser?->id) . ';', Position::BodyBegin);

        HtmlStack::js('window.orderEdit.ordersIndexUrl = "' . \craft\helpers\UrlHelper::cpUrl('commerce/orders') . '"', Position::BodyBegin);
        HtmlStack::js('window.orderEdit.ordersIndexUrlHashed = "' . \Craft::$app->getSecurity()->hashData('commerce/orders') . '"', Position::BodyBegin);
        HtmlStack::js('window.orderEdit.continueEditingUrl = "' . $order->cpEditUrl . '"', Position::BodyBegin);
        HtmlStack::js('window.orderEdit.userPhotoFallback = "' . \Craft::$app->getAssetManager()->getPublishedUrl('@app/web/assets/cp/dist', true, 'images/user.svg') . '"', Position::BodyBegin);

        // Pad the decimal mask with `#` to match the number of decimal places in the currency
        $subUnit = Plugin::getInstance()->getCurrencies()->getSubunitFor($order->currency);
        $formattingLocale = \Craft::$app->getFormattingLocale();

        $currencyConfig = [
            'currency' => $order->currency,
            'decimals' => $subUnit,
            'decimalSeparator' => $formattingLocale->getNumberSymbol($formattingLocale::SYMBOL_DECIMAL_SEPARATOR),
            'groupSeparator' => $formattingLocale->getNumberSymbol($formattingLocale::SYMBOL_GROUPING_SEPARATOR),
        ];

        HtmlStack::js('window.orderEdit.currencyConfig = ' . \craft\helpers\Json::encode($currencyConfig), Position::BodyBegin);

        $customer = $order->customerId ? $order->getCustomer() : null;
        if ($customer) {
            $customer = $this->customerToArray($customer);
        }

        HtmlStack::js('window.orderEdit.originalCustomer = ' . \craft\helpers\Json::encode($customer, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT), Position::BodyBegin);

        $pdfUrls = Plugin::getInstance()->getPdfs()->getAllEnabledPdfs($order->storeId)->map(fn(Pdf $pdf) => [
            'name' => $pdf->name,
            'url' => $order->getPdfUrl(null, $pdf->handle),
        ])->all();

        HtmlStack::js('window.orderEdit.pdfUrls = ' . \craft\helpers\Json::encode($pdfUrls) . ';', Position::BodyBegin);

        $emails = Plugin::getInstance()->getEmails()->getAllEnabledEmails($order->storeId);
        // Reset keys in case any have been removed, so the JS doesn't think it is an object
        $emails = array_values($emails->all());
        HtmlStack::js('window.orderEdit.emailTemplates = ' . \craft\helpers\Json::encode(\craft\helpers\ArrayHelper::toArray($emails)) . ';', Position::BodyBegin);

        $response = [];
        $response['order'] = $this->orderToArray($order);

        if ($order->hasErrors()) {
            $response['order']['errors'] = $order->getErrors();
            $response['errors'] = $order->getErrors();
            $response['error'] = t('The order is not valid.', category: 'commerce');
        }

        HtmlStack::js('window.orderEdit.data = ' . \craft\helpers\Json::encode($response, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT) . ';', Position::BodyBegin);

        $forceEdit = ($order->hasErrors() || !$order->isCompleted);

        HtmlStack::js('window.orderEdit.forceEdit = ' . \craft\helpers\Json::encode($forceEdit) . ';', Position::BodyBegin);

        $store = $order->getStore();
        HtmlStack::js('window.orderEdit.store = ' . \craft\helpers\Json::encode($store->toArray([], ['settings.locationAddress'])) . ';', Position::BodyBegin);
    }

    private function updateOrder(Order $order, $orderRequestData, bool $tryAutoSet = true): void
    {
        $order->setRecalculationMode($orderRequestData['order']['recalculationMode']);
        $order->reference = $orderRequestData['order']['reference'];

        $hasSetCustomer = false;
        $customerId = $orderRequestData['order']['customerId'] ?? null;
        if ($customerId && $customer = \Craft::$app->getUsers()->getUserById((int)$customerId)) {
            $hasSetCustomer = true;
            $order->setCustomer($customer);
        } else {
            $order->setCustomer();
        }
        $order->couponCode = $orderRequestData['order']['couponCode'];
        $order->isCompleted = $orderRequestData['order']['isCompleted'];
        $order->orderStatusId = $orderRequestData['order']['orderStatusId'];
        $order->orderSiteId = $orderRequestData['order']['orderSiteId'];

        // Set the order language based on the `orderSiteId`
        if ($site = \Craft::$app->getSites()->getSiteById($order->orderSiteId)) {
            $order->orderLanguage = $site->language;
        }

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
            $getAddress = static function($address, Order $order, $title) {
                if ($address && ($address['id'] && ($address['ownerId'] != $order->id || isset($address['_copy'])))) {
                    if (isset($address['_copy'])) {
                        unset($address['_copy']);
                    }
                    $address = Elements::getElementById($address['id'], Address::class);
                    $address = Elements::duplicateElement($address, [
                        'owner' => $order,
                        'primaryOwner' => $order,
                        'title' => $title,
                    ]);
                } elseif ($address && ($address['id'] && $address['ownerId'] == $order->id)) {
                    /** @var Address|null $address */
                    $address = Address::find()->ownerId($address['ownerId'])->id($address['id'])->one();
                }

                return $address;
            };
            $billingAddress = $getAddress($submittedBillingAddress, $order, t('Billing Address', category: 'commerce'));
            $order->setBillingAddress($billingAddress);

            $shippingAddress = $getAddress($submittedShippingAddress, $order, t('Shipping Address', category: 'commerce'));
            $order->setShippingAddress($shippingAddress);

            if (array_key_exists('sourceBillingAddressId', $orderRequestData['order'])) {
                $order->sourceBillingAddressId = $orderRequestData['order']['sourceBillingAddressId'];
            }

            if (array_key_exists('sourceShippingAddressId', $orderRequestData['order'])) {
                $order->sourceShippingAddressId = $orderRequestData['order']['sourceShippingAddressId'];
            }
        }

        if (!$order->shippingMethodHandle) {
            // If no shipping method or it is being removed nullify the name
            $order->shippingMethodName = null;
        } elseif (!empty($orderRequestData['order']['shippingMethodName'])) {
            // If the shipping method name is being submitted, use it.
            // This is particularly useful for custom shipping methods as they can't be retrieved from the DB via their handle
            $order->shippingMethodName = $orderRequestData['order']['shippingMethodName'];
        } else {
            // Fallback to attempting to retrieve the shipping method
            $shippingMethod = Plugin::getInstance()->getShippingMethods()->getShippingMethodByHandle($order->shippingMethodHandle);
            if ($shippingMethod) {
                $order->shippingMethodName = $shippingMethod->name ?? null;
            }
        }

        // CP save has full control over all notices including admin ones
        $order->clearNotices(noticeTypes: [OrderNoticeType::Customer, OrderNoticeType::Admin]);

        // Create Notices on Order
        $notices = [];
        foreach ($orderRequestData['order']['notices'] ?? [] as $notice) {
            $notices[] = \Craft::createObject([
                'class' => OrderNotice::class,
                'attributes' => array_merge($notice, ['noticeType' => OrderNoticeType::Customer]),
            ]);
        }
        foreach ($orderRequestData['order']['adminNotices'] ?? [] as $notice) {
            $notices[] = \Craft::createObject([
                'class' => OrderNotice::class,
                'attributes' => array_merge($notice, ['noticeType' => OrderNoticeType::Admin]),
            ]);
        }
        $order->addNotices($notices);

        $dateOrdered = $orderRequestData['order']['dateOrdered'];
        if ($dateOrdered !== null) {
            if ($orderRequestData['order']['dateOrdered']['time'] == '') {
                $dateTime = new DateTime('now', new DateTimeZone($dateOrdered['timezone']));
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
            $hasFreeShipping = $lineItemData['hasFreeShipping'] ?? null;
            $isPromotable = $lineItemData['isPromotable'] ?? null;
            $isShippable = $lineItemData['isShippable'] ?? null;
            $isTaxable = $lineItemData['isTaxable'] ?? null;
            $uid = $lineItemData['uid'] ?? \craft\helpers\StringHelper::UUID();

            if ($lineItemId) {
                $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);
            } else {
                try {
                    $params = compact('options', 'qty', 'note', 'uid');
                    if ($type === LineItemType::Purchasable) {
                        $params['purchasableId'] = $purchasableId;
                    }

                    $lineItem = Plugin::getInstance()->getLineItems()->create($order, $params, $type);
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

                if ($hasFreeShipping !== null) {
                    $lineItem->setHasFreeShipping($hasFreeShipping);
                }

                if ($isPromotable !== null) {
                    $lineItem->setIsPromotable($isPromotable);
                }

                if ($isShippable !== null) {
                    $lineItem->setIsShippable($isShippable);
                }

                if ($isTaxable !== null) {
                    $lineItem->setIsTaxable($isTaxable);
                }
            }

            // Deleted a purchasable while we had a purchasable ID in memory on the order edit page, unset it.
            $customerIdForPurchasable = $orderRequestData['order']['customerId'] ?? false;
            if ($lineItem->type === LineItemType::Purchasable && $purchasableId && !Plugin::getInstance()->getPurchasables()->getPurchasableById((int)$purchasableId, (int)$orderRequestData['order']['orderSiteId'], $customerIdForPurchasable !== false ? (int)$customerIdForPurchasable : false)) {
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

    private function getTransactionsWithLevelsTableArray(array $transactions, int $level = 0): array
    {
        $return = [];
        $user = currentUserElement();
        foreach ($transactions as $transaction) {
            if (!\craft\helpers\ArrayHelper::firstWhere($return, 'id', $transaction->id)) {
                $refundCapture = '';
                if ($user?->can('commerce-capturePayment') && $transaction->canCapture()) {
                    $refundCapture = template(
                        'commerce/orders/includes/_capture',
                        [
                            'currentUser' => $user,
                            'transaction' => $transaction,
                        ],
                        TemplateMode::Cp,
                    );
                } elseif ($user?->can('commerce-refundPayment') && $transaction->canRefund()) {
                    $refundCapture = template(
                        'commerce/orders/includes/_refund',
                        [
                            'currentUser' => $user,
                            'transaction' => $transaction,
                        ],
                        TemplateMode::Cp,
                    );
                }

                $transactionResponse = \craft\helpers\Json::decodeIfJson($transaction->response);
                if (is_array($transactionResponse)) {
                    $transactionResponse = \craft\helpers\Json::htmlEncode($transactionResponse);
                }

                $transactionMessage = \craft\helpers\Json::decodeIfJson($transaction->message);
                $transactionMessage = \craft\helpers\Json::htmlEncode($transactionMessage);

                $return[] = [
                    'id' => $transaction->id,
                    'level' => $level,
                    'type' => [
                        'label' => \craft\helpers\Html::encode(t(\craft\helpers\StringHelper::toTitleCase($transaction->type), category: 'commerce')),
                        'level' => $level,
                    ],
                    'status' => [
                        'key' => $transaction->status,
                        'label' => \craft\helpers\Html::encode(t(\craft\helpers\StringHelper::toTitleCase($transaction->status), category: 'commerce')),
                    ],
                    'paymentAmount' => $transaction->paymentAmountAsCurrency,
                    'amount' => $transaction->amountAsCurrency,
                    'gateway' => \craft\helpers\Html::encode($transaction->gateway->name ?? t('Missing Gateway', category: 'commerce')),
                    'date' => $transaction->dateUpdated ? $transaction->dateUpdated->format('H:i:s (jS M Y)') : '',
                    'info' => [
                        ['label' => \craft\helpers\Html::encode(t('Transaction ID', category: 'commerce')), 'type' => 'code', 'value' => $transaction->id],
                        ['label' => \craft\helpers\Html::encode(t('Transaction Hash', category: 'commerce')), 'type' => 'code', 'value' => $transaction->hash],
                        ['label' => \craft\helpers\Html::encode(t('Gateway Reference', category: 'commerce')), 'type' => 'code', 'value' => $transaction->reference],
                        ['label' => \craft\helpers\Html::encode(t('Gateway Message', category: 'commerce')), 'type' => 'text', 'value' => $transactionMessage],
                        ['label' => \craft\helpers\Html::encode(t('Note', category: 'commerce')), 'type' => 'text', 'value' => \craft\helpers\Html::encode($transaction->note)],
                        ['label' => \craft\helpers\Html::encode(t('Gateway Code', category: 'commerce')), 'type' => 'code', 'value' => $transaction->code],
                        ['label' => \craft\helpers\Html::encode(t('Converted Price', category: 'commerce')), 'type' => 'text', 'value' => $transaction->paymentAmountAsCurrency . ' <small class="light">(1 ' . $transaction->currency . ' = ' . $transaction->paymentRate . ' ' . $transaction->paymentCurrency . ')</small>'],
                        ['label' => \craft\helpers\Html::encode(t('Gateway Response', category: 'commerce')), 'type' => 'response', 'value' => $transactionResponse],
                    ],
                    'actions' => $refundCapture,
                ];

                if (!empty($transaction->childTransactions)) {
                    $childTransactions = $this->getTransactionsWithLevelsTableArray($transaction->childTransactions, $level + 1);

                    foreach ($childTransactions as $childTransaction) {
                        $return[] = $childTransaction;
                    }
                }
            }
        }

        return $return;
    }

    private function addLivePurchasableInfo(array $results, int $siteId, int|false|null $customerId = null): array
    {
        $purchasables = [];
        $store = Plugin::getInstance()->getStores()->getStoreBySiteId($siteId);
        $baseCurrency = $store->getCurrency();

        $elementIdsByType = [];
        foreach ($results as $r) {
            if (!array_key_exists((string)$r['type'], $elementIdsByType)) {
                $elementIdsByType[$r['type']] = [];
            }
            $elementIdsByType[$r['type']][] = $r['id'];
        }

        $purchasablesById = [];
        foreach ($elementIdsByType as $type => $ids) {
            if (!class_exists($type)) {
                continue;
            }

            /** @var ElementQuery $query */
            $query = $type::find();

            if ($type::isLocalized()) {
                $query->siteId($siteId);
            }

            $query->status(null);

            // Donation (migrated) returns the new PurchasableQuery; Product/Variant (not yet migrated) still return the legacy one.
            if ($query instanceof PurchasableQuery || $query instanceof NewPurchasableQuery) {
                $query->forCustomer($customerId);
            }

            $purchasablesById = [...$purchasablesById, ...$query->id($ids)->all()];
        }

        foreach ($results as $row) {
            /** @var PurchasableInterface|null $purchasable */
            $purchasable = \craft\helpers\ArrayHelper::firstWhere($purchasablesById, 'id', $row['id']);
            if ($purchasable) {
                // @TODO Revisit purchasable price lookup once per-store currency handling is finalized
                $row['price'] = $purchasable->getSalePrice();
                $row['promotionalPrice'] = $purchasable->getPromotionalPrice();
                $row['priceAsCurrency'] = MoneyHelper::toString(MoneyHelper::toMoney(['value' => $purchasable->getSalePrice(), 'currency' => $baseCurrency]));
                $row['isAvailable'] = Plugin::getInstance()->getPurchasables()->isPurchasableAvailable($purchasable);
                $row['detail'] = [
                    'title' => t('Information', category: 'commerce'),
                    'content' => $purchasable->getSnapshot(),
                    'showAsList' => true,
                ];
                $row['newLineItemUid'] = \craft\helpers\StringHelper::UUID();
                $row['newLineItemOptionsSignature'] = LineItemHelper::generateOptionsSignature([]);
                $row['description'] = \craft\helpers\Html::encode($row['description']);
                $row['sku'] = \craft\helpers\Html::encode($row['sku']);
                $row['qty'] = '';
                $purchasables[] = $row;
            }
        }

        return $purchasables;
    }

    private function customerToArray(User $customer): array
    {
        $totalAddresses = Address::find()->ownerId($customer->id)->count();

        return $customer->toArray(expand: ['photo']) + [
            'cpEditUrl' => $customer->getCpEditUrl(),
            'totalAddresses' => $totalAddresses,
            'photoThumbHtml' => $customer->getThumbHtml(100),

            // @TODO Remove `photoThumbUrl` once the order edit Vue UI is updated to use `photoThumbHtml` instead
            'photoThumbUrl' => '',
        ];
    }

    protected function enforceManageOrderPermissions(Order $order): void
    {
        abort_unless(($user = currentUserElement()) && $order->canView($user), 403, 'User not authorized to view this order.');
    }
}
