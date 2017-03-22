<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use yii\web\HttpException;

/**
 * Class Currencies Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.2
 */
class PaymentCurrenciesController extends BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $currencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();
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
                $variables['currency'] = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($id);

                if (!$variables['currency']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['currency'] = new PaymentCurrency();
            }
        }

        if (!empty($variables['id'])) {
            if ($variables['currency']->primary) {
                $variables['title'] = $variables['currency']->currency.' ('.$variables['currency']->iso.')';
            } else {
                $variables['title'] = $variables['currency']->currency.' ('.$variables['currency']->iso.')';
            }
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new currency');
        }

        $variables['storeCurrency'] = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencies'] = array_keys(Plugin::getInstance()->getCurrencies()->getAllCurrencies());

        $this->renderTemplate('commerce/settings/paymentcurrencies/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $currency = new PaymentCurrency();

        // Shared attributes
        $currency->id = Craft::$app->getRequest()->getParam('currencyId');
        $currency->iso = Craft::$app->getRequest()->getParam('iso');
        $currency->rate = Craft::$app->getRequest()->getParam('rate');
        $currency->primary = Craft::$app->getRequest()->getParam('primary');

        // Save it
        if (Plugin::getInstance()->getPaymentCurrencies()->savePaymentCurrency($currency)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Currency saved.'));
            $this->redirectToPostedUrl($currency);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save currency.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['currency' => $currency]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($id);

        if ($currency && !$currency->primary) {
            Plugin::getInstance()->getPaymentCurrencies()->deletePaymentCurrencyById($id);
            $this->asJson(['success' => true]);
        }

        $message = Craft::t('commerce', 'You can not delete that currency.');
        $this->asErrorJson($message);
    }

}
