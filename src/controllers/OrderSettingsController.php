<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderSettings as OrderSettingsModel;
use craft\commerce\Plugin;
use yii\web\Response;

/**
 * Class Order Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderSettingsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @param array $variables
     * @return Response
     */
    public function actionEdit(array $variables = []): Response
    {
        $variables['orderSettings'] = Plugin::getInstance()->getOrderSettings()->getOrderSettingByHandle('order');

        $variables['title'] = Craft::t('commerce', 'Order Settings');

        return $this->renderTemplate('commerce/settings/ordersettings/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $orderSettings = new OrderSettingsModel();

        // Shared attributes
        $orderSettings->id = Craft::$app->getRequest()->getBodyParam('orderSettingsId');
        $orderSettings->name = 'Order';
        $orderSettings->handle = 'order';

        // Set the field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Order::class;
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
