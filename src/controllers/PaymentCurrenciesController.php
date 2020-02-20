<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Currencies Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentCurrenciesController extends BaseStoreSettingsController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $currencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();

        return $this->renderTemplate('commerce/store-settings/paymentcurrencies/index', compact('currencies'));
    }

    /**
     * @param int|null $id
     * @param PaymentCurrency|null $currency
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, PaymentCurrency $currency = null): Response
    {
        $variables = compact('id', 'currency');

        if (!$variables['currency']) {
            if ($variables['id']) {
                $variables['currency'] = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($variables['id']);

                if (!$variables['currency']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['currency'] = new PaymentCurrency();
            }
        }

        if ($variables['currency']->id) {
            if ($variables['currency']->primary) {
                $variables['title'] = $variables['currency']->currency . ' (' . $variables['currency']->iso . ')';
            } else {
                $variables['title'] = $variables['currency']->currency . ' (' . $variables['currency']->iso . ')';
            }
        } else {
            $variables['title'] = Plugin::t('Create a new currency');
        }

        $variables['storeCurrency'] = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencies'] = array_keys(Plugin::getInstance()->getCurrencies()->getAllCurrencies());

        return $this->renderTemplate('commerce/store-settings/paymentcurrencies/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $currency = new PaymentCurrency();

        // Shared attributes
        $currency->id = Craft::$app->getRequest()->getBodyParam('currencyId');
        $currency->iso = Craft::$app->getRequest()->getBodyParam('iso');
        $currency->rate = Craft::$app->getRequest()->getBodyParam('rate');
        $currency->primary = (bool)Craft::$app->getRequest()->getBodyParam('primary');

        // Save it
        if (Plugin::getInstance()->getPaymentCurrencies()->savePaymentCurrency($currency)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Currency saved.'));
            $this->redirectToPostedUrl($currency);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save currency.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['currency' => $currency]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($id);

        if ($currency && !$currency->primary) {
            Plugin::getInstance()->getPaymentCurrencies()->deletePaymentCurrencyById($id);
            return $this->asJson(['success' => true]);
        }

        $message = Plugin::t('You can not delete that currency.');
        return $this->asErrorJson($message);
    }
}
