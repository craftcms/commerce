<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\OrderSettings as OrderSettingsModel;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\web\HttpException;

/**
 * Class Order Settings Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class OrderSettings extends BaseAdmin
{
    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        $variables['orderSettings'] = Plugin::getInstance()->getOrderSettings->getOrderSettingByHandle('order');

        $variables['title'] = Craft::t('commerce', 'Order Settings');

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

        $orderSettings = new OrderSettingsModel();

        // Shared attributes
        $orderSettings->id = Craft::$app->getRequest()->getParam('orderSettingsId');
        $orderSettings->name = 'Order';
        $orderSettings->handle = 'order';

        // Set the field layout
        $fieldLayout = craft()->fields->assembleLayoutFromPost();
        $fieldLayout->type = 'Commerce_Order';
        $orderSettings->setFieldLayout($fieldLayout);

        // Save it
        if (Plugin::getInstance()->getOrderSettings()->saveOrderSetting($orderSettings)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Order settings saved.'));
            $this->redirectToPostedUrl($orderSettings);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save order settings.'));
        }

        Craft::$app->getUrlManager()->setRouteParams(['orderSettings' => $orderSettings]);
    }

}
