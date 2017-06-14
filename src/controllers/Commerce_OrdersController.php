<?php
namespace Craft;

/**
 * Class Commerce_OrdersController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_OrdersController extends Commerce_BaseCpController
{
    /**
     * @throws HttpException
     */
    public function init()
    {
        craft()->userSession->requirePermission('commerce-manageOrders');
        parent::init();
    }

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

        if (!$variables['orderSettings'])
        {
            throw new HttpException(404, Craft::t('No order settings found.'));
        }

        if (empty($variables['order']))
        {
            if (!empty($variables['orderId']))
            {
                $variables['order'] = craft()->commerce_orders->getOrderById($variables['orderId']);

                if (!$variables['order'])
                {
                    throw new HttpException(404);
                }
            }
        }

        if (!empty($variables['orderId']))
        {
            $variables['title'] = "Order ".substr($variables['order']->number, 0, 7);
        }
        else
        {
            throw new HttpException(404);
        }

        craft()->templates->includeCssResource('commerce/order.css');

        $this->prepVariables($variables);


        if (empty($variables['paymentForm']))
        {
            $paymentMethod = $variables['order']->getPaymentMethod();

            if ($paymentMethod && $paymentMethod->getGatewayAdapter())
            {
                $variables['paymentForm'] = $variables['order']->paymentMethod->getPaymentFormModel();
            }
            else
            {
                $paymentMethod = ArrayHelper::getFirstValue(craft()->commerce_paymentMethods->getAllPaymentMethods());

                if($paymentMethod)
                {
                    $variables['paymentForm'] = $paymentMethod->getPaymentFormModel();
                }
                else
                {
                    $variables['paymentForm'] = null;
                }
            }
        }
        
        $variables['orderStatusesJson'] = JsonHelper::encode(craft()->commerce_orderStatuses->getAllOrderStatuses());

        $this->renderTemplate('commerce/orders/_edit', $variables);
    }

    /**
     * Return Payment Modal
     */
    public function actionGetPaymentModal()
    {
        $this->requireAjaxRequest();
        $templatesService = craft()->templates;

        $orderId = craft()->request->getParam('orderId');
        $paymentFormData = craft()->request->getParam('paymentForm');

        $order = craft()->commerce_orders->getOrderById($orderId);
        $paymentMethods = craft()->commerce_paymentMethods->getAllPaymentMethods();

        $formHtml = "";
        foreach ($paymentMethods as $key => $paymentMethod)
        {
            // If adapter is not accessible, don't use it.
            if (!$paymentMethod->getGatewayAdapter())
            {
                unset($paymentMethods[$key]);
                continue;
            }

            // If gateway adapter does no support backend cp payments.
            if (!$paymentMethod->getGatewayAdapter()->cpPaymentsEnabled())
            {
                unset($paymentMethods[$key]);
                continue;
            }

            // Add the errors and data back to the current form model.
            if ($paymentMethod->id == $order->paymentMethodId)
            {
                $paymentFormModel = $order->paymentMethod->getPaymentFormModel();

                if ($paymentFormData)
                {
                    // Re-add submitted data to payment form model
                    if (isset($paymentFormData['attributes']))
                    {
                        $paymentFormModel->attributes = $paymentFormData['attributes'];
                    }

                    // Re-add errors to payment form model
                    if (isset($paymentFormData['errors']))
                    {
                        $paymentFormModel->addErrors($paymentFormData['errors']);
                    }
                }
            }
            else
            {
                $paymentFormModel = $paymentMethod->getPaymentFormModel();
            }

            $paymentFormHtml = $paymentMethod->getPaymentFormHtml([
                'paymentForm' => $paymentFormModel,
                'order' => $order
            ]);

            $formHtml .= $paymentFormHtml;
        }

        $modalHtml = craft()->templates->render('commerce/orders/_paymentmodal', [
            'paymentMethods' => $paymentMethods,
            'order'          => $order,
            'paymentForms'   => $formHtml,
        ]);

        $this->returnJson([
            'success'   => true,
            'modalHtml' => $modalHtml,
            'headHtml' => $templatesService->getHeadHtml(),
            'footHtml' => $templatesService->getFootHtml(),
        ]);
    }

    /**
     * Modifies the variables of the request.
     *
     * @param $variables
     */
    private function prepVariables(&$variables)
    {
        $variables['tabs'] = [];

        foreach ($variables['orderSettings']->getFieldLayout()->getTabs() as $index => $tab)
        {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($variables['order']->hasErrors())
            {
                foreach ($tab->getFields() as $field)
                {
                    if ($variables['order']->getErrors($field->getField()->handle))
                    {
                        $hasErrors = true;
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t($tab->name),
                'url'   => '#tab'.($index + 1),
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

        if ($transaction->canCapture())
        {
            // capture transaction and display result
            $child = craft()->commerce_payments->captureTransaction($transaction);

            $message = $child->message ? ' ('.$child->message.')' : '';

            if ($child->status == Commerce_TransactionRecord::STATUS_SUCCESS)
            {
                craft()->commerce_orders->updateOrderPaidTotal($child->order);
                craft()->userSession->setNotice(Craft::t('Transaction captured successfully: {message}', [
                    'message' => $message
                ]));
            }
            else
            {
                craft()->userSession->setError(Craft::t('Couldn’t capture transaction: {message}', [
                    'message' => $message
                ]));
            }
        }
        else
        {
            craft()->userSession->setError(Craft::t('Couldn’t capture transaction.', ['id' => $id]));
        }

        $this->redirectToPostedUrl();
    }

    /**
     * Refund Transaction
     */
    public function actionTransactionRefund()
    {
        $id = craft()->request->getParam('id');
        $transaction = craft()->commerce_transactions->getTransactionById($id);

        if ($transaction->canRefund())
        {
            // capture transaction and display result
            $child = craft()->commerce_payments->refundTransaction($transaction);

            $message = $child->message ? ' ('.$child->message.')' : '';

            if ($child->status == Commerce_TransactionRecord::STATUS_SUCCESS)
            {
                craft()->userSession->setNotice(Craft::t('Transaction refunded successfully: {message}', [
                    'message' => $message
                ]));
            }
            else
            {
                craft()->userSession->setError(Craft::t('Couldn’t refund transaction: {message}', [
                    'message' => $message
                ]));
            }
        }
        else
        {
            craft()->userSession->setError(Craft::t('Couldn’t refund transaction.'));
        }

        $this->redirectToPostedUrl();
    }

    public function actionCompleteOrder()
    {
        $this->requireAjaxRequest();
        $orderId = craft()->request->getParam('orderId');

        $order = craft()->commerce_orders->getOrderById($orderId);

        if ($order && !$order->isCompleted)
        {
            if (craft()->commerce_orders->completeOrder($order))
            {
                $date = new DateTime($order->dateOrdered);
                $this->returnJson(['success' => true, 'dateOrdered' => $date]);
            }
        }

        $this->returnErrorJson(Craft::t("Could not mark the order as completed."));
    }

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionUpdateOrderAddress()
    {
        $this->requireAjaxRequest();

        $orderId = craft()->request->getParam('orderId');
        $addressId = craft()->request->getParam('addressId');
        $type = craft()->request->getParam('addressType');

        // Validate Address Type
        if (!in_array($type, ['shippingAddress', 'billingAddress']))
        {
            $this->returnErrorJson(Craft::t('Not a valid address type'));
        }

        $order = craft()->commerce_orders->getOrderById($orderId);
        if (!$order)
        {
            $this->returnErrorJson(Craft::t('Bad order ID.'));
        }

        // Return early if the address is already set.
        if ($order->{$type.'Id'} == $addressId)
        {
            $this->returnJson(['success' => true]);
        }

        // Validate Address Id
        $address = craft()->commerce_addresses->getAddressById($addressId);
        if (!$address)
        {
            $this->returnErrorJson(Craft::t('Bad address ID.'));
        }

        $order->{$type.'Id'} = $address->id;

        if (craft()->commerce_orders->saveOrder($order))
        {
            $this->returnJson(['success' => true]);
        }

        $this->returnErrorJson(Craft::t('Could not update orders address.'));
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
        $orderStatus = craft()->commerce_orderStatuses->getOrderStatusById($orderStatusId);

        if (!$order or !$orderStatus)
        {
            $this->returnErrorJson(Craft::t('Bad Order or Status'));
        }

        $order->orderStatusId = $orderStatus->id;
        $order->message = $message;

        if (craft()->commerce_orders->saveOrder($order))
        {
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

        if (craft()->commerce_orders->saveOrder($order))
        {
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
        $order = craft()->commerce_orders->getOrderById($orderId);

        if (!$order)
        {
            throw new Exception(Craft::t('No order with the ID “{id}”', ['id' => $orderId]));
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

        if (!$order)
        {
            throw new Exception(Craft::t('No order exists with the ID “{id}”.',
                ['id' => $orderId]));
        }

        if (craft()->commerce_orders->deleteOrder($order))
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson(['success' => true]);
            }
            else
            {
                craft()->userSession->setNotice(Craft::t('Order deleted.'));
                $this->redirectToPostedUrl($order);
            }
        }
        else
        {
            if (craft()->request->isAjaxRequest())
            {
                $this->returnJson(['success' => false]);
            }
            else
            {
                craft()->userSession->setError(Craft::t('Couldn’t delete order.'));
                craft()->urlManager->setRouteVariables(['order' => $order]);
            }
        }
    }
}
