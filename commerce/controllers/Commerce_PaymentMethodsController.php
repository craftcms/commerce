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
        $paymentMethods = craft()->commerce_paymentMethods->getAllPaymentMethods();
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
        if (empty($variables['paymentMethod']))
        {
            if (!empty($variables['id']))
            {
                $id = $variables['id'];
                $variables['paymentMethod'] = craft()->commerce_paymentMethods->getPaymentMethodById($id);

                if (!$variables['paymentMethod'])
                {
                    throw new HttpException(404);
                }
            }
            else
            {
                $variables['paymentMethod'] = new Commerce_PaymentMethodModel();
            }
        }

        $variables['gateways'] = craft()->commerce_gateways->getAllGateways();
        $list = [];
        foreach ($variables['gateways'] as $gw)
        {
            $list[$gw->handle()] = $gw->displayName();
        }
        asort($list);

        $variables['paymentMethod']->getGateway(); //init gateway settings
        $variables['gatewaysList'] = $list;
        if ($variables['paymentMethod']->id)
        {
            $variables['title'] = $variables['paymentMethod']->name;
        }
        else
        {
            $variables['title'] = Craft::t('Create a new payment method');
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
        $paymentMethod->frontendEnabled = craft()->request->getPost('frontendEnabled');


        // Check if settings have been overridden in config.
        $configSettings = craft()->config->get('paymentMethodSettings', 'commerce');
        if (!isset($configSettings[$paymentMethod->id]))
        {
            $paymentMethod->settings = craft()->request->getPost('settings', []);
        }
        else
        {
            if ($paymentMethod->id)
            {
                // We need to get this directly from the database since the model populateModel fills from config file.
                $method = craft()->db->createCommand()->select('*')->where('id = '.$paymentMethod->id)->from('commerce_paymentmethods')->queryRow();
                if ($method)
                {
                    $paymentMethod->settings = $method['settings'];
                }
            }
        }

        // Save it
        if (craft()->commerce_paymentMethods->savePaymentMethod($paymentMethod))
        {
            craft()->userSession->setNotice(Craft::t('Payment method saved.'));
            $this->redirectToPostedUrl($paymentMethod);
        }
        else
        {
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
    public function actionArchive()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        if (craft()->commerce_paymentMethods->archivePaymentMethod($id))
        {
            $this->returnJson(['success' => true]);
        };

        $this->returnErrorJson(Craft::t('Could not archive payment method'));
    }

    /**
     * @throws HttpException
     */
    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $ids = JsonHelper::decode(craft()->request->getRequiredPost('ids'));
        if ($success = craft()->commerce_paymentMethods->reorderPaymentMethods($ids))
        {
            return $this->returnJson(['success' => $success]);
        };

        return $this->returnJson(['error' => Craft::t('Couldn’t reorder Payment Methods.')]);
    }

}
