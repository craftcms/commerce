<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\Exception as DbException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Currencies Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentCurrenciesController extends BaseStoreManagementController
{
    /**
     * @throws CurrencyException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $currencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($store->id);
        return $this->renderTemplate('commerce/store-management/paymentcurrencies/index', compact('currencies', 'store'));
    }

    /**
     * @param int|null $id
     * @param PaymentCurrency|null $currency
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(int $id = null, PaymentCurrency $currency = null, string $storeHandle = null): Response
    {
        $variables = compact('id', 'currency');

        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            if ($store === null) {
                throw new InvalidConfigException('Invalid store.');
            }
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if (!$variables['currency']) {
            if ($variables['id']) {
                $variables['currency'] = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($variables['id'], $store->id);

                if (!$variables['currency'] || $variables['currency']->storeId !== $store->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['currency'] = Craft::createObject([
                    'class' => PaymentCurrency::class,
                    'storeId' => $store->id,
                ]);
            }
        }

        if ($variables['currency']->id) {
            $variables['title'] = $variables['currency']->iso; // TODO: get the currency name
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new currency');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['currency'], prepend: true);

        $variables['storeCurrency'] = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencyOptions'] = Plugin::getInstance()->getCurrencies()->getAllCurrenciesList();
        $variables['store'] = $store;
        $variables['hasCompletedOrders'] = Order::find()->isCompleted(true)->exists();

        return $this->renderTemplate('commerce/store-management/paymentcurrencies/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws DbException
     * @throws BadRequestHttpException
     */
    public function actionSave(): void
    {
        $this->requirePostRequest();

        $currency = new PaymentCurrency();

        // Shared attributes
        $currency->id = $this->request->getBodyParam('currencyId');
        $currency->storeId = $this->request->getBodyParam('storeId');
        $currency->iso = $this->request->getBodyParam('iso');
        $currency->rate = $this->request->getBodyParam('rate', 1);

        // Save it
        if (Plugin::getInstance()->getPaymentCurrencies()->savePaymentCurrency($currency)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Currency saved.'));
            $this->redirectToPostedUrl($currency);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save currency.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['currency' => $currency]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        if (!Plugin::getInstance()->getPaymentCurrencies()->deletePaymentCurrencyById($id)) {
            return $this->asFailure();
        }

        return $this->asSuccess();
    }
}
