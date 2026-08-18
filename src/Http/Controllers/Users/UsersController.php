<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Users;

use craft\commerce\Plugin;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Cp\Html\ElementIndexHtml;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Http\Controllers\Users\EditUserTrait;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

readonly class UsersController
{
    use EditUserTrait;

    public const string SCREEN_COMMERCE = 'commerce';

    public function __construct(
        private ElementIndexHtml $elementIndexHtml,
    ) {
    }

    public function index(?int $userId = null): CpScreenResponse
    {
        $user = $this->editedUser($userId);

        $response = $this->asEditUserScreen($user, self::SCREEN_COMMERCE);

        \Craft::$app->getView()->registerAssetBundle(CommerceCpAsset::class);

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

        if (currentUser()?->can('commerce-manageOrders')) {
            $completedOrdersKey = sprintf($key, 'completed-orders');
            $activeCartsKey = sprintf($key, 'active-carts');
            $inactiveCartsKey = sprintf($key, 'inactive-carts');

            $content .= Html::tag('h2', t('Orders', category: 'commerce')) .
                Html::beginTag('div', ['class' => 'commerce-user-orders']) .
                $this->elementIndexHtml->html(Order::class, Arr::merge($config, [
                    'id' => $completedOrdersKey,
                    'jsSettings' => [
                        'criteria' => ['isCompleted' => true],
                        'storageKey' => $completedOrdersKey,
                    ],
                ])) .
                Html::endTag('div') .

                Html::tag('hr') .

                Html::tag('h2', t('Active Carts', category: 'commerce')) .
                Html::beginTag('div', ['class' => 'commerce-user-active-carts']) .
                $this->elementIndexHtml->html(Order::class, Arr::merge($config, [
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

                Html::tag('h2', t('Inactive Carts', category: 'commerce')) .
                Html::beginTag('div', ['class' => 'commerce-user-active-carts']) .
                $this->elementIndexHtml->html(Order::class, Arr::merge($config, [
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

        return $response->contentHtml($content);
    }
}
