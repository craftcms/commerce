<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Users;

use CraftCms\Cms\Http\Controllers\Users\EditUserTrait;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
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

        return $response->inertiaPage('commerce::users/Commerce', [
            'elementType' => Order::class,
            'sections' => currentUser()?->can('commerce-manageOrders') ? $this->sections($user->id) : [],
        ]);
    }

    /**
     * @return list<array{key: string, heading: string, criteria: array<string, mixed>}>
     */
    private function sections(int $customerId): array
    {
        $edge = app(Carts::class)->getActiveCartEdgeDuration();

        return [
            [
                'key' => 'completed-orders',
                'heading' => t('Orders', category: 'commerce'),
                'criteria' => ['customerId' => $customerId, 'isCompleted' => true],
            ],
            [
                'key' => 'active-carts',
                'heading' => t('Active Carts', category: 'commerce'),
                'criteria' => ['customerId' => $customerId, 'isCompleted' => false, 'dateUpdated' => '>= ' . $edge],
            ],
            [
                'key' => 'inactive-carts',
                'heading' => t('Inactive Carts', category: 'commerce'),
                'criteria' => ['customerId' => $customerId, 'isCompleted' => false, 'dateUpdated' => '< ' . $edge],
            ],
        ];
    }
}
