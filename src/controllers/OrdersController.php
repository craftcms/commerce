<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\ArrayHelper;
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

        return $this->renderTemplate('commerce/orders/_index');
    }

    /**
     * @param $orderId
     *
     * @return \yii\web\Response
     * @throws HttpException
     */
    public function actionEditOrder($orderId)
    {
        $plugin = Plugin::getInstance();
        $variables = [
            'orderId' => $orderId,
            'orderSettings' => $plugin->getOrderSettings()->getOrderSettingByHandle('order')
        ];

        if (!$variables['orderSettings']) {
            throw new HttpException(404, Craft::t('commerce', 'No order settings found.'));
        }

        if (empty($variables['order']) && !empty($variables['orderId'])) {
            $variables['order'] = $plugin->getOrders()->getOrderById($variables['orderId']);

            if (!$variables['order']) {
                throw new HttpException(404);
            }
        }

        if (!empty($variables['orderId'])) {
            $variables['title'] = 'Order '.substr($variables['order']->number, 0, 7);
        } else {
            throw new HttpException(404);
        }

        $this->prepVariables($variables);

        if (empty($variables['paymentForm'])) {
            /** @var Gateway $gateway */
            $gateway = $variables['order']->getGateway();

            if ($gateway) {
                $variables['paymentForm'] = $gateway->getPaymentFormModel();
            } else {
                $gateway = ArrayHelper::firstValue($plugin->getGateways()->getAllGateways());

                if ($gateway) {
                    $variables['paymentForm'] = $gateway->getPaymentFormModel();
                }
            }
        }

        $allStatuses = array_values($plugin->getOrderStatuses()->getAllOrderStatuses());
        $variables['orderStatusesJson'] = Json::encode($allStatuses);

        return $this->renderTemplate('commerce/orders/_edit', $variables);
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
                'class' => $hasErrors ? 'error' : null
            ];
        }
    }

    /**
     * Return Payment Modal
     */
    public function actionGetPaymentModal()
    {
        $this->requireAcceptsJson();
        $view = Craft::$app->getView();

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
            if (!$gateway->cpPaymentsEnabled()) {
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
                $child->order->updateOrderPaidTotal();
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

        if ($order && !$order->isCompleted && $order->markAsComplete()) {
            $date = new \DateTime($order->dateOrdered);
            return $this->asJson(['success' => true, 'dateOrdered' => $date]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could not mark the order as completed.'));
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
        if (!in_array($type, ['shippingAddress', 'billingAddress'], true)) {
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
        $address = $addressId ? Plugin::getInstance()->getAddresses()->getAddressById($addressId) : null;
        if (!$address) {
            $this->asErrorJson(Craft::t('commerce', 'Bad address ID.'));
        }

        $order->{$type.'Id'} = $address->id;

        if (Craft::$app->getElements()->saveElement($order)) {
            $this->asJson(['success' => true]);
        }

        $this->asErrorJson(Craft::t('commerce', 'Could not update orders address.'));
    }

    /**
     * @return null|\yii\web\Response
     */
    public function actionUpdateStatus()
    {
        $this->requireAcceptsJson();
        $orderId = Craft::$app->getRequest()->getParam('orderId');
        $orderStatusId = Craft::$app->getRequest()->getParam('orderStatusId');
        $message = Craft::$app->getRequest()->getParam('message');

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);

        if (!$order || !$orderStatus) {
            $this->asErrorJson(Craft::t('commerce', 'Bad Order or Status'));
        }

        $order->orderStatusId = $orderStatus->id;
        $order->message = $message;

        if (Craft::$app->getElements()->saveElement($order)) {
            return $this->asJson(['success' => true]);
        }

        return null;
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

        if (Craft::$app->getElements()->saveElement($order)) {
            $this->redirectToPostedUrl($order);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save order.'));
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
        $order->setFieldValuesFromRequest('fields');
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
