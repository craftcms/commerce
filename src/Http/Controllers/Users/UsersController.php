<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Users;

use CraftCms\Cms\Element\EmbeddedElementIndex;
use CraftCms\Cms\Http\Controllers\Users\EditUserTrait;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\Separator;
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
            return $response;
        }

        $edge = app(Carts::class)->getActiveCartEdgeDuration();
        $index = fn(string $key, array $criteria) => EmbeddedElementIndex::make(Order::class)
            ->criteria(['customerId' => $user->id, ...$criteria])
            ->storageKey("Commerce-Users-element-indexes-$key")
            ->uiNode("commerce-user-$key");

        return $response->ui(Ui::make([
            Heading::make('commerce-user-orders-heading', t('Orders', category: 'commerce')),
            $index('completed-orders', ['isCompleted' => true]),
            Separator::make('commerce-user-orders-separator'),
            Heading::make('commerce-user-active-carts-heading', t('Active Carts', category: 'commerce')),
            $index('active-carts', ['isCompleted' => false, 'dateUpdated' => '>= ' . $edge]),
            Separator::make('commerce-user-active-carts-separator'),
            Heading::make('commerce-user-inactive-carts-heading', t('Inactive Carts', category: 'commerce')),
            $index('inactive-carts', ['isCompleted' => false, 'dateUpdated' => '< ' . $edge]),
        ]));
    }
}
