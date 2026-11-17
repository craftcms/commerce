<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Users;

use CraftCms\Cms\Element\EmbeddedElementIndex;
use CraftCms\Cms\Http\Controllers\Users\EditUserTrait;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

readonly class UsersController
{
    use EditUserTrait;

    public const string SCREEN_COMMERCE = 'commerce';

    public function index(?int $userId = null): CpScreenResponse
    {
        $user = $this->editedUser($userId);

        $response = $this->asEditUserScreen($user, self::SCREEN_COMMERCE);

        if (!currentUser()?->can('commerce-manageOrders')) {
            return $response->contentHtml('');
        }

        $edge = app(Carts::class)->getActiveCartEdgeDuration();

        $indexes = [
            'completed-orders' => [t('Orders', category: 'commerce'), ['isCompleted' => true]],
            'active-carts' => [t('Active Carts', category: 'commerce'), ['isCompleted' => false, 'dateUpdated' => '>= ' . $edge]],
            'inactive-carts' => [t('Inactive Carts', category: 'commerce'), ['isCompleted' => false, 'dateUpdated' => '< ' . $edge]],
        ];

        $ui = Ui::make();

        foreach ($indexes as $key => [$label, $criteria]) {
            $ui->addTab($label, [
                EmbeddedElementIndex::make(Order::class)
                    ->criteria([...$criteria, 'customerId' => $user->id, 'status' => null])
                    ->storageKey("Commerce-Users-element-indexes-$key")
                    ->defaultTableColumns($key === 'completed-orders'
                        ? ['reference', 'orderStatus', 'dateOrdered', 'totalPrice']
                        : ['reference', 'dateUpdated', 'totalPrice'])
                    ->defaultSort($key === 'completed-orders' ? 'dateOrdered' : 'dateUpdated', 'desc')
                    ->uiNode("$key-index"),
            ], $key);
        }

        return $response->ui($ui);
    }
}
