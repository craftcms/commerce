<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\helpers\AdminTable;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Cp\SiteSwitcher;
use CraftCms\Cms\Http\Controllers\Concerns\RedirectsToShownSource;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpModalResponse;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Users;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Http\ViewModels\OrderEditViewModel;
use CraftCms\Commerce\Http\ViewModels\OrderIndexViewModel;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderEditGuard;
use CraftCms\Commerce\Order\Edit\OrderEditSessions;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Payment\Exceptions\RefundException;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\Payments;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

class OrdersController
{
    use RedirectsToShownSource;
    use RespondsWithFlash;

    public function index(ElementIndexRequest $request, ?string $orderStatusHandle = null): InertiaResponse|RedirectResponse
    {
        $viewModel = new OrderIndexViewModel($request, $orderStatusHandle);

        if ($viewModel->showSiteMenu()) {
            app(SiteSwitcher::class)->scopeToSite();
        }

        return $this->shownSourceRedirect($request, $viewModel, $orderStatusHandle !== null && $orderStatusHandle !== '')
            ?? Inertia::render('commerce::orders/Index', [$viewModel]);
    }

    public function create(Request $request, string $storeHandle): Response
    {
        $store = app(Stores::class)->getStoreByHandle($storeHandle);
        abort_unless($store !== null, 400, "Invalid store handle: $storeHandle");

        $userId = $request->input('customerId');
        $user = $userId ? Users::getUserById((int)$userId) : null;
        abort_if($userId && !$user, 400, "Invalid user ID: $userId");

        $attributes = [
            'number' => app(Carts::class)->generateCartNumber(),
            'origin' => Order::ORIGIN_CP,
            'storeId' => $store->id,
        ];
        if ($user) {
            $attributes['customer'] = $user;
        }

        $order = new Order();
        $order->setAttributes($attributes);

        if ($user) {
            // Try to set defaults
            $order->autoSetAddresses();
            $order->autoSetShippingMethod();
        }

        abort_unless(Elements::saveElement($order, false), 500, t('Can not create a new order', category: 'commerce'));

        return redirect(Url::cpUrl('commerce/orders/' . $order->id));
    }

