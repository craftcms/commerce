<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\services\Orders;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use yii\web\Response;

/**
 * Class Order Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderSettingsController extends BaseAdminController
{
    /**
     * @param array $variables
     * @return Response
     */
    public function actionEdit(array $variables = []): Response
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Order::class);

        $variables['fieldLayout'] = $fieldLayout;
        $variables['title'] = Craft::t('commerce', 'Order Settings');

        return $this->renderTemplate('commerce/settings/ordersettings/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();

        $fieldLayout->reservedFieldHandles = [
            'billingAddress',
            'customer',
            'estimatedBillingAddress',
            'estimatedShippingAddress',
            'paymentAmount',
            'paymentCurrency',
            'paymentSource',
            'recalculationMode',
            'shippingAddress',
        ];

        if (!$fieldLayout->validate()) {
            Craft::info('Field layout not saved due to validation error.', __METHOD__);

            Craft::$app->getUrlManager()->setRouteParams([
                'variables' => [
                    'fieldLayout' => $fieldLayout,
                ],
            ]);

            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save order fields.'));
            return null;
        }

        if ($currentOrderFieldLayout = Craft::$app->getProjectConfig()->get(Orders::CONFIG_FIELDLAYOUT_KEY)) {
            $uid = ArrayHelper::firstKey($currentOrderFieldLayout);
        } else {
            $uid = StringHelper::UUID();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        Craft::$app->getProjectConfig()->set(Orders::CONFIG_FIELDLAYOUT_KEY, $configData);

        $this->setSuccessFlash(Craft::t('commerce', 'Order fields saved.'));

        return $this->redirectToPostedUrl();
    }
}