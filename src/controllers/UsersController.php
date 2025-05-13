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

        if (Craft::$app->getUser()->getIdentity()->can('commerce-manageOrders')) {
            $content .= Html::tag('h2', Craft::t('commerce', 'Orders')) .
                Html::beginTag('div', ['class' => 'commerce-user-orders']) .
                Cp::elementIndexHtml(Order::class, ArrayHelper::merge($config, [
                    'id' => sprintf('element-index-%s', mt_rand()),
                    'jsSettings' => [
                        'criteria' => ['isCompleted' => true],
                    ],
                ])) .
                Html::endTag('div') .

                Html::tag('hr') .

                Html::tag('h2', Craft::t('commerce', 'Active Carts')) .
                Html::beginTag('div', ['class' => 'commerce-user-active-carts']) .
                Cp::elementIndexHtml(Order::class, ArrayHelper::merge($config, [
                    'id' => sprintf('element-index-%s', mt_rand()),
                    'jsSettings' => [
                        'criteria' => [
                            'isCompleted' => false,
                            'dateUpdated' => '>= ' . $edge,
                        ],
                    ],
                ])) .
                Html::endTag('div') .

                Html::tag('hr') .

                Html::tag('h2', Craft::t('commerce', 'Inactive Carts')) .
                Html::beginTag('div', ['class' => 'commerce-user-active-carts']) .
                Cp::elementIndexHtml(Order::class, ArrayHelper::merge($config, [
                    'id' => sprintf('element-index-%s', mt_rand()),
                    'jsSettings' => [
                        'criteria' => [
                            'isCompleted' => false,
                            'dateUpdated' => '< ' . $edge,
                        ],
                    ],
                ])) .
                Html::endTag('div');
        }


        if (Craft::$app->getUser()->getIdentity()->can('commerce-manageSubscriptions') and !empty(Plugin::getInstance()->getPlans()->getAllPlans())) {
            $content .= Html::tag('hr') .
                Html::tag('h2', Craft::t('commerce', 'Subscriptions')) .
                Html::beginTag('div', ['class' => 'commerce-user-active-carts']) .
                    Cp::elementIndexHtml(Subscription::class, [
                        'id' => sprintf('element-index-%s', mt_rand()),
                        'context' => 'embedded-index',
                        'sources' => false,
                        'jsSettings' => [
                            'criteria' => [
                                'userId' => $user->id,
                                'status' => null,
                            ],
                        ],
                    ]) .
                Html::endTag('div');
        }

        return $response->contentHtml($content);
    }
}
