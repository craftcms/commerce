<?php
namespace Craft;

/**
 * Class Commerce_PaymentMethodsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_PaymentMethodsController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $paymentMethods = craft()->commerce_paymentMethods->getAllPaymentMethods(['order' => 'name']);
        $this->renderTemplate('commerce/settings/paymentmethods/index', compact('paymentMethods'));
    }

    /**
     * Create/Edit PaymentMethod
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['paymentMethod'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['paymentMethod'] = craft()->commerce_paymentMethods->getPaymentMethodById($id);

                if (!$variables['paymentMethod']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['paymentMethod'] = new Commerce_PaymentMethodModel();
            }
        }

        $variables['gateways'] = craft()->commerce_gateways->getAllGateways();
        $list = [];
        foreach ($variables['gateways'] as $gw) {
            $list[$gw->handle()] = $gw->displayName();
        }
        asort($list);

        $variables['paymentMethod']->getGatewayAdapter(); //init gateway settings
        $variables['gatewaysList'] = $list;
        if ($variables['paymentMethod']->id) {
            $variables['title'] = $variables['paymentMethod']->name;
        } else {
            $variables['title'] = 'Create a new payment method';
        }
        $this->renderTemplate('commerce/settings/paymentmethods/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $paymentMethod = new Commerce_PaymentMethodModel();

        // Shared attributes
        $paymentMethod->id = craft()->request->getRequiredPost('id');
        $paymentMethod->name = craft()->request->getRequiredPost('name');
        $paymentMethod->paymentType = craft()->request->getRequiredPost('paymentType');
        $paymentMethod->class = craft()->request->getRequiredPost('class');
        $paymentMethod->settings = craft()->request->getPost('settings', []);
        $paymentMethod->frontendEnabled = craft()->request->getPost('frontendEnabled');

        // Save it
        if (craft()->commerce_paymentMethods->savePaymentMethod($paymentMethod)) {
            craft()->userSession->setNotice(Craft::t('Payment method saved.'));
            $this->redirectToPostedUrl($paymentMethod);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save payment method.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables([
            'paymentMethod' => $paymentMethod
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_paymentMethods->deleteById($id);
        $this->returnJson(['success' => true]);
    }

}
