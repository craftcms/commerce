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
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\RefundException;
use craft\commerce\errors\TransactionException;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\CustomerAddress;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\commerce\web\assets\commerceui\CommerceOrderAsset;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\AdminTable;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\web\Controller;
use craft\web\View;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;
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
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function init()
    {
        $this->requirePermission('commerce-manageOrders');

        parent::init();
    }

    /**
     * Index of orders
     *
     * @param string $orderStatusHandle
     * @return Response
     * @throws Throwable
     */
    public function actionOrderIndex(string $orderStatusHandle = ''): Response
    {
        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

        Craft::$app->getView()->registerJs('window.orderEdit = {};', View::POS_BEGIN);
        $permissions = [
            'commerce-editOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-editOrders'),
            'commerce-deleteOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-deleteOrders'),
        ];
        Craft::$app->getView()->registerJs('window.orderEdit.currentUserPermissions = ' . Json::encode($permissions) . ';', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.edition = "' . Plugin::getInstance()->edition . '"', View::POS_BEGIN);

        return $this->renderTemplate('commerce/orders/_index', compact('orderStatusHandle'));
    }

    /**
     * Create an order
     */
    public function actionNewOrder(): Response
    {
        $this->requirePermission('commerce-editOrders');

        $customerId = Craft::$app->getRequest()->getParam('customerId', null);

        $order = new Order();
        $order->number = Plugin::getInstance()->getCarts()->generateCartNumber();

        if (!$customerId || !$customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId)) {
            $customer = new Customer();
            Plugin::getInstance()->getCustomers()->saveCustomer($customer);
        }

        $order->customerId = $customer->id;
        $order->origin = Order::ORIGIN_CP;

        if (!Craft::$app->getElements()->saveElement($order)) {
            throw new Exception(Plugin::t('Can not create a new order'));
        }

        return $this->redirect('commerce/orders/' . $order->id);
    }

    /**
     * @param int $orderId
     * @param Order $order
     * @return Response
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEditOrder($orderId, Order $order = null): Response
    {
        $this->requirePermission('commerce-editOrders');

        $plugin = Plugin::getInstance();
        $variables = [];

        if ($order === null && $orderId) {
            $order = $plugin->getOrders()->getOrderById($orderId);

            if (!$order) {
                throw new HttpException(404, Plugin::t('Can not find order.'));
            }
        }

        $variables['order'] = $order;
        $variables['orderId'] = $order->id;
        $variables['fieldLayout'] = Craft::$app->getFields()->getLayoutByType(Order::class);

        $transactions = $order->getTransactions();

        $variables['orderTransactions'] = $this->_getTransactionsWIthLevelsTableArray($transactions);

        $this->_updateTemplateVariables($variables);
        $this->_registerJavascript($variables);

        return $this->renderTemplate('commerce/orders/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requirePermission('commerce-editOrders');
        $this->requirePostRequest();

        $data = Craft::$app->getRequest()->getBodyParam('orderData');

        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            throw new HttpException(400, Plugin::t('Invalid Order ID'));
        }

        // Set custom field values
        $order->setFieldValuesFromRequest('fields');

        $alreadyCompleted = $order->isCompleted;
        // Set data from request to the order
        $this->_updateOrder($order, $orderRequestData);
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

            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save order.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'order' => $order
            ]);

            return null;
        }

        // This request is marking the order as complete
        if ($markAsComplete) {
            $order->markAsComplete();
        }

        $this->redirectToPostedUrl();
    }

    /**
     * Deletes an order.
     *
     * @return Response|null
     * @throws Exception if you try to edit a non existing Id.
     * @throws Throwable
     */
    public function actionDeleteOrder()
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-deleteOrders');

        $orderId = (int)Craft::$app->getRequest()->getRequiredBodyParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new HttpException(404, Plugin::t('Can not find order.'));
        }

        if (!Craft::$app->getElements()->deleteElementById($order->id)) {
            return $this->asJson(['success' => false]);
        }

        Craft::$app->getSession()->setNotice(Plugin::t('Order deleted.'));

        return $this->asJson(['success' => true]);
    }

    /**
     * The refresh action accepts a json representation of an order, recalculates it depending on the mode submitted,
     * and returns the order as json with any validation errors.
     *
     * @return Response
     * @throws Exception
     */
    public function actionRefresh()
    {
        $this->requirePermission('commerce-editOrders');

        $data = Craft::$app->getRequest()->getRawBody();
        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            return $this->asErrorJson(Plugin::t('Invalid Order ID'));
        }

        $this->_updateOrder($order, $orderRequestData);

        if ($order->validate(null, false) && $order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
            $order->recalculate(); // dont save, just recalculate
        }

        // Recalculation mode should always return to none, unless it is still a cart
        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        if (!$order->isCompleted) {
            $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        }

        $response = [];
        $response['order'] = $this->_orderToArray($order);

        if ($order->hasErrors()) {
            $response['order']['errors'] = $order->getErrors();
            $response['error'] = Plugin::t('The order is not valid.');
        }

        return $this->asJson($response);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
     */
    public function actionUserOrdersTable(): Response
    {
        $this->requirePermission('commerce-manageOrders');
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $page = $request->getParam('page', 1);
        $sort = $request->getParam('sort', null);
        $limit = $request->getParam('per_page', 10);
        $search = $request->getParam('search', null);
        $offset = ($page - 1) * $limit;

        $customerId = $request->getQueryParam('customerId', null);

        if (!$customerId) {
            return $this->asErrorJson(Plugin::t('Customer ID is required.'));
        }

        $customer = Plugin::getInstance()->getCustomers()->getCustomerById($customerId);

        if (!$customer) {
            return $this->asErrorJson(Plugin::t('Unable to retrieve customer.'));
        }

        $orderQuery = Order::find()
            ->customer($customer)
            ->isCompleted();

        if ($search) {
            $orderQuery->search($search);
        }

        if ($sort) {
            list($field, $direction) = explode('|', $sort);

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
                'total' => Craft::$app->getFormatter()->asCurrency($order->getTotalPaid(), $order->currency, [], [], false),
                'orderStatus' => $order->getOrderStatusHtml(),
            ];
        }

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $rows,
        ]);
    }


    /**
     * @param Order $order
     * @return array
     */
    private function _orderToArray($order)
    {
        // Remove custom fields
        $orderFields = array_keys($order->fields());

        sort($orderFields);

        // Remove unneeded fields
        ArrayHelper::removeValue($orderFields, 'hasDescendants');
        ArrayHelper::removeValue($orderFields, 'makePrimaryShippingAddress');
        ArrayHelper::removeValue($orderFields, 'shippingSameAsBilling');
        ArrayHelper::removeValue($orderFields, 'billingSameAsShipping');
        ArrayHelper::removeValue($orderFields, 'tempId');
        ArrayHelper::removeValue($orderFields, 'resaving');
        ArrayHelper::removeValue($orderFields, 'duplicateOf');
        ArrayHelper::removeValue($orderFields, 'totalDescendants');
        ArrayHelper::removeValue($orderFields, 'fieldLayoutId');
        ArrayHelper::removeValue($orderFields, 'contentId');
        ArrayHelper::removeValue($orderFields, 'trashed');
        ArrayHelper::removeValue($orderFields, 'structureId');
        ArrayHelper::removeValue($orderFields, 'url');
        ArrayHelper::removeValue($orderFields, 'ref');
        ArrayHelper::removeValue($orderFields, 'title');
        ArrayHelper::removeValue($orderFields, 'slug');

        if ($order::hasContent() && ($fieldLayout = $order->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getFields() as $field) {
                /** @var Field $field */
                ArrayHelper::removeValue($orderFields, $field->handle);
            }
        }

        // Typecast order attributes
        $order->typeCastAttributes();

        $extraFields = ['lineItems.snapshot', 'availableShippingMethods', 'billingAddress', 'shippingAddress'];

        $orderArray = $order->toArray($orderFields, $extraFields);

        if (!empty($orderArray['lineItems'])) {
            foreach ($orderArray['lineItems'] as &$lineItem) {
                $lineItem['showForm'] = ArrayHelper::isAssociative($lineItem['options']) || (is_array($lineItem['options']) && empty($lineItem['options']));
            }
            unset($lineItem);
        }

        return $orderArray;
    }

    /**
     * @param null $query
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionPurchasableSearch($query = null)
    {

        if ($query === null) {
            $results = (new Query())
                ->select(['id', 'price', 'description', 'sku'])
                ->from('{{%commerce_purchasables}}')
                ->limit(10)
                ->all();
            if (!$results) {
                return $this->asJson([]);
            }

            $purchasables = $this->_addLivePurchasableInfo($results);

            return $this->asJson($purchasables);
        }

        // Prepare purchasables query
        $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = (new Query())
            ->select(['id', 'price', 'description', 'sku'])
            ->from(Table::PURCHASABLES);

        // Are they searching for a purchasable ID?
        if (is_numeric($query)) {
            $results = $sqlQuery->where(['id' => $query])->all();
            if (!$results) {
                return $this->asJson([]);
            }

            $purchasables = $this->_addLivePurchasableInfo($results);

            return $this->asJson($purchasables);
        }

        // Are they searching for a SKU or purchasable description?
        if ($query) {
            $sqlQuery->where([
                'or',
                [$likeOperator, 'description', $query],
                [$likeOperator, 'sku', $query]
            ]);
        }

        $results = $sqlQuery->limit(30)->all();

        if (!$results) {
            return $this->asJson([]);
        }

        $purchasables = $this->_addLivePurchasableInfo($results);

        return $this->asJson($purchasables);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     */
    public function actionPurchasablesTable()
    {
        $this->requirePermission('commerce-editOrders');
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $page = $request->getParam('page', 1);
        $sort = $request->getParam('sort', null);
        $limit = $request->getParam('per_page', 10);
        $search = $request->getParam('search', null);
        $offset = ($page - 1) * $limit;

        // Prepare purchasables query
        $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = (new Query())
            ->select(['id', 'price', 'description', 'sku'])
            ->from(Table::PURCHASABLES);

        // Are they searching for a SKU or purchasable description?
        if ($search) {
            $sqlQuery->where([
                'or',
                [$likeOperator, 'description', $search],
                [$likeOperator, 'sku', $search]
            ]);
        }

        $total = $sqlQuery->count();

        $sqlQuery->limit($limit);
        $sqlQuery->offset($offset);
        $result = $sqlQuery->all();

        $rows = [];

        // Add the currency formatted price
        $baseCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        foreach ($result as $row) {
            /** @var PurchasableInterface $purchasable */
            if ($purchasable = Craft::$app->getElements()->getElementById($row['id'])) {
                $row['priceAsCurrency'] = Craft::$app->getFormatter()->asCurrency($row['price'], $baseCurrency, [], [], true);
                $row['isAvailable'] = $purchasable->getIsAvailable();
                $rows[] = $row;
            }
        }

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $rows,
        ]);
    }

    /**
     * @param null $query
     * @return Response
     */
    public function actionCustomerSearch($query = null)
    {
        $limit = 30;
        $customers = [];

        if ($query === null) {
            return $this->asJson($customers);
        }

        $customersQuery = Plugin::getInstance()->getCustomers()->getCustomersQuery($query);

        $customersQuery->limit($limit);

        $customers = $customersQuery->all();

        $customers = $this->_prepCustomersArray($customers);

        return $this->asJson($customers);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionSendEmail()
    {
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getParam('id');
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        if ($id === null || $orderId === null) {
            return $this->asErrorJson(Plugin::t('Bad Request'));
        }

        $email = Plugin::getInstance()->getEmails()->getEmailById($id);
        $order = Order::find()->id($orderId)->one();

        if ($email === null) {
            return $this->asErrorJson(Plugin::t('Can not find email'));
        }

        if ($order === null) {
            return $this->asErrorJson(Plugin::t('Can not find order'));
        }

        $success = true;
        try {
            if (!Plugin::getInstance()->getEmails()->sendEmail($email, $order)) {
                $success = false;
            }
        } catch (\Exception $exception) {
            $success = false;
        }

        if (!$success) {
            return $this->asErrorJson(Plugin::t('Could not send email'));
        }

        return $this->asJson(['success' => true]);
    }

    /**
     * Updates an order address
     *
     * @return Response
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws BadRequestHttpException
     */
    public function actionUpdateOrderAddress()
    {
        $this->requireAcceptsJson();

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $addressId = Craft::$app->getRequest()->getParam('addressId');
        $type = Craft::$app->getRequest()->getParam('addressType');

        // Validate Address Type
        if (!in_array($type, ['shippingAddress', 'billingAddress'], true)) {
            $this->asErrorJson(Plugin::t('Not a valid address type'));
        }

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order) {
            $this->asErrorJson(Plugin::t('Bad order ID.'));
        }

        // Return early if the address is already set.
        if ($order->{$type . 'Id'} == $addressId) {
            return $this->asJson(['success' => true]);
        }

        // Validate Address Id
        $address = $addressId ? Plugin::getInstance()->getAddresses()->getAddressById($addressId) : null;
        if (!$address) {
            return $this->asErrorJson(Plugin::t('Bad address ID.'));
        }

        $order->{$type . 'Id'} = $address->id;

        if (Craft::$app->getElements()->saveElement($order)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Plugin::t('Could not update orders address.'));
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @since 3.0.11
     */
    public function actionGetIndexSourcesBadgeCounts(): Response
    {
        $this->requireAcceptsJson();

        $counts = Plugin::getInstance()->getOrderStatuses()->getOrderCountByStatus();

        $total = array_reduce($counts, static function($sum, $thing) {
            return $sum + (int)$thing['orderCount'];
        }, 0);

        return $this->asJson(compact('counts', 'total'));
    }

    /**
     * Returns Payment Modal
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function actionGetPaymentModal(): Response
    {
        $this->requireAcceptsJson();
        $view = $this->getView();

        $request = Craft::$app->getRequest();
        $orderId = $request->getParam('orderId');
        $paymentFormData = $request->getParam('paymentForm');

        $plugin = Plugin::getInstance();
        $order = $plugin->getOrders()->getOrderById($orderId);
        $gateways = $plugin->getGateways()->getAllGateways();

        $formHtml = '';
        /** @var Gateway $gateway */
        foreach ($gateways as $key => $gateway) {
            // If gateway adapter does no support backend cp payments.
            if (!$gateway->cpPaymentsEnabled() || $gateway instanceof MissingGateway) {
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

            $paymentFormHtml = $gateway->getPaymentFormHtml([
                'paymentForm' => $paymentFormModel,
                'order' => $order
            ]);

            $paymentFormHtml = $view->renderTemplate('commerce/_components/gateways/_modalWrapper', [
                'formHtml' => $paymentFormHtml,
                'gateway' => $gateway,
                'paymentForm' => $paymentFormModel,
                'order' => $order
            ]);

            $formHtml .= $paymentFormHtml;
        }

        $modalHtml = $view->renderTemplate('commerce/orders/_paymentmodal', [
            'gateways' => $gateways,
            'order' => $order,
            'paymentForms' => $formHtml,
        ]);

        return $this->asJson([
            'success' => true,
            'modalHtml' => $modalHtml,
            'headHtml' => $view->getHeadHtml(),
            'footHtml' => $view->getBodyHtml(),
        ]);
    }

    /**
     * Captures Transaction
     *
     * @return Response
     * @throws TransactionException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionTransactionCapture(): Response
    {
        $this->requirePermission('commerce-capturePayment');
        $this->requirePostRequest();
        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById($id);

        if ($transaction->canCapture()) {
            // capture transaction and display result
            $child = Plugin::getInstance()->getPayments()->captureTransaction($transaction);

            $message = $child->message ? ' (' . $child->message . ')' : '';

            if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                $child->order->updateOrderPaidInformation();
                Craft::$app->getSession()->setNotice(Plugin::t('Transaction captured successfully: {message}', [
                    'message' => $message
                ]));
            } else {
                Craft::$app->getSession()->setError(Plugin::t('Couldn’t capture transaction: {message}', [
                    'message' => $message
                ]));
            }
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t capture transaction.', ['id' => $id]));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Refunds transaction.
     *
     * @return Response
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionTransactionRefund()
    {
        $this->requirePermission('commerce-refundPayment');
        $this->requirePostRequest();
        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById($id);

        $amount = Craft::$app->getRequest()->getParam('amount');
        $amount = Localization::normalizeNumber($amount);
        $note = Craft::$app->getRequest()->getRequiredBodyParam('note');

        if (!$transaction) {
            $error = Plugin::t('Can not find the transaction to refund');
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asErrorJson($error);
            } else {
                Craft::$app->getSession()->setError($error);
                return $this->redirectToPostedUrl();
            }
        }

        if (!$amount) {
            $amount = $transaction->getRefundableAmount();
        }

        if ($amount > $transaction->getRefundableAmount()) {
            $error = Plugin::t('Can not refund amount greater than the remaining amount');
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asErrorJson($error);
            } else {
                Craft::$app->getSession()->setError($error);
                return $this->redirectToPostedUrl();
            }
        }

        if ($transaction->canRefund()) {
            try {
                // refund transaction and display result
                $child = Plugin::getInstance()->getPayments()->refundTransaction($transaction, $amount, $note);

                $message = $child->message ? ' (' . $child->message . ')' : '';

                if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                    $child->order->updateOrderPaidInformation();
                    Craft::$app->getSession()->setNotice(Plugin::t('Transaction refunded successfully: {message}', [
                        'message' => $message
                    ]));
                } else {
                    Craft::$app->getSession()->setError(Plugin::t('Couldn’t refund transaction: {message}', [
                        'message' => $message
                    ]));
                }
            } catch (RefundException $exception) {
                Craft::$app->getSession()->setError($exception->getMessage());
            }
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t refund transaction.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Modifies the variables of the request.
     *
     * @param $variables
     * @throws InvalidConfigException
     */
    private function _updateTemplateVariables(&$variables)
    {
        /** @var Order $order */
        $order = $variables['order'];

        $variables['title'] = Plugin::t('Order') . ' ' . $order->reference;

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_CP) {
            $variables['title'] = Plugin::t('New Order');
        }

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_WEB) {
            $variables['title'] = Plugin::t('Cart') . ' ' . $order->getShortNumber();
        }

        $variables['tabs'] = [];

        $variables['tabs'][] = [
            'label' => Plugin::t('Order Details'),
            'url' => '#orderDetailsTab',
            'class' => null
        ];

        /** @var FieldLayout $fieldLayout */
        $fieldLayout = $variables['fieldLayout'];
        foreach ($fieldLayout->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($order->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($order->getErrors($field->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $classes = ['custom-tab'];

            if ($hasErrors) {
                $classes[] = 'errors';
            }

            $variables['tabs'][] = [
                'label' => Plugin::t($tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => implode(' ', $classes)
            ];

            // Add the static version of the custom fields.
            $classes[] = 'static';
            $variables['tabs'][] = [
                'label' => Plugin::t($tab->name),
                'url' => '#tab' . ($index + 1) . 'Static',
                'class' => implode(' ', $classes)
            ];
        }

        $variables['tabs'][] = [
            'label' => Plugin::t('Transactions'),
            'url' => '#transactionsTab',
            'class' => null
        ];

        $variables['tabs'][] = [
            'label' => Plugin::t('Status History'),
            'url' => '#orderHistoryTab',
            'class' => null
        ];

        $variables['fullPageForm'] = true;


        $variables['paymentMethodsAvailable'] = false;

        if (empty($variables['paymentForm'])) {
            /** @var Gateway $gateway */
            $gateway = $order->getGateway();

            if ($gateway && !$gateway instanceof MissingGateway) {
                $variables['paymentForm'] = $gateway->getPaymentFormModel();
            } else {
                $gateway = ArrayHelper::firstValue(Plugin::getInstance()->getGateways()->getAllGateways());

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
     * @param array $variables
     * @throws InvalidConfigException
     */
    private function _registerJavascript(array $variables)
    {
        Craft::$app->getView()->registerAssetBundle(CommerceOrderAsset::class);

        Craft::$app->getView()->registerJs('window.orderEdit = {};', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.orderId = ' . $variables['order']->id . ';', View::POS_BEGIN);

        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        Craft::$app->getView()->registerJs('window.orderEdit.orderStatuses = ' . Json::encode(ArrayHelper::toArray($orderStatuses)) . ';', View::POS_BEGIN);

        $lineItemStatuses = Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses();
        Craft::$app->getView()->registerJs('window.orderEdit.lineItemStatuses = ' . Json::encode(array_values($lineItemStatuses)) . ';', View::POS_BEGIN);

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategoriesAsList();
        Craft::$app->getView()->registerJs('window.orderEdit.taxCategories = ' . Json::encode(ArrayHelper::toArray($taxCategories)) . ';', View::POS_BEGIN);

        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategoriesAsList();
        Craft::$app->getView()->registerJs('window.orderEdit.shippingCategories = ' . Json::encode(ArrayHelper::toArray($shippingCategories)) . ';', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.edition = "' . Plugin::getInstance()->edition . '"', View::POS_BEGIN);

        $permissions = [
            'commerce-editOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-editOrders'),
            'commerce-deleteOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-deleteOrders'),
        ];
        Craft::$app->getView()->registerJs('window.orderEdit.currentUserPermissions = ' . Json::encode($permissions) . ';', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.ordersIndexUrl = "' . UrlHelper::cpUrl('commerce/orders') . '"', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.ordersIndexUrlHashed = "' . Craft::$app->getSecurity()->hashData('commerce/orders') . '"', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.continueEditingUrl = "' . $variables['order']->cpEditUrl . '"', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.userPhotoFallback = "' . Craft::$app->getAssetManager()->getPublishedUrl('@app/web/assets/cp/dist', true, 'images/user.svg') . '"', View::POS_BEGIN);

        $customer = null;
        if ($variables['order']->customerId) {
            $customerQuery = Plugin::getInstance()->getCustomers()->getCustomersQuery()->andWhere(['customers.id' => $variables['order']->customerId]);
            $customers = $this->_prepCustomersArray($customerQuery->all());

            if (!empty($customers)) {
                $customer = ArrayHelper::firstValue($customers);
            }
        }
        Craft::$app->getView()->registerJs('window.orderEdit.originalCustomer = ' . Json::encode($customer), View::POS_BEGIN);

        $statesList = Plugin::getInstance()->getStates()->getAllEnabledStatesAsListGroupedByCountryId();

        if (!empty($statesList)) {
            foreach ($statesList as &$states) {
                foreach ($states as $key => &$state) {
                    $state = [
                        'id' => $key,
                        'name' => $state,
                    ];
                }
                $states = array_values($states);
            }
        }

        Craft::$app->getView()->registerJs('window.orderEdit.statesByCountryId = ' . Json::encode($statesList), View::POS_BEGIN);
        $countries = Plugin::getInstance()->getCountries()->getAllEnabledCountries();
        $countries = array_values(ArrayHelper::toArray($countries, ['id', 'name']));
        Craft::$app->getView()->registerJs('window.orderEdit.countries = ' . Json::encode($countries), View::POS_BEGIN);

        // TODO when we support multiple PDF templates, retrieve them all from a service
        $pdfUrls = [
            [
                'name' => 'Download PDF',
                'url' => $variables['order']->getPdfUrl()
            ]
        ];
        Craft::$app->getView()->registerJs('window.orderEdit.pdfUrls = ' . Json::encode(ArrayHelper::toArray($pdfUrls)) . ';', View::POS_BEGIN);

        $emails = Plugin::getInstance()->getEmails()->getAllEnabledEmails();
        Craft::$app->getView()->registerJs('window.orderEdit.emailTemplates = ' . Json::encode(ArrayHelper::toArray($emails)) . ';', View::POS_BEGIN);

        $response = [];
        $response['order'] = $this->_orderToArray($variables['order']);

        if ($variables['order']->hasErrors()) {
            $response['order']['errors'] = $variables['order']->getErrors();
            $response['error'] = Plugin::t('The order is not valid.');
        }

        Craft::$app->getView()->registerJs('window.orderEdit.data = ' . Json::encode($response) . ';', View::POS_BEGIN);

        $forceEdit = ($variables['order']->hasErrors() || !$variables['order']->isCompleted);

        Craft::$app->getView()->registerJs('window.orderEdit.forceEdit = ' . Json::encode($forceEdit) . ';', View::POS_BEGIN);
    }

    /**
     * @param Order $order
     * @param $orderRequestData
     * @throws Exception
     * @throws InvalidConfigException
     */
    private function _updateOrder(Order $order, $orderRequestData)
    {
        $order->setRecalculationMode($orderRequestData['order']['recalculationMode']);
        $order->reference = $orderRequestData['order']['reference'];
        $order->email = $orderRequestData['order']['email'] ?? '';
        $order->customerId = $orderRequestData['order']['customerId'] ?? null;
        $order->couponCode = $orderRequestData['order']['couponCode'];
        $order->isCompleted = $orderRequestData['order']['isCompleted'];
        $order->orderStatusId = $orderRequestData['order']['orderStatusId'];
        $order->message = $orderRequestData['order']['message'];
        $order->shippingMethodHandle = $orderRequestData['order']['shippingMethodHandle'];

        $dateOrdered = $orderRequestData['order']['dateOrdered'];
        if ($dateOrdered !== null) {

            if ($orderRequestData['order']['dateOrdered']['time'] == '') {
                $dateTime = (new \DateTime('now', new \DateTimeZone($dateOrdered['timezone'])));
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

        // Only email set on the order
        if ($order->customerId == null && $order->email) {
            // See if there is a user with that email
            $user = User::find()->email($order->email)->one();
            $customer = null;
            if ($user) {
                $customer = Plugin::getInstance()->getCustomers()->getCustomerByUserId($user->id);
            }
            // If no user or customer
            if ($customer == null) {
                $customer = new Customer();
                Plugin::getInstance()->getCustomers()->saveCustomer($customer);
            }

            $order->customerId = $customer->id;
        }

        // If the customer was changed, the payment source or gateway may not be valid on the order for the new customer and we should unset it.
        try {
            $order->getPaymentSource();
            $order->getGateway();
        } catch (\Exception $e) {
            $order->paymentSourceId = null;
            $order->gatewayId = null;
        }

        // Addresses
        $billingAddressId = $orderRequestData['order']['billingAddressId'];
        $shippingAddressId = $orderRequestData['order']['shippingAddressId'];
        $billingAddress = null;
        $shippingAddress = null;

        // We need to create a new address if it belongs to a customer and the order is completed
        if ($billingAddressId && $order->isCompleted) {
            $belongsToCustomer = CustomerAddress::find()
                ->where(['addressId' => $billingAddressId])
                ->andWhere(['not', ['customerId' => null]])
                ->exists();

            if ($belongsToCustomer) {
                $billingAddressId = 'new';
            }
        }

        if ($shippingAddressId && $order->isCompleted) {
            $belongsToCustomer = CustomerAddress::find()
                ->where(['addressId' => $shippingAddressId])
                ->andWhere(['not', ['customerId' => null]])
                ->exists();

            if ($belongsToCustomer) {
                $shippingAddressId = 'new';
            }
        }

        if ($billingAddressId == 'new' || (isset($orderRequestData['order']['billingAddress']['id']) && $billingAddressId == $orderRequestData['order']['billingAddress']['id'])) {
            $billingAddress = Plugin::getInstance()->getAddresses()->removeReadOnlyAttributesFromArray($orderRequestData['order']['billingAddress']);
            $billingAddress['isEstimated'] = false;
            $billingAddress = new Address($billingAddress);

            $billingAddress->id = ($billingAddressId == 'new') ? null : $billingAddress->id;

            // TODO figure out if we need to validate at this point;
            Plugin::getInstance()->getAddresses()->saveAddress($billingAddress, false);
            $billingAddressId = $billingAddress->id;
        }

        if ($shippingAddressId == 'new' || (isset($orderRequestData['order']['shippingAddress']['id']) && $shippingAddressId == $orderRequestData['order']['shippingAddress']['id'])) {
            $shippingAddress = Plugin::getInstance()->getAddresses()->removeReadOnlyAttributesFromArray($orderRequestData['order']['shippingAddress']);
            $shippingAddress['isEstimated'] = false;
            $shippingAddress = new Address($shippingAddress);

            $shippingAddress->id = ($shippingAddressId == 'new') ? null : $shippingAddress->id;

            // TODO figure out if we need to validate at this point;
            Plugin::getInstance()->getAddresses()->saveAddress($shippingAddress, false);
            $shippingAddressId = $shippingAddress->id;
        }

        $order->billingAddressId = $billingAddressId;
        $order->shippingAddressId = $shippingAddressId;

        if ($billingAddress) {
            $order->setBillingAddress($billingAddress);
        }

        if ($shippingAddress) {
            $order->setShippingAddress($shippingAddress);
        }

        $lineItems = [];
        $adjustments = [];

        foreach ($orderRequestData['order']['lineItems'] as $lineItemData) {
            // Normalize data
            $lineItemId = $lineItemData['id'] ?? null;
            $note = $lineItemData['note'] ?? '';
            $privateNote = $lineItemData['privateNote'] ?? '';
            $purchasableId = $lineItemData['purchasableId'];
            $lineItemStatusId = $lineItemData['lineItemStatusId'];
            $options = $lineItemData['options'] ?? [];
            $qty = $lineItemData['qty'] ?? 1;

            $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

            if (!$lineItem) {
                try {
                    $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order->id, $purchasableId, $options, $qty, $note);
                } catch (\Exception $exception) {
                    $order->addError('lineItems', $exception->getMessage());
                    continue;
                }
            }

            $lineItem->purchasableId = $purchasableId;
            $lineItem->qty = $qty;
            $lineItem->note = $note;
            $lineItem->privateNote = $privateNote;
            $lineItem->lineItemStatusId = $lineItemStatusId;
            $lineItem->setOptions($options);

            $lineItem->setOrder($order);

            /** @var Purchasable $purchasable */
            if ($purchasable = Craft::$app->getElements()->getElementById($purchasableId)) {
                $lineItem->setPurchasable($purchasable);
                if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
                    $lineItem->refreshFromPurchasable();
                }
            }

            if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {
                $lineItem->salePrice = $lineItemData['salePrice'];
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

                $adjustments[] = $adjustment;
            }

            // add all the updated adjustments to the order
            $order->setAdjustments($adjustments);
        }
    }

    /**
     * @param Transaction[] $transactions
     * @param int $level
     * @return array
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws CurrencyException
     * @since 3.0
     */
    private function _getTransactionsWIthLevelsTableArray($transactions, $level = 0): array
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
                } else if ($user->can('commerce-refundPayment') && $transaction->canRefund()) {
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

                $return[] = [
                    'id' => $transaction->id,
                    'level' => $level,
                    'type' => [
                        'label' => Html::encode(Plugin::t(StringHelper::toTitleCase($transaction->type))),
                        'level' => $level,
                    ],
                    'status' => [
                        'key' => $transaction->status,
                        'label' => Html::encode(Plugin::t(StringHelper::toTitleCase($transaction->status)))
                    ],
                    'paymentAmount' => Craft::$app->getFormatter()->asCurrency($transaction->paymentAmount, $transaction->paymentCurrency),
                    'amount' => Craft::$app->getFormatter()->asCurrency($transaction->amount, $transaction->currency),
                    'gateway' => Html::encode($transaction->gateway->name ?? Plugin::t('Missing Gateway')),
                    'date' => $transaction->dateUpdated ? $transaction->dateUpdated->format('H:i:s (jS M Y)') : '',
                    'info' => [
                        ['label' => Html::encode(Plugin::t('Transaction ID')), 'type' => 'code', 'value' => $transaction->id],
                        ['label' => Html::encode(Plugin::t('Transaction Hash')), 'type' => 'code', 'value' => $transaction->hash],
                        ['label' => Html::encode(Plugin::t('Gateway Reference')), 'type' => 'code', 'value' => $transaction->reference],
                        ['label' => Html::encode(Plugin::t('Gateway Message')), 'type' => 'text', 'value' => $transaction->message],
                        ['label' => Html::encode(Plugin::t('Note')), 'type' => 'text', 'value' => $transaction->note ?? ''],
                        ['label' => Html::encode(Plugin::t('Gateway Code')), 'type' => 'code', 'value' => $transaction->code],
                        ['label' => Html::encode(Plugin::t('Converted Price')), 'type' => 'text', 'value' => Plugin::getInstance()->getPaymentCurrencies()->convert($transaction->paymentAmount, $transaction->paymentCurrency) . ' <small class="light">(' . $transaction->currency . ')</small>' . ' <small class="light">(1 ' . $transaction->currency . ' = ' . number_format($transaction->paymentRate) . ' ' . $transaction->paymentCurrency . ')</small>'],
                        ['label' => Html::encode(Plugin::t('Gateway Response')), 'type' => 'response', 'value' => $transactionResponse],
                    ],
                    'actions' => $refundCapture,
                ];

                if (!empty($transaction->childTransactions)) {
                    $childTransactions = $this->_getTransactionsWIthLevelsTableArray($transaction->childTransactions, $level + 1);

                    foreach ($childTransactions as $childTransaction) {
                        $return[] = $childTransaction;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param array $results
     * @param string $baseCurrency
     * @param array $purchasables
     * @return array
     * @throws InvalidConfigException
     */
    private function _addLivePurchasableInfo(array $results): array
    {
        $baseCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $purchasables = [];
        foreach ($results as $row) {
            /** @var PurchasableInterface $purchasable */
            if ($purchasable = Craft::$app->getElements()->getElementById($row['id'])) {
                $row['priceAsCurrency'] = Craft::$app->getFormatter()->asCurrency($row['price'], $baseCurrency, [], [], true);
                $row['isAvailable'] = $purchasable->getIsAvailable();
                $purchasables[] = $row;
            }
        }
        return $purchasables;
    }

    /**
     * @param array $customers
     * @return array
     * @since 3.1.4
     */
    private function _prepCustomersArray(array $customers): array
    {
        if (empty($customers)) {
            return [];
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        foreach ($customers as &$customer) {
            $user = $customer['userId'] ? Craft::$app->getUsers()->getUserById($customer['userId']) : null;
            $customer['user'] = $user ? [
                'title' => $user ? $user->__toString() : null,
                'url' => $user && $currentUser->can('editUsers') ? $user->getCpEditUrl() : null,
                'status' => $user ? $user->getStatus() : null,
            ] : null;
            $customer['photo'] = $user && $user->photoId ? $user->getThumbUrl(30) : null;
            $customer['url'] = $currentUser->can('commerce-manageCustomers') ? UrlHelper::cpUrl('commerce/customers/' . $customer['id']) : null;
        }

        return $customers;
    }
}
