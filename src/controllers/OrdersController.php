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
use craft\commerce\elements\Order;
use craft\commerce\errors\RefundException;
use craft\commerce\errors\TransactionException;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\models\Customer;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\commerce\web\assets\commerceui\CommerceUiAsset;
use craft\db\Query;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\web\Controller;
use craft\web\View;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
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
    // Public Methods
    // =========================================================================

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
     */
    public function actionOrderIndex(): Response
    {
        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

        Craft::$app->getView()->registerJs('window.orderEdit = {};', View::POS_BEGIN);
        $permissions = [
            'commerce-editOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-editOrders'),
            'commerce-deleteOrders' => Craft::$app->getUser()->getIdentity()->can('commerce-deleteOrders'),
        ];
        Craft::$app->getView()->registerJs('window.orderEdit.currentUserPermissions = ' . Json::encode($permissions) . ';', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.edition = "' . Plugin::getInstance()->edition . '"', View::POS_BEGIN);

        return $this->renderTemplate('commerce/orders/_index');
    }

    /**
     * Create an order
     */
    public function actionNewOrder(): Response
    {
        $this->requirePermission('commerce-editOrder');

        $order = new Order();
        $order->number = Plugin::getInstance()->getCarts()->generateCartNumber();
        $customer = new Customer();
        Plugin::getInstance()->getCustomers()->saveCustomer($customer);
        $order->customerId = $customer->id;
        $order->origin = Order::ORIGIN_CP;

        if (!Craft::$app->getElements()->saveElement($order)) {
            throw new Exception(Craft::t('commerce', 'Can not create a new order'));
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
        $this->requirePermission('commerce-editOrder');

        $plugin = Plugin::getInstance();
        $variables = [];

        if ($order === null && $orderId) {
            $order = $plugin->getOrders()->getOrderById($orderId);

            if (!$order) {
                throw new HttpException(404, Craft::t('commerce', 'Can not find order.'));
            }
        }

        $variables['order'] = $order;
        $variables['orderId'] = $order->id;
        $variables['fieldLayout'] = Craft::$app->getFields()->getLayoutByType(Order::class);

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
        $this->requirePermission('commerce-editOrder');
        $this->requirePostRequest();

        $data = Craft::$app->getRequest()->getBodyParam('orderData');

        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            throw new HttpException(400, Craft::t('commerce', 'Invalid Order ID'));
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

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save order.'));

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
        $this->requirePermission('commerce-deleteOrder');

        $orderId = (int) Craft::$app->getRequest()->getRequiredBodyParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new HttpException(404, Craft::t('commerce', 'Can not find order.'));
        }

        if (!Craft::$app->getElements()->deleteElementById($order->id)) {
            return $this->asJson(['success' => false]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Order deleted.'));

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
        $this->requirePermission('commerce-editOrder');

        $data = Craft::$app->getRequest()->getRawBody();
        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            return $this->asErrorJson(Craft::t('commerce', 'Invalid Order ID'));
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
            $response['error'] = Craft::t('commerce', 'The order is not valid.');
        }

        return $this->asJson($response);
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

        $extraFields = ['lineItems.snapshot', 'availableShippingMethods'];
        return $order->toArray($orderFields, $extraFields);
    }

    /**
     * @param null $query
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionPurchasableSearch($query = null)
    {
        // Prepare purchasables query
        $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = (new Query())
            ->select(['id', 'price', 'description', 'sku'])
            ->from('{{%commerce_purchasables}}');

        // Are they searching for a purchasable ID?
        if (is_numeric($query)) {
            $result = $sqlQuery->where(['id' => $query])->all();
            if (!$result) {
                return $this->asJson([]);
            }
            return $this->asJson($result);
        }

        // Are they searching for a SKU or purchasable description?
        if ($query) {
            $sqlQuery->where([
                'or',
                [$likeOperator, 'description', $query],
                [$likeOperator, 'SKU', $query]
            ]);
        }

        $result = $sqlQuery->limit(30)->all();

        if (!$result) {
            return $this->asJson([]);
        }

        $purchasables = [];

        // Add the currency formatted price
        $baseCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        foreach ($result as $row) {
            /** @var PurchasableInterface $purchasable */
            if ($purchasable = Craft::$app->getElements()->getElementById($row['id'])) {
                $row['priceAsCurrency'] = Craft::$app->getFormatter()->asCurrency($row['price'], $baseCurrency, [], [], true);
                $row['isAvailable'] = $purchasable->getIsAvailable();
                $purchasables[] = $row;
            }
        }

        return $this->asJson($purchasables);
    }

    /**
     * @param null $query
     * @return Response
     */
    public function actionCustomerSearch($query = null)
    {
        $limit = 30;

        $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ILIKE' : 'LIKE';
        $sqlQuery = (new Query())
            ->select(['[[customers.id]] as customerId', '[[orders.email]] as email', 'count([[orders.id]]) as totalOrders', '[[customers.userId]] as userId', '[[users.firstName]] as firstName', '[[users.lastName]] as lastName'])
            ->from('{{%commerce_customers}} customers')
            ->leftJoin('{{%commerce_orders}} orders', '[[customers.id]] = [[orders.customerId]]')
            ->leftJoin('{{%users}} users', '[[customers.userId]] = [[users.id]]');

        // Are they searching for a purchasable ID?
        $results = [];
        if (is_numeric($query)) {
            $result = $sqlQuery->where(['[[customers.id]]' => $query])->one();
            if ($result) {
                $results[] = $result;
            }
        }

        // Are they searching for a SKU or purchasable description?
        if (!is_numeric($query)) {
            if ($query) {
                $sqlQuery->where(
                    [$likeOperator, '[[orders.email]]', $query]
                );
            }
            $results = $sqlQuery->limit($limit)->all();
        }

        foreach ($results as $key => $row) {
            if (!isset($row['customerId']) || $row['customerId'] === null) {
                unset($results[$key]);
            }
        }

        return $this->asJson($results);
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
            return $this->asErrorJson(Craft::t('commerce', 'Bad Request'));
        }

        $email = Plugin::getInstance()->getEmails()->getEmailById($id);
        $order = Order::find()->id($orderId)->one();

        if ($email === null) {
            return $this->asErrorJson(Craft::t('commerce', 'Can not find email'));
        }

        if ($order === null) {
            return $this->asErrorJson(Craft::t('commerce', 'Can not find order'));
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
            return $this->asErrorJson(Craft::t('commerce', 'Could not send email'));
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
            $this->asErrorJson(Craft::t('commerce', 'Not a valid address type'));
        }

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order) {
            $this->asErrorJson(Craft::t('commerce', 'Bad order ID.'));
        }

        // Return early if the address is already set.
        if ($order->{$type . 'Id'} == $addressId) {
            return $this->asJson(['success' => true]);
        }

        // Validate Address Id
        $address = $addressId ? Plugin::getInstance()->getAddresses()->getAddressById($addressId) : null;
        if (!$address) {
            return $this->asErrorJson(Craft::t('commerce', 'Bad address ID.'));
        }

        $order->{$type . 'Id'} = $address->id;

        if (Craft::$app->getElements()->saveElement($order)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could not update orders address.'));
    }


    /**
     * Returns Payment Modal
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
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
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Transaction captured successfully: {message}', [
                    'message' => $message
                ]));
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t capture transaction: {message}', [
                    'message' => $message
                ]));
            }
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t capture transaction.', ['id' => $id]));
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
            $error = Craft::t('commerce', 'Can not find the transaction to refund');
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

        if ($amount > $transaction->paymentAmount) {
            $error = Craft::t('commerce', 'Can not refund amount greater than the original transaction');
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
                    Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Transaction refunded successfully: {message}', [
                        'message' => $message
                    ]));
                } else {
                    Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t refund transaction: {message}', [
                        'message' => $message
                    ]));
                }
            } catch (RefundException $exception) {
                Craft::$app->getSession()->setError($exception->getMessage());
            }
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t refund transaction.'));
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

        $variables['title'] = Craft::t('commerce', 'Order') . ' ' . $order->reference;

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_CP) {
            $variables['title'] = Craft::t('commerce', 'New Order');
        }

        if (!$order->isCompleted && $order->origin == Order::ORIGIN_WEB) {
            $variables['title'] = Craft::t('commerce', 'Cart') . ' ' . $order->getShortNumber();
        }

        $variables['tabs'] = [];

        $variables['tabs'][] = [
            'label' => Craft::t('commerce', 'Order Details'),
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
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => implode(' ', $classes)
            ];

            // Add the static version of the custom fields.
            $classes[] = 'static';
            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab' . ($index + 1) . 'Static',
                'class' => implode(' ', $classes)
            ];
        }

        $variables['tabs'][] = [
            'label' => Craft::t('commerce', 'Transactions'),
            'url' => '#transactionsTab',
            'class' => null
        ];

        $variables['tabs'][] = [
            'label' => Craft::t('commerce', 'Status History'),
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
        Craft::$app->getView()->registerAssetBundle(CommerceUiAsset::class);

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
        Craft::$app->getView()->registerJs('window.orderEdit.continueEditingUrl = "' . $variables['order']->cpEditUrl . '"', View::POS_BEGIN);

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
            $response['error'] = Craft::t('commerce', 'The order is not valid.');
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

        if (($dateOrdered = $orderRequestData['order']['dateOrdered']) !== null) {
            $order->dateOrdered = DateTimeHelper::toDateTime($dateOrdered) ?: null;
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
}
