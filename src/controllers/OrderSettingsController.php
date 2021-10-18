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
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

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

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionSave(): ?Response
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