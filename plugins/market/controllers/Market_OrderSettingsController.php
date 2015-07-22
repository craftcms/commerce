<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_OrderSettingsController extends Market_BaseController
{
    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        $variables['orderSettings'] = craft()->market_orderSettings->getByHandle('order');

        $variables['title'] = Craft::t('Order Settings');

        $this->renderTemplate('market/settings/ordersettings/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $orderSettings = new Market_OrderSettingsModel();

        // Shared attributes
        $orderSettings->id               = craft()->request->getPost('orderSettingsId');
        $orderSettings->name             = 'Order';
        $orderSettings->handle           = 'order';

        // Set the field layout
        $fieldLayout       = craft()->fields->assembleLayoutFromPost();
        $fieldLayout->type = 'Market_Order';
        $orderSettings->setFieldLayout($fieldLayout);

        // Save it
        if (craft()->market_orderSettings->save($orderSettings)) {
            craft()->userSession->setNotice(Craft::t('Order settings saved.'));
            $this->redirectToPostedUrl($orderSettings);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save order settings.'));
        }

        craft()->urlManager->setRouteVariables(['orderSettings' => $orderSettings]);
    }

} 