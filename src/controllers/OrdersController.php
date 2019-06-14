<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\errors\RefundException;
use craft\commerce\errors\TransactionException;
use craft\commerce\gateways\MissingGateway;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\web\assets\commerceui\CommerceUiAsset;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\web\View;
use DateTime;
use Throwable;
use Twig_Error_Loader;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Orders Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrdersController extends BaseCpController
{
    // Public Methods
    // =========================================================================

    /**
     * @throws HttpException
     */
    public function init()
    {
        $this->requirePermission('commerce-manageOrders');

        parent::init();
    }



    /**
     * @param int $orderId
     * @param Order $order
     * @return Response
     * @throws HttpException
     */
    public function actionEditOrder($orderId, Order $order = null): Response
    {
        $plugin = Plugin::getInstance();
        $variables = [
            'orderId' => $orderId,
            'order' => $order,
            'fieldLayout' => Craft::$app->getFields()->getLayoutByType(Order::class)
        ];

        if (empty($variables['order']) && !empty($variables['orderId'])) {
            $variables['order'] = $plugin->getOrders()->getOrderById($variables['orderId']);

            if (!$variables['order']) {
                throw new HttpException(404);
            }
        }

        if (!empty($variables['orderId'])) {
            $variables['title'] = $variables['order']->reference ? 'Order ' . $variables['order']->reference : 'Cart ' . $variables['order']->number;
        } else {
            throw new HttpException(404);
        }

        $this->_prepVariables($variables);

        $variables['paymentMethodsAvailable'] = false;

        if (empty($variables['paymentForm'])) {
            /** @var Gateway $gateway */
            $gateway = $variables['order']->getGateway();

            if ($gateway && !$gateway instanceof MissingGateway) {
                $variables['paymentForm'] = $gateway->getPaymentFormModel();
            } else {
                $gateway = ArrayHelper::firstValue($plugin->getGateways()->getAllGateways());

                if ($gateway && !$gateway instanceof MissingGateway) {
                    $variables['paymentForm'] = $gateway->getPaymentFormModel();
                }
            }

            if ($gateway instanceof MissingGateway) {
                $variables['paymentMethodsAvailable'] = false;
            }
        }

        // New order edit global data
        Craft::$app->getView()->registerAssetBundle(CommerceUiAsset::class);

        Craft::$app->getView()->registerJs('window.orderEdit = {};', View::POS_BEGIN);

        Craft::$app->getView()->registerJs('window.orderEdit.orderId = ' . $orderId . ';', View::POS_BEGIN);

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

        $pdfUrls = [
            'Download PDF' => $variables['order']->getPdfUrl()
        ];
        Craft::$app->getView()->registerJs('window.orderEdit.pdfUrls = '.Json::encode(ArrayHelper::toArray($pdfUrls)) . ';', View::POS_BEGIN);

        return $this->renderTemplate('commerce/orders/_edit', $variables);
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
     * Completes Order
     *
     * @return Response
     * @throws Exception
     * @throws Throwable
     * @throws OrderStatusException
     * @throws ElementNotFoundException
     * @throws BadRequestHttpException
     */
    public function actionCompleteOrder(): Response
    {
        $this->requireAcceptsJson();
        $orderId = Craft::$app->getRequest()->getParam('orderId');

        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if ($order && !$order->isCompleted && $order->markAsComplete()) {
            $date = new DateTime($order->dateOrdered);
            return $this->asJson(['success' => true, 'dateOrdered' => $date]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could not mark the order as completed.'));
    }




    // Private Methods
    // =========================================================================

    /**
     * Modifies the variables of the request.
     *
     * @param $variables
     */
    private function _prepVariables(&$variables)
    {
        /** @var Order $order */
        $order = $variables['order'];

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
    }

    /**
     * @return Order
     * @throws Exception
     */
    private function _setOrderFromPost(): Order
    {
        $orderId = Craft::$app->getRequest()->getBodyParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new Exception(Craft::t('commerce', 'No order with the ID “{id}”', ['id' => $orderId]));
        }

        $order->setFieldValuesFromRequest('fields');

        return $order;
    }
}
