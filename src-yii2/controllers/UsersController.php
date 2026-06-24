<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Subscription;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\controllers\EditUserTrait;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\web\CpScreenResponseBehavior;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class User Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class UsersController extends BaseFrontEndController
{
    use EditUserTrait;

    public const SCREEN_COMMERCE = 'commerce';

    /**
     * @param int|null $userId
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws \Throwable
     * @throws InvalidConfigException
     */
    public function actionIndex(?int $userId = null): Response
    {
        $user = $this->editedUser($userId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, 'commerce');

        $view = Craft::$app->getView();
        $view->registerAssetBundle(CommerceCpAsset::class);

        $config = [
            'context' => 'embedded-index',
            'sources' => false,
            'showSiteMenu' => true,
            'jsSettings' => [
                'criteria' => ['customerId' => $user->id],
            ],
        ];

        $edge = Plugin::getInstance()->getCarts()->getActiveCartEdgeDuration();

        $content = '';
        $key = 'Commerce-Users-element-indexes-%s';

        if (Craft::$app->getUser()->getIdentity()->can('commerce-manageOrders')) {
            $completedOrdersKey = sprintf($key, 'completed-orders');
            $activeCartsKey = sprintf($key, 'active-carts');
            $inactiveCartsKey = sprintf($key, 'inactive-carts');

            $content .= Html::tag('h2', Craft::t('commerce', 'Orders')) .
                Html::beginTag('div', ['class' => 'commerce-user-orders']) .
                Cp::elementIndexHtml(Order::class, ArrayHelper::merge($config, [
                    'id' => $completedOrdersKey,
                    'jsSettings' => [
                        'criteria' => ['isCompleted' => true],
                        'storageKey' => $completedOrdersKey,
                    ],
                ])) .
                Html::endTag('div') .

                Html::tag('hr') .

                Html::tag('h2', Craft::t('commerce', 'Active Carts')) .
                Html::beginTag('div', ['class' => 'commerce-user-active-carts']) .
                Cp::elementIndexHtml(Order::class, ArrayHelper::merge($config, [
                    'id' => $activeCartsKey,
                    'jsSettings' => [
                        'criteria' => [
                            'isCompleted' => false,
                            'dateUpdated' => '>= ' . $edge,
                        ],
                        'storageKey' => $activeCartsKey,
                    ],
                ])) .
                Html::endTag('div') .

                Html::tag('hr') .

                Html::tag('h2', Craft::t('commerce', 'Inactive Carts')) .
                Html::beginTag('div', ['class' => 'commerce-user-active-carts']) .
                Cp::elementIndexHtml(Order::class, ArrayHelper::merge($config, [
                    'id' => $inactiveCartsKey,
                    'jsSettings' => [
                        'criteria' => [
                            'isCompleted' => false,
                            'dateUpdated' => '< ' . $edge,
                        ],
                        'storageKey' => $inactiveCartsKey,
                    ],
                ])) .
                Html::endTag('div');
        }


        if (Craft::$app->getUser()->getIdentity()->can('commerce-manageSubscriptions') and !empty(Plugin::getInstance()->getPlans()->getAllPlans())) {
            $subscriptionsKey = sprintf($key, 'subscriptions');
            $content .= Html::tag('hr') .
                Html::tag('h2', Craft::t('commerce', 'Subscriptions')) .
                Html::beginTag('div', ['class' => 'commerce-user-subscriptions']) .
                    Cp::elementIndexHtml(Subscription::class, [
                        'id' => $subscriptionsKey,
                        'context' => 'embedded-index',
                        'sources' => false,
                        'jsSettings' => [
                            'criteria' => [
                                'userId' => $user->id,
                                'status' => null,
                            ],
                            'storageKey' => $subscriptionsKey,
                        ],
                    ]) .
                Html::endTag('div');
        }

        return $response->contentHtml($content);
    }
}
