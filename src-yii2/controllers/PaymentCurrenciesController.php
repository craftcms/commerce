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
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\i18n\Formatter;
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
        $this->getView()->registerTranslations('commerce', [
            'Base',
            'Code',
            'Conversion Rate',
            'Currency',
            'No additional payment currencies exist yet.',
            'Warning, deleting this currency will stop all payments and refunds in this currency, are you sure you want to delete “{name}”?',
        ]);

        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $currencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($store->id);

        return $this->asStoreManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(
                Craft::t('commerce', 'New currency'), "commerce/store-management/$storeHandle/payment-currencies/new",
                ['class' => 'btn submit add icon']
            ))
            ->contentTemplate('commerce/store-management/paymentcurrencies/index', compact('currencies', 'store'));
    }

    /**
     * @param int|null $id
     * @param PaymentCurrency|null $currency
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(?int $id = null, ?PaymentCurrency $currency = null, ?string $storeHandle = null): Response
    {
        if ($storeHandle) {
            $store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle);
            if ($store === null) {
                throw new InvalidConfigException('Invalid store.');
            }
        } else {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if (!$currency) {
            if ($id) {
                $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($id, $store->id);

                if (!$currency || $currency->storeId !== $store->id) {
                    throw new HttpException(404);
                }
            } else {
                $currency = Craft::createObject([
                    'class' => PaymentCurrency::class,
                    'storeId' => $store->id,
                ]);
            }
        }

        if ($currency->id) {
            $title = $currency->iso; // @TODO Use the full currency name instead of the ISO code for the page title
        } else {
            $title = Craft::t('commerce', 'Create a new currency');
        }

        $storeCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $currencyOptions = Plugin::getInstance()->getCurrencies()->getAllCurrenciesList();
        $hasCompletedOrders = Order::find()->isCompleted(true)->exists();

        $formatter = Craft::$app->getFormatter();

        $metaSidebarHtml = $currency->id ? Cp::metadataHtml([
            Craft::t('app', 'Created at') => $formatter->asDateTime($currency->dateCreated, Formatter::FORMAT_WIDTH_SHORT),
            Craft::t('app', 'Updated at') => $formatter->asDateTime($currency->dateUpdated, Formatter::FORMAT_WIDTH_SHORT),
        ]) : '';

        return $this->asStoreManagementCpScreen($storeHandle, false)
            ->addCrumb(Craft::t('commerce','Payment Currencies'), "commerce/store-management/$storeHandle/payment-currencies")
            ->metaSidebarHtml($metaSidebarHtml)
            ->action('commerce/payment-currencies/save')
            ->redirectUrl("commerce/store-management/$storeHandle/payment-currencies")
            ->submitButtonLabel(Craft::t('app', 'Save'))
            ->contentTemplate('commerce/store-management/paymentcurrencies/_edit', [
                'id' => $id,
                'currency' => $currency,
                'title' => $title,
                'storeCurrency' => $storeCurrency,
                'currencyOptions' => $currencyOptions,
                'store' => $store,
                'hasCompletedOrders' => $hasCompletedOrders,
            ]);
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
