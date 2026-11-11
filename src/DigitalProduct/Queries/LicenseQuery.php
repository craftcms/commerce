<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Queries;

use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\Order\Elements\Order;
use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * @extends ElementQuery<License>
 */
class LicenseQuery extends ElementQuery
{
    #[Override]
    protected string $table = Table::LICENSES;

    /** @var array<string, int> */
    #[Override]
    protected array $defaultOrderBy = [
        'elements.dateCreated' => SORT_DESC,
        'elements.id' => SORT_DESC,
    ];

    /**
     * Matches licenses by owner email or by their user's email.
     */
    public mixed $email = null;

    public mixed $ownerEmail = null;

    public mixed $userEmail = null;

    public mixed $ownerId = null;

    public mixed $productId = null;

    public mixed $typeId = null;

    public mixed $orderId = null;

    public mixed $licenseKey = null;

    public mixed $licenseDate = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct(License::class, $config);

        $this->query->leftJoin(new Alias(Table::DIGITALPRODUCTS, 'commerce_digitalproducts'), 'commerce_digitalproducts.id', '=', 'commerce_licenses.productId');
        $this->query->leftJoin(new Alias(CraftTable::USERS, 'license_users'), 'license_users.id', '=', 'commerce_licenses.userId');

        $this->query->addSelect([
            'commerce_licenses.productId',
            'commerce_licenses.orderId',
            'commerce_licenses.licenseKey',
            'commerce_licenses.ownerName',
            'commerce_licenses.ownerEmail',
            'commerce_licenses.userId',
        ]);

        $this->beforeQuery(static function(self $query) {
            if ($query->typeId === [] || $query->ownerId === [] || $query->productId === []) {
                throw new QueryAbortedException();
            }

            if ($query->email) {
                $query->where(fn(Builder $q) => $q
                    ->whereParam('commerce_licenses.ownerEmail', $query->email)
                    ->orWhereParam('license_users.email', $query->email));
            }

            foreach ([
                'ownerEmail' => 'commerce_licenses.ownerEmail',
                'userEmail' => 'license_users.email',
                'ownerId' => 'commerce_licenses.userId',
                'productId' => 'commerce_licenses.productId',
                'typeId' => 'commerce_digitalproducts.typeId',
                'orderId' => 'commerce_licenses.orderId',
                'licenseKey' => 'commerce_licenses.licenseKey',
            ] as $param => $column) {
                if ($query->$param !== null && $query->$param !== '') {
                    $query->whereParam($column, $query->$param);
                }
            }

            if ($query->licenseDate) {
                $query->whereDateParam('elements.dateCreated', $query->licenseDate);
            }
        });
    }

    public function email(mixed $value): static
    {
        $this->email = $value;
        return $this;
    }

    public function ownerEmail(mixed $value): static
    {
        $this->ownerEmail = $value;
        return $this;
    }

    public function userEmail(mixed $value): static
    {
        $this->userEmail = $value;
        return $this;
    }

    /**
     * Narrows the query results to licenses owned by a user, given the user or their username/email.
     */
    public function owner(User|string|null $value): static
    {
        if ($value instanceof User) {
            $this->ownerId = $value->id;
        } elseif ($value !== null) {
            $this->ownerId = DB::table(CraftTable::USERS)
                ->where(fn(Builder $q) => $q->where('username', $value)->orWhere('email', $value))
                ->pluck('id')
                ->all();
        } else {
            $this->ownerId = null;
        }

        return $this;
    }

    public function ownerId(mixed $value): static
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Narrows the query results to licenses for a digital product, given the product or its SKU(s).
     *
     * @param DigitalProduct|string|string[]|null $value
     */
    public function product(mixed $value): static
    {
        if ($value instanceof DigitalProduct) {
            $this->productId = $value->id;
        } elseif ($value !== null) {
            $this->productId = DB::table(Table::PURCHASABLES)
                ->whereParam('sku', $value)
                ->whereIn('id', DB::table(Table::DIGITALPRODUCTS)->select('id'))
                ->pluck('id')
                ->all();
        } else {
            $this->productId = null;
        }

        return $this;
    }

    public function productId(mixed $value): static
    {
        $this->productId = $value;
        return $this;
    }

    /**
     * Narrows the query results to licenses for digital products of the given type(s), per the types’ handles.
     *
     * @param DigitalProductType|string|string[]|null $value
     */
    public function type(mixed $value): static
    {
        if ($value instanceof DigitalProductType) {
            $this->typeId = [$value->id];
        } elseif ($value !== null) {
            $this->typeId = DB::table(Table::DIGITALPRODUCTTYPES)
                ->whereParam('handle', $value)
                ->pluck('id')
                ->all();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    public function typeId(mixed $value): static
    {
        $this->typeId = $value;
        return $this;
    }

    /**
     * @param Order|int|int[]|null $value
     */
    public function orderId(mixed $value): static
    {
        $this->orderId = $value instanceof Order ? $value->id : $value;
        return $this;
    }

    public function licenseKey(mixed $value): static
    {
        $this->licenseKey = $value;
        return $this;
    }

    public function licenseDate(mixed $value): static
    {
        $this->licenseDate = $value;
        return $this;
    }

    /**
     * Narrows the query results to licenses issued before a date.
     */
    public function before(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->licenseDate = [...Arr::wrap($this->licenseDate), '<' . $value];

        return $this;
    }

    /**
     * Narrows the query results to licenses issued on or after a date.
     */
    public function after(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->licenseDate = [...Arr::wrap($this->licenseDate), '>=' . $value];

        return $this;
    }
}
