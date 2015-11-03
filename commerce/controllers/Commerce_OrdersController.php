<?php
namespace Craft;

/**
 * Class Commerce_OrdersController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_OrdersController extends Commerce_BaseCpController
{
    /**
     * Index of orders
     */
    public function actionOrderIndex()
    {
        // Remove all incomplete carts older than a certain date in config.
        craft()->commerce_cart->purgeIncompleteCarts();

        $this->renderTemplate('commerce/orders/_index');
    }

    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEditOrder(array $variables = [])
    {
        $variables['orderSettings'] = craft()->commerce_orderSettings->getOrderSettingByHandle('order');

        if (!$variables['orderSettings']) {
            throw new HttpException(404, Craft::t('No order settings found.'));
        }

        if (empty($variables['order'])) {
            if (!empty($variables['orderId'])) {
                $variables['order'] = craft()->commerce_orders->getOrderById($variables['orderId']);

                if (!$variables['order']) {
                    throw new HttpException(404);
                }
            }
        }

        if (!empty($variables['orderId'])) {
            $variables['title'] = "Order " . substr($variables['order']->number, 0, 7);
        } else {
            throw new HttpException(404);
        }

        craft()->templates->includeCssResource('commerce/order.css');

        $this->prepVariables($variables);

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
                'label' => Craft::t($tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => ($hasErrors ? 'error' : null)
            ];
        }
    }

    /**
     * Capture Transaction
     */
    public function actionTransactionCapture()
    {
        $id = craft()->request->getParam('id');
        $transaction = craft()->commerce_transactions->getTransactionById($id);

        if ($transaction->canCapture()) {
            // capture transaction and display result
            $child = craft()->commerce_payments->captureTransaction($transaction);

            $message = $child->message ? ' (' . $child->message . ')' : '';

            if ($child->status == Commerce_TransactionRecord::SUCCESS) {
                craft()->commerce_orders->updateOrderPaidTotal($child->order);
                craft()->userSession->setNotice(Craft::t('Transaction captured successfully: {message}', [
                    'message' => $message
                ]));
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t capture transaction: {message}', [
                    'message' => $message
                ]));
            }
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t capture transaction.', ['id' => $id]));
        }
    }

    /**
     * Refund Transaction
     */
    public function actionTransactionRefund()
    {
        $id = craft()->request->getParam('id');
        $transaction = craft()->commerce_transactions->getTransactionById($id);

        if ($transaction->canRefund()) {
            // capture transaction and display result
            $child = craft()->commerce_payments->refundTransaction($transaction);

            $message = $child->message ? ' (' . $child->message . ')' : '';

            if ($child->status == Commerce_TransactionRecord::SUCCESS) {
                craft()->userSession->setNotice(Craft::t('Transaction refunded successfully: {message}', [
                    'message' => $message
                ]));
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t refund transaction: {message}', [
                    'message' => $message
                ]));
            }
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t refund transaction.'));
        }
    }

    /**
     * Update Order Status Id
     */
    public function actionUpdateStatus()
    {
        $this->requireAjaxRequest();
        $orderId = craft()->request->getParam('orderId');
        $orderStatusId = craft()->request->getParam('orderStatusId');
        $message = craft()->request->getParam('message');

        $order = craft()->commerce_orders->getOrderById($orderId);
        $orderStatus = craft()->commerce_orderStatuses->getById($orderStatusId);

        if (!$order or !$orderStatus) {
            $this->returnErrorJson(Craft::t('Bad Order or Status'));
        }

        $order->orderStatusId = $orderStatus->id;
        $order->message = $message;

        if (craft()->commerce_orders->saveOrder($order)) {
            $this->returnJson(['success' => true]);
        }
    }


    /**
     * Update an address on Order.
     */
    public function actionUpdateAddress()
    {
        $this->requireAjaxRequest();
        $orderId = craft()->request->getParam('orderId');
        $addressType = craft()->request->getParam('addressType');
        $address = craft()->request->getParam('address');

        $order = craft()->commerce_orders->getOrderById($orderId);

        if ($addressType == 'billing') {
            $billingAddress = Commerce_AddressModel::populateModel($address);
            $order->setBillingAddress($billingAddress);
        } else {
            $shippingAddress = Commerce_AddressModel::populateModel($address);
            $order->setShippingAddress($shippingAddress);
        }

        $order->billingAddressId = null;
        $order->shippingAddressId = null;


        if ($order->hasErrors()) {
            $this->returnErrorJson(Craft::t('Error saving address.'));
        }

        if (craft()->commerce_orders->saveOrder($order)) {
            $this->returnJson(['success' => true]);
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

        if (craft()->commerce_orders->saveOrder($order)) {
            $this->redirectToPostedUrl($order);
        }

        craft()->userSession->setError(Craft::t("Couldn’t save order."));
        craft()->urlManager->setRouteVariables([
            'order' => $order
        ]);
    }

    /**
     * @return Commerce_OrderModel
     * @throws Exception
     */
    private function _setOrderFromPost()
    {
        $orderId = craft()->request->getPost('orderId');

        if ($orderId) {
            $order = craft()->commerce_orders->getOrderById($orderId);

            if (!$order) {
                throw new Exception(Craft::t('No order with the ID “{id}”',
                    ['id' => $orderId]));
            }
        }

        return $order;
    }

    /**
     * @param Commerce_OrderModel $order
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

        $orderId = craft()->request->getRequiredPost('orderId');
        $order = craft()->commerce_orders->getOrderById($orderId);

        if (!$order) {
            throw new Exception(Craft::t('No order exists with the ID “{id}”.',
                ['id' => $orderId]));
        }

        if (craft()->commerce_orders->deleteOrder($order)) {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => true]);
            } else {
                craft()->userSession->setNotice(Craft::t('Order deleted.'));
                $this->redirectToPostedUrl($order);
            }
        } else {
            if (craft()->request->isAjaxRequest()) {
                $this->returnJson(['success' => false]);
            } else {
                craft()->userSession->setError(Craft::t('Couldn’t delete order.'));
                craft()->urlManager->setRouteVariables(['order' => $order]);
            }
        }
    }
}
