<?php
namespace Craft;

/**
 * Class Commerce_OrderSettingsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_OrderSettingsController extends Commerce_BaseAdminController
{
    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        $variables['orderSettings'] = craft()->commerce_orderSettings->getOrderSettingByHandle('order');

        $variables['title'] = Craft::t('Order Settings');

        $this->renderTemplate('commerce/settings/ordersettings/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $orderSettings = new Commerce_OrderSettingsModel();

        // Shared attributes
        $orderSettings->id = craft()->request->getPost('orderSettingsId');
        $orderSettings->name = 'Order';
        $orderSettings->handle = 'order';

        // Set the field layout
        $fieldLayout = craft()->fields->assembleLayoutFromPost();
        $fieldLayout->type = 'Commerce_Order';
        $orderSettings->setFieldLayout($fieldLayout);

        // Save it
        if (craft()->commerce_orderSettings->saveOrderSetting($orderSettings)) {
            craft()->userSession->setNotice(Craft::t('Order settings saved.'));
            $this->redirectToPostedUrl($orderSettings);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save order settings.'));
        }

        craft()->urlManager->setRouteVariables(['orderSettings' => $orderSettings]);
    }

}