    public function edit(int $orderId): InertiaResponse
    {
        $order = app(Orders::class)->getOrderById($orderId);
        abort_if(!$order, 404, t('Can not find order.', category: 'commerce'));

        $this->enforceManageOrderPermissions($order);

        $sessions = app(OrderEditSessions::class);
        $session = $order->isCompleted ? $sessions->getSessionByOrderId($order->id) : null;
        $original = null;
        $sessionError = null;

        if ($session) {
            $original = app(Orders::class)->getOrderById($orderId);

            if ($session->belongsTo(currentUserElement()?->id)) {
                try {
                    $sessions->preview($session, $order);
                } catch (OperationNotApplicableException $exception) {
                    $sessionError = $exception->getMessage();
                    $order = app(Orders::class)->getOrderById($orderId);
                }
            }
        }

        return Inertia::render('commerce::orders/Edit', [new OrderEditViewModel($order, $session, $original, $sessionError)]);
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

        $customer = Users::getUserById((int)$customerId);

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
                in_array($direction, ['asc', 'desc'], true) &&
                in_array($field, [
                    'reference',
                    'dateOrdered',
                    'totalPrice',
                ], true)
            ) {
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

    public function loadCartUrl(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless(currentUser()?->can('commerce-manageOrders'), 403);

        $number = $request->input('number');
        $id = $request->input('id');
        abort_if(!$number && !$id, 400, 'Missing number or id');

        $query = Order::find()->isCompleted(false);
        $cart = ($number ? $query->number($number) : $query->id((int)$id))->one();

        if (!$cart) {
            abort(404, 'Cart not found.');
        }

        return $this->asSuccess(data: [
            'url' => app(Carts::class)->getLoadCartUrl($cart),
        ]);
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

        $order = app(Orders::class)->getOrderById((int)$orderId);
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

        $user = Users::getUserById((int)$userId);

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

    public function indexSourcesBadgeCounts(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $site = \craft\helpers\Cp::requestedSite();
        $storeId = $site?->getStore()?->id;

        $counts = app(OrderStatuses::class)->getOrderCountByStatus($storeId);

        $total = array_reduce($counts, static fn($sum, $thing) => $sum + (int)$thing['orderCount'], 0);

        return $this->asSuccess(data: compact('counts', 'total'));
    }

    public function transactionCapture(Request $request): Response
    {
        $id = $request->input('id');
        $transaction = app(Transactions::class)->getTransactionById((int)$id);
        $this->abortIfEditing($transaction?->getOrder());

        if ($transaction->canCapture()) {
            // capture transaction and display result
            $child = app(Payments::class)->captureTransaction($transaction);

            $message = $child->message ? ' (' . $child->message . ')' : '';

            if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                $child->order?->updateOrderPaidInformation();
                return $this->asSuccess(t('Transaction captured successfully: {message}', ['message' => $message], category: 'commerce'));
            }

            return $this->asFailure(t('Couldn’t capture transaction: {message}', ['message' => $message], category: 'commerce'));
        }

        return $this->asFailure(t('Couldn’t capture transaction.', category: 'commerce'));
    }

    public function transactionRefund(Request $request): Response
    {
        $id = $request->input('id');

        $transaction = app(Transactions::class)->getTransactionById((int)$id);

        if (!$transaction) {
            return $this->asFailure(t('Can not find the transaction to refund', category: 'commerce'));
        }

        $this->abortIfEditing($transaction->getOrder());

        $amount = $request->input('amount');
        $amount = Money::toMoney(array_merge($amount, ['currency' => $transaction->paymentCurrency]));
        $amount = (float)Money::toDecimal($amount);

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
                $child = app(Payments::class)->refundTransaction($transaction, $amount, $note);

                $message = $child->message ? ' (' . $child->message . ')' : '';

                if ($child->status == TransactionRecord::STATUS_SUCCESS || $child->status == TransactionRecord::STATUS_PROCESSING) {
                    $child->order?->updateOrderPaidInformation();
                    return $this->asSuccess(t('Transaction refunded successfully: {message}', ['message' => $message], category: 'commerce'));
                }

                return $this->asFailure(t('Couldn’t refund transaction: {message}', ['message' => $message], category: 'commerce'));
            } catch (RefundException $exception) {
                return $this->asFailure($exception->getMessage());
            }
        }

        return $this->asFailure(t('Couldn’t refund transaction.', category: 'commerce'));
    }

    private function abortIfEditing(?Order $order): void
    {
        if (!$order) {
            return;
        }

        $reason = app(OrderEditGuard::class)->blockReason($order, currentUserElement()?->id);
        abort_if($reason !== null, 423, (string)$reason);
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
                implode('', array_map(fn($id) => Html::hiddenInput('oldUserIds[]', $id), $oldUserIds)))
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
            $count = app(Orders::class)->reassignOrders($oldUserIds, $newUserId);
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
            ->contentHtml(fn() => Html::tag('p', t('Remove customer association and email from the {numOrders, plural, =1{order} other{orders}}. Optionally select additional customer data to remove below', [
                'numOrders' => count($orderIds),
            ], category: 'commerce')) .
                Html::beginTag('div') .
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
                Html::endTag('div') .
                implode('', array_map(fn($id) => Html::hiddenInput('orderIds[]', (string)$id), $orderIds)))
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
            app(Orders::class)->removeCustomerData($orderIds, $dataToRemove);
        } catch (\Exception) {
            return $this->asFailure(t('Unable to remove order data.', category: 'commerce'));
        }

        return $this->asSuccess(t('Order customer data removed.', category: 'commerce'));
    }

    protected function enforceManageOrderPermissions(Order $order): void
    {
        abort_unless(($user = currentUserElement()) && $order->canView($user), 403, 'User not authorized to view this order.');
    }
}
