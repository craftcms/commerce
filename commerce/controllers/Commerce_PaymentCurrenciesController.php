<?php
namespace Craft;

/**
 * Class Commerce_CurrenciesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.2
 */
class Commerce_PaymentCurrenciesController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $currencies = craft()->commerce_paymentCurrencies->getAllPaymentCurrencies();
        $this->renderTemplate('commerce/settings/paymentcurrencies/index', compact('currencies'));
    }

    /**
     * Create/Edit Currency
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['currency'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['currency'] = craft()->commerce_paymentCurrencies->getPaymentCurrencyById($id);

                if (!$variables['currency']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['currency'] = new Commerce_PaymentCurrencyModel();
            }
        }

        if (!empty($variables['id'])) {
            if ($variables['currency']->primary)
            {
                $variables['title'] = $variables['currency']->currency.' ('.$variables['currency']->iso.')';
            }else{
                $variables['title'] = $variables['currency']->currency.' ('.$variables['currency']->iso.')';
            }
        } else {
            $variables['title'] = Craft::t('Create a new currency');
        }

        $variables['storeCurrency'] = craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrencyIso();
        $variables['currencies'] = array_keys(craft()->commerce_currencies->getAllCurrencies());

        $this->renderTemplate('commerce/settings/paymentcurrencies/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $currency = new Commerce_PaymentCurrencyModel();

        // Shared attributes
        $currency->id = craft()->request->getPost('currencyId');
        $currency->iso = craft()->request->getPost('iso');
        $currency->rate = craft()->request->getPost('rate');
        $currency->primary = craft()->request->getPost('primary');

        // Save it
        if (craft()->commerce_paymentCurrencies->savePaymentCurrency($currency)) {
            craft()->userSession->setNotice(Craft::t('Currency saved.'));
            $this->redirectToPostedUrl($currency);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save currency.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['currency' => $currency]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');
        $currency = craft()->commerce_paymentCurrencies->getPaymentCurrencyById($id);

        if ($currency && !$currency->primary)
        {
            craft()->commerce_paymentCurrencies->deletePaymentCurrencyById($id);
            $this->returnJson(['success' => true]);
        }

        $message = Craft::t('You can not delete that currency.');
        $this->returnErrorJson($message);
    }

}
