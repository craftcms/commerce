<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\gql\checkout\CheckoutSchema;
use craft\commerce\models\Checkout;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Manages the hosted checkouts’ settings.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class CheckoutsController extends BaseAdminController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce/settings/checkouts/index', [
            'checkouts' => Plugin::getInstance()->getCheckouts()->getAllCheckouts(),
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
    }

    /**
     * @param int|null $id
     * @param Checkout|null $checkout
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionEdit(?int $id = null, ?Checkout $checkout = null): Response
    {
        $plugin = Plugin::getInstance();

        if (!$checkout) {
            if ($id) {
                $checkout = $plugin->getCheckouts()->getCheckoutById($id);

                if (!$checkout) {
                    throw new NotFoundHttpException('Checkout not found');
                }
            } else {
                $checkout = new Checkout();
                // New checkouts start with every store selected
                $checkout->setStoreIds($plugin->getStores()->getAllStores()->pluck('id')->all());
            }
        }

        $stores = $plugin->getStores()->getAllStores();

        $gateways = $plugin->getGateways()->getAllGateways()->map(fn($gateway) => [
            'id' => $gateway->id,
            'name' => $gateway->name,
            'handle' => $gateway->handle,
            'supported' => CheckoutSchema::gatewayType($gateway) !== null,
            'frontendEnabled' => (bool)$gateway->getIsFrontendEnabled(),
            'cpEditUrl' => $gateway->getCpEditUrl(),
        ])->all();

        // The store has the final say on partial payments, so the form needs to know which stores allow them
        $storePartialPayments = $stores->mapWithKeys(fn($store) => [$store->id => [
            'name' => $store->getName(),
            'allowed' => (bool)$store->getAllowPartialPaymentOnCheckout(),
            'url' => UrlHelper::cpUrl('commerce/settings/stores/' . $store->id),
        ]])->all();

        $title = $checkout->id ? $checkout->name : Craft::t('commerce', 'Create a new checkout');

        return $this->asCpScreen()
            ->title($title)
            ->crumbs([
                ['label' => Craft::t('commerce', 'Commerce'), 'url' => 'commerce'],
                ['label' => Craft::t('app', 'Settings'), 'url' => 'commerce/settings', 'ariaLabel' => Craft::t('commerce', 'Commerce Settings')],
                ['label' => Craft::t('commerce', 'Checkouts'), 'url' => 'commerce/settings/checkouts'],
            ])
            ->tabs([
                'general' => ['label' => Craft::t('commerce', 'General'), 'url' => '#general'],
                'payments' => ['label' => Craft::t('commerce', 'Payments'), 'url' => '#payments'],
                'appearance' => ['label' => Craft::t('commerce', 'Appearance'), 'url' => '#appearance'],
                'links' => ['label' => Craft::t('commerce', 'Links'), 'url' => '#links'],
                'requirements' => ['label' => Craft::t('commerce', 'Requirements'), 'url' => '#requirements'],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/checkouts/save')
            ->redirectUrl('commerce/settings/checkouts')
            ->metaSidebarTemplate('commerce/settings/checkouts/_sidebar', [
                'checkout' => $checkout,
                'readOnly' => $this->isReadOnlyScreen(),
            ])
            ->contentTemplate('commerce/settings/checkouts/_edit', [
                'checkout' => $checkout,
                'stores' => $stores,
                'gateways' => $gateways,
                'storePartialPayments' => $storePartialPayments,
                'colorSchemeOptions' => Checkout::getColorSchemeOptions(),
                'cornerStyleOptions' => Checkout::getCornerStyleOptions(),
                'readOnly' => $this->isReadOnlyScreen(),
            ]);
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $service = Plugin::getInstance()->getCheckouts();
        $id = $this->request->getBodyParam('id');

        if ($id) {
            $existing = $service->getCheckoutById((int)$id);

            if (!$existing) {
                throw new BadRequestHttpException("Invalid checkout ID: $id");
            }

            $checkout = $existing;
        } else {
            $checkout = new Checkout();
        }

        $checkout->name = $this->request->getBodyParam('name');
        $checkout->handle = $this->request->getBodyParam('handle');
        $checkout->enabled = (bool)$this->request->getBodyParam('enabled');
        $checkout->isDefault = (bool)$this->request->getBodyParam('isDefault');
        $checkout->setStoreIds((array)$this->request->getBodyParam('storeIds', []));
        $checkout->setGatewayIds((array)$this->request->getBodyParam('gatewayIds', []));
        $checkout->brandColor = $this->request->getBodyParam('brandColor') ?: null;
        $checkout->colorScheme = $this->request->getBodyParam('colorScheme', Checkout::COLOR_SCHEME_LIGHT);
        $checkout->cornerStyle = $this->request->getBodyParam('cornerStyle', Checkout::CORNER_STYLE_ROUNDED);
        $checkout->logoUrl = $this->request->getBodyParam('logoUrl') ?: null;
        $checkout->returnUrl = $this->request->getBodyParam('returnUrl') ?: null;
        $checkout->continueShoppingUrl = $this->request->getBodyParam('continueShoppingUrl') ?: null;
        $checkout->allowPartialPayment = (bool)$this->request->getBodyParam('allowPartialPayment');
        $checkout->setPartialPaymentPercentages($this->request->getBodyParam('partialPaymentPercentages'));
        $requireBilling = $this->request->getBodyParam('requireBillingAddress');
        $checkout->requireBillingAddress = ($requireBilling === null || $requireBilling === '') ? null : (bool)(int)$requireBilling;
        $checkout->orderConditionMessage = $this->request->getBodyParam('orderConditionMessage') ?: null;
        $checkout->setOrderCondition($this->request->getBodyParam('orderCondition'));

        if (!$service->saveCheckout($checkout)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save checkout.'));
            Craft::$app->getUrlManager()->setRouteParams(['checkout' => $checkout]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Checkout saved.'));

        return $this->redirectToPostedUrl($checkout);
    }

    /**
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = (int)$this->request->getRequiredBodyParam('id');

        if (!Plugin::getInstance()->getCheckouts()->deleteCheckoutById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t delete the checkout.'));
        }

        return $this->asSuccess();
    }
}
