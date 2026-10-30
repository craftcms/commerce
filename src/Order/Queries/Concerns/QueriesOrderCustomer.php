<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Queries\Concerns;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Queries\OrderQuery;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * Order customer scopes — see {@see QueriesOrderIdentity} for the trait convention this follows.
 *
 * @internal
 */
trait QueriesOrderCustomer
{
    public mixed $customerId = null;

    public mixed $email = null;

    protected function initQueriesOrderCustomer(): void
    {
        $this->beforeQuery(static function(OrderQuery $orderQuery) {
            static::applyEmail($orderQuery, $orderQuery->email);
            static::applyCustomerId($orderQuery, $orderQuery->customerId);
        });
    }

    public static function applyEmail(BuilderContract $query, mixed $value): void
    {
        if (!isset($value) || !$value) {
            return;
        }

        // Join and search the users table for email address
        $query->leftJoin(new Alias(CraftTable::USERS, 'users'), 'users.id', '=', 'commerce_orders.customerId');
        $query->whereParam('users.email', $value, caseInsensitive: true);
    }

    public static function applyCustomerId(BuilderContract $query, mixed $value): void
    {
        if (!isset($value)) {
            return;
        }

        $query->whereParam('commerce_orders.customerId', $value);
    }

    public function email(mixed $value): static
    {
        $this->email = $value;
        return $this;
    }

    public function customer(int|User|null $value): static
    {
        $this->customerId = $value instanceof User ? $value->id : $value;
        return $this;
    }

    public function customerId(mixed $value): static
    {
        $this->customerId = $value;
        return $this;
    }
}
