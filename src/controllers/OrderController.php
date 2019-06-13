<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Field;
use craft\commerce\base\Gateway;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\models\Customer;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\commerce\web\assets\commerceui\CommerceUiAsset;
use craft\db\Query;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
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
class OrderController extends Controller
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
        // Remove all incomplete carts older than a certain date in config.
        Plugin::getInstance()->getCarts()->purgeIncompleteCarts();

        Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

        return $this->renderTemplate('commerce/orders/_index');
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
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $data = Craft::$app->getRequest()->getBodyParam('orderData');

        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            throw new HttpException(400, Craft::t('commerce', 'Invalid Order ID'));
        }

        $this->_updateOrder($order, $orderRequestData);

        $order->setFieldValuesFromRequest('fields');

        if (!Craft::$app->getElements()->saveElement($order)) {
            // Recalculation mode should always return to none, unless it is still a cart
            $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
            if (!$order->isCompleted) {
                $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save order.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'order' => $order
            ]);
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

        $orderId = Craft::$app->getRequest()->getRequiredBodyParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new HttpException(404, Craft::t('commerce', 'Can not find order.'));
        }

        if (!Craft::$app->getElements()->deleteElementById($order->id)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t delete order.'));
            Craft::$app->getUrlManager()->setRouteParams(['order' => $order]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Order deleted.'));
        return $this->redirect('commerce/orders');
    }

    /**
     * The refresh action accepts a json representation of an order, recalculates it depending on the mode submitted,
     * and returns the order as json with any validation errors.
     *
     * @return Response
     * @throws HttpException
     * @throws Exception
     */
    public function actionRefresh()
    {
        $data = Craft::$app->getRequest()->getRawBody();
        $orderRequestData = Json::decodeIfJson($data);

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderRequestData['order']['id']);

        if (!$order) {
            return $this->asErrorJson(Craft::t('commerce', 'Invalid Order ID'));
        }

        $this->_updateOrder($order, $orderRequestData);

        if ($order->validate() && $order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
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
     * Modifies the variables of the request.
     *
     * @param $variables
     * @throws InvalidConfigException
     */
    private function _updateTemplateVariables(&$variables)
    {
        /** @var Order $order */
        $order = $variables['order'];

        if ($order->isCompleted || $order->reference) {
            $variables['title'] = 'Order ' . $order->reference;
        } else {
            $variables['title'] = 'Cart ' . $order->number;
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

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => $hasErrors ? 'error' : null
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
        Craft::$app->getView()->registerJs('window.orderEdit.lineItemStatuses = ' . Json::encode(ArrayHelper::toArray($lineItemStatuses)) . ';', View::POS_BEGIN);

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategoriesAsList();
        Craft::$app->getView()->registerJs('window.orderEdit.taxCategories = ' . Json::encode(ArrayHelper::toArray($taxCategories)) . ';', View::POS_BEGIN);

        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategoriesAsList();
        Craft::$app->getView()->registerJs('window.orderEdit.shippingCategories = ' . Json::encode(ArrayHelper::toArray($shippingCategories)) . ';', View::POS_BEGIN);

        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods();
        Craft::$app->getView()->registerJs('window.orderEdit.shippingMethods = ' . Json::encode(ArrayHelper::toArray($shippingMethods)) . ';', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.edition = "' . Plugin::getInstance()->edition . '"', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.ordersIndexUrl = "' . UrlHelper::cpUrl('commerce/orders') . '"', View::POS_BEGIN);
        Craft::$app->getView()->registerJs('window.orderEdit.continueEditingUrl = "' . $variables['order']->cpEditUrl . '"', View::POS_BEGIN);

        // TODO when we support multiple PDF templates, retrieve them all from a service
        $pdfUrls = [
            'Download PDF' => $variables['order']->getPdfUrl()
        ];
        Craft::$app->getView()->registerJs('window.orderEdit.pdfUrls = ' . Json::encode(ArrayHelper::toArray($pdfUrls)) . ';', View::POS_BEGIN);

        $response = [];
        $response['order'] = $this->_orderToArray($variables['order']);

        if ($variables['order']->hasErrors()) {
            $response['order']['errors'] = $variables['order']->getErrors();
            $response['error'] = Craft::t('commerce', 'The order is not valid.');
        }

        Craft::$app->getView()->registerJs('window.orderEdit.data = ' . Json::encode($response) . ';', View::POS_BEGIN);
    }

    /**
     * @param Order $order
     * @param $orderRequestData
     */
    private function _updateOrder(Order $order, $orderRequestData)
    {
        $originalCustomerId = $order->customerId;

        $order->setRecalculationMode($orderRequestData['order']['recalculationMode']);
        $order->reference = $orderRequestData['order']['reference'];
        $order->email = $orderRequestData['order']['email'];
        $order->customerId = $orderRequestData['order']['customerId'];
        $order->couponCode = $orderRequestData['order']['couponCode'];
        $order->isCompleted = $orderRequestData['order']['isCompleted'];
        $order->orderStatusId = $orderRequestData['order']['orderStatusId'];
        $order->message = 'Uncomment the message variable in the controller'; //$orderRequestData['order']['message'];
        //$order->dateOrdered = ?; //$orderRequestData['order']['dateOrdered'];
        $order->shippingMethodHandle = $orderRequestData['order']['shippingMethodHandle'];

        // New customer
        if($order->customerId == null && $order->email)
        {
            $newCustomer = new Customer();
            if(Plugin::getInstance()->getCustomers()->saveCustomer($newCustomer)){
                $order->customerId = $newCustomer->id;
            }
        }

        // Changing the customer should change the email, if that customer has a user account
        if($originalCustomerId && $order->customerId && $order->customerId != $originalCustomerId)
        {
            $customer = Plugin::getInstance()->getCustomers()->getCustomerById($order->customerId);
            if($customer && $customer->getUser())
            {
                $order->email = $customer->getUser()->email;
            }
        }


        $lineItems = [];
        $adjustments = [];

        foreach ($orderRequestData['order']['lineItems'] as $lineItemData) {

            // Normalize data
            $lineItemId = $lineItemData['id'] ?? null;
            $note = $lineItemData['note'] ?? '';
            $adminNote = $lineItemData['adminNote'] ?? '';
            $purchasableId = $lineItemData['purchasableId'];
            $lineItemStatusId = $lineItemData['lineItemStatusId'];
            $options = $lineItemData['options'] ?? [];
            $qty = $lineItemData['qty'] ?? 1;

            $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

            if (!$lineItem) {
                $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order->id, $purchasableId, $options, $qty, $note);
            }

            $lineItem->purchasableId = $purchasableId;
            $lineItem->qty = $qty;
            $lineItem->note = $note;
            $lineItem->adminNote = $adminNote;
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

                foreach ($lineItemData['adjustments'] as $adjustment) {

                    $id = $adjustment['id'];

                    $adjustment = null;
                    if ($id) {
                        $adjustment = Plugin::getInstance()->getOrderAdjustments()->getOrderAdjustmentById($id);
                    }
                    if ($adjustment === null) {
                        $adjustment = new OrderAdjustment();
                    }

                    $adjustment->setOrder($order);
                    $adjustment->setLineItem($lineItem);
                    $adjustment->amount = $adjustment['amount'];
                    $adjustment->type = $adjustment['type'];
                    $adjustment->name = $adjustment['name'];
                    $adjustment->description = $adjustment['description'];
                    $adjustment->included = $adjustment['included'];

                    $adjustments[] = $adjustment;
                }
            }
        }

        $order->setLineItems($lineItems);

        // Only update the adjustments if the recalculation mode is none (manually updating adjustments)
        if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {

            foreach ($orderRequestData['order']['orderAdjustments'] as $adjustment) {

                $id = $adjustment['id'];

                $adjustment = null;
                if ($id) {
                    $adjustment = Plugin::getInstance()->getOrderAdjustments()->getOrderAdjustmentById($id);
                }
                if ($adjustment === null) {
                    $adjustment = new OrderAdjustment();
                }

                $adjustment->setOrder($order);
                $adjustment->amount = $adjustment['amount'];
                $adjustment->type = $adjustment['type'];
                $adjustment->name = $adjustment['name'];
                $adjustment->description = $adjustment['description'];
                $adjustment->included = $adjustment['included'];

                $adjustments[] = $adjustment;
            }

            // add all the updated adjustments to the order
            $order->setAdjustments($adjustments);
        }
    }
}
