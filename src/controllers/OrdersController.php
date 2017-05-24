<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\Json;
use yii\base\Exception;
use yii\web\HttpException;

/**
 * Class Orders Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class OrdersController extends BaseCpController
{
    /**
     * @throws HttpException
     */
    public function init()
    {
        $this->requirePermission('commerce-manageOrders');
        parent::init();
    }

    /**
     * Index of orders
     */
    public function actionOrderIndex()
    {
        // Remove all incomplete carts older than a certain date in config.
        Plugin::getInstance()->getCart()->purgeIncompleteCarts();

        $this->renderTemplate('commerce/orders/_index');
    }

    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEditOrder(array $variables = [])
    {
        $variables['orderSettings'] = Plugin::getInstance()->getOrderSettings->getOrderSettingByHandle('order');

        if (!$variables['orderSettings']) {
            throw new HttpException(404, Craft::t('commerce', 'No order settings found.'));
        }

        if (empty($variables['order'])) {
            if (!empty($variables['orderId'])) {
                $variables['order'] = Plugin::getInstance()->getOrders()->getOrderById($variables['orderId']);

                if (!$variables['order']) {
                    throw new HttpException(404);
                }
            }
        }

        if (!empty($variables['orderId'])) {
            $variables['title'] = "Order ".substr($variables['order']->number, 0, 7);
        } else {
            throw new HttpException(404);
        }

        Craft::$app->getView()->registerCssFile('commerce/order.css');

        $this->prepVariables($variables);


        if (empty($variables['paymentForm'])) {
            $paymentMethod = $variables['order']->getPaymentMethod();

            if ($paymentMethod && $paymentMethod->getGatewayAdapter()) {
                $variables['paymentForm'] = $variables['order']->paymentMethod->getPaymentFormModel();
            } else {
                $paymentMethod = Plugin::getInstance()->getPaymentMethods()->getAllPaymentMethods();
                $variables['paymentForm'] = $paymentMethod->getPaymentFormModel();
            }
        }

        $variables['orderStatusesJson'] = Json::encode(Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses());

        $this->renderTemplate('commerce/orders/_edit', $variables);
    }

    /**
     * Modifies the variables of the request.
     *
     * @param $variables
     */
    private function prepVariables(&$variables)
    {
        $variables['tabs'] = [];

        foreach ($variables['orderSettings']->getFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($variables['order']->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    if ($variables['order']->getErrors($field->getField()->handle)) {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab'.($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            ];
        }
    }

    /**
     * Return Payment Modal
     */
    public function actionGetPaymentModal()
    {
        $this->requireAcceptsJson();
        $templatesService = Craft::$app->getView();

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $paymentFormData = Craft::$app->getRequest()->getParam('paymentForm');

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        $paymentMethods = Plugin::getInstance()->getPaymentMethods()->getAllPaymentMethods();

        $formHtml = "";
        foreach ($paymentMethods as $key => $paymentMethod) {
            // If adapter is not accessible, don't use it.
            if (!$paymentMethod->getGatewayAdapter()) {
                unset($paymentMethods[$key]);
                continue;
            }

            // If gateway adapter does no support backend cp payments.
            if (!$paymentMethod->getGatewayAdapter()->cpPaymentsEnabled()) {
                unset($paymentMethods[$key]);
                continue;
            }

            // Add the errors and data back to the current form model.
            if ($paymentMethod->id == $order->paymentMethodId) {
                $paymentFormModel = $order->paymentMethod->getPaymentFormModel();

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
                $paymentFormModel = $paymentMethod->getPaymentFormModel();
            }

            $paymentFormHtml = $paymentMethod->getPaymentFormHtml([
                'paymentForm' => $paymentFormModel,
                'order' => $order
            ]);

            $formHtml .= $paymentFormHtml;
        }

        $modalHtml = Craft::$app->getView()->render('commerce/orders/_paymentmodal', [
            'paymentMethods' => $paymentMethods,
            'order' => $order,
            'paymentForms' => $formHtml,
        ]);

        $this->asJson([
            'success' => true,
            'modalHtml' => $modalHtml,
            'headHtml' => $templatesService->getHeadHtml(),
            'footHtml' => $templatesService->getFootHtml(),
        ]);
    }

    /**
     * Capture Transaction
     */
    public function actionTransactionCapture()
    {
        $id = Craft::$app->getRequest()->getParam('id');
        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById($id);

        if ($transaction->canCapture()) {
            // capture transaction and display result
            $child = Plugin::getInstance()->getPayments()->captureTransaction($transaction);

            $message = $child->message ? ' ('.$child->message.')' : '';

            if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                Plugin::getInstance()->getOrders()->updateOrderPaidTotal($child->order);
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

        $this->redirectToPostedUrl();
    }

    /**
     * Refund Transaction
     */
    public function actionTransactionRefund()
    {
        $id = Craft::$app->getRequest()->getParam('id');
        $transaction = Plugin::getInstance()->getTransactions()->getTransactionById($id);

        if ($transaction->canRefund()) {
            // capture transaction and display result
            $child = Plugin::getInstance()->getPayments()->refundTransaction($transaction);

            $message = $child->message ? ' ('.$child->message.')' : '';

            if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Transaction refunded successfully: {message}', [
                    'message' => $message
                ]));
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t refund transaction: {message}', [
                    'message' => $message
                ]));
            }
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t refund transaction.'));
        }

        $this->redirectToPostedUrl();
    }

    public function actionCompleteOrder()
    {
        $this->requireAcceptsJson();
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if ($order && !$order->isCompleted) {
            if (Plugin::getInstance()->getOrders()->completeOrder($order)) {
                $date = new \DateTime($order->dateOrdered);
                $this->asJson(['success' => true, 'dateOrdered' => $date]);
            }
        }

        $this->asErrorJson(Craft::t("commerce", "Could not mark the order as completed."));
    }

    /**
     *
     */
    public function actionUpdateOrderAddress()
    {
        $this->requireAcceptsJson();

        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $addressId = Craft::$app->getRequest()->getParam('addressId');
        $type = Craft::$app->getRequest()->getParam('addressType');

        // Validate Address Type
        if (!in_array($type, ['shippingAddress', 'billingAddress'])) {
            $this->asErrorJson(Craft::t('commerce', 'Not a valid address type'));
        }

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order) {
            $this->asErrorJson(Craft::t('commerce', 'Bad order ID.'));
        }

        // Return early if the address is already set.
        if ($order->{$type.'Id'} == $addressId) {
            $this->asJson(['success' => true]);
        }

        // Validate Address Id
        $address = Plugin::getInstance()->getAddresses()->getAddressById($addressId);
        if (!$address) {
            $this->asErrorJson(Craft::t('commerce', 'Bad address ID.'));
        }

        $order->{$type.'Id'} = $address->id;

        if (Plugin::getInstance()->getOrders()->saveOrder($order)) {
            $this->asJson(['success' => true]);
        }

        $this->asErrorJson(Craft::t('commerce', 'Could not update orders address.'));
    }

    /**
     * Update Order Status Id
     */
    public function actionUpdateStatus()
    {
        $this->requireAcceptsJson();
        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $orderStatusId = Craft::$app->getRequest()->getParam('orderStatusId');
        $message = Craft::$app->getRequest()->getParam('message');

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);

        if (!$order or !$orderStatus) {
            $this->asErrorJson(Craft::t('commerce', 'Bad Order or Status'));
        }

        $order->orderStatusId = $orderStatus->id;
        $order->message = $message;

        if (Plugin::getInstance()->getOrders()->saveOrder($order)) {
            $this->asJson(['success' => true]);
        }
    }

    /**
     *
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionSaveOrder()
    {
        $this->requirePostRequest();

        $order = $this->_setOrderFromPost();
        $this->_setContentFromPost($order);

        if (Plugin::getInstance()->getOrders()->saveOrder($order)) {
            $this->redirectToPostedUrl($order);
        }

        Craft::$app->getSession()->setError(Craft::t("commerce", "Couldn’t save order."));
        Craft::$app->getUrlManager()->setRouteParams([
            'order' => $order
        ]);
    }

    /**
     * @return Order
     * @throws Exception
     */
    private function _setOrderFromPost()
    {
        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new Exception(Craft::t('commerce', 'No order with the ID “{id}”', ['id' => $orderId]));
        }

        return $order;
    }

    /**
     * @param Order $order
     */
    private function _setContentFromPost($order)
    {
        $order->setContentFromPost('fields');
    }

    /**
     * Deletes a order.
     *
     * @throws Exception if you try to edit a non existing Id.
     */
    public function actionDeleteOrder()
    {
        $this->requirePostRequest();

        $orderId = Craft::$app->getRequest()->getRequiredParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new Exception(Craft::t('commerce', 'No order exists with the ID “{id}”.',
                ['id' => $orderId]));
        }

        if (!Craft::$app->getElements()->deleteElementById($order->id)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t delete order.'));
                Craft::$app->getUrlManager()->setRouteParams(['order' => $order]);
            }

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true]);
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Order deleted.'));
            $this->redirectToPostedUrl($order);
        }
    }
}
