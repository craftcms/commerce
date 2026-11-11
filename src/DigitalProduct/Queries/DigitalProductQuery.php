<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct\Queries;

use Closure;
use CraftCms\Cms\Element\Queries\Exceptions\QueryAbortedException;
use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\Purchasable\Queries\PurchasableQuery;
use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Override;
use Tpetry\QueryExpressions\Language\Alias;

use function CraftCms\Cms\currentUser;

/**
 * @extends PurchasableQuery<DigitalProduct>
 */
class DigitalProductQuery extends PurchasableQuery
{
    /** @var array<string, int> */
    #[Override]
    protected array $defaultOrderBy = [
        'commerce_digitalproducts.postDate' => SORT_DESC,
        'elements.id' => SORT_DESC,
    ];

    public mixed $typeId = null;

    public mixed $postDate = null;

    public mixed $expiryDate = null;

    /**
     * Whether to only return digital products the current user can manage.
     */
    public ?bool $editable = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        if (!isset($config['status'])) {
            $config['status'] = [DigitalProduct::STATUS_LIVE];
        }

        parent::__construct(DigitalProduct::class, $config);

        $this->query->join(new Alias(Table::DIGITALPRODUCTS, 'commerce_digitalproducts'), 'commerce_digitalproducts.id', '=', 'elements.id');

        $this->query->addSelect([
            'commerce_digitalproducts.typeId',
            'commerce_digitalproducts.postDate',
            'commerce_digitalproducts.expiryDate',
        ]);

        $this->beforeQuery(static function(self $query) {
            $query->normalizeTypeId();

            if ($query->typeId === []) {
                throw new QueryAbortedException();
            }

            if ($query->typeId) {
                $query->whereIn('commerce_digitalproducts.typeId', $query->typeId);
            }

            if (isset($query->postDate)) {
                $query->whereDateParam('commerce_digitalproducts.postDate', $query->postDate);
            }

            if (isset($query->expiryDate)) {
                $query->whereDateParam('commerce_digitalproducts.expiryDate', $query->expiryDate);
            }

            if ($query->editable) {
                if (!currentUser()) {
                    throw new QueryAbortedException();
                }

                $query->whereIn('commerce_digitalproducts.typeId', app(DigitalProductTypes::class)->getEditableDigitalProductTypeIds());
            }
        });
    }

    /**
     * Narrows the query results based on the digital products’ types, per the types’ handles.
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

    public function postDate(mixed $value): static
    {
        $this->postDate = $value;
        return $this;
    }

    public function expiryDate(mixed $value): static
    {
        $this->expiryDate = $value;
        return $this;
    }

    /**
     * Narrows the query results to digital products posted before a date.
     */
    public function before(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = [...Arr::wrap($this->postDate), '<' . $value];

        return $this;
    }

    /**
     * Narrows the query results to digital products posted on or after a date.
     */
    public function after(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = [...Arr::wrap($this->postDate), '>=' . $value];

        return $this;
    }

    public function editable(?bool $value = true): static
    {
        $this->editable = $value;
        return $this;
    }

    #[Override]
    protected function statusCondition(string $status): Closure
    {
        $currentTime = now()->endOfMinute()->setTimezone('UTC');

        return match ($status) {
            DigitalProduct::STATUS_LIVE => fn(Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('commerce_digitalproducts.postDate', '<=', $currentTime)
                ->where(fn(Builder $query) => $query
                    ->whereNull('commerce_digitalproducts.expiryDate')
                    ->orWhere('commerce_digitalproducts.expiryDate', '>', $currentTime)),
            DigitalProduct::STATUS_PENDING => fn(Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->where('commerce_digitalproducts.postDate', '>', $currentTime),
            DigitalProduct::STATUS_EXPIRED => fn(Builder $query) => $query
                ->whereBool('elements.enabled', true)
                ->whereBool('elements_sites.enabled', true)
                ->whereNotNull('commerce_digitalproducts.expiryDate')
                ->where('commerce_digitalproducts.expiryDate', '<=', $currentTime),
            default => parent::statusCondition($status),
        };
    }

    private function normalizeTypeId(): void
    {
        if (empty($this->typeId)) {
            $this->typeId = is_array($this->typeId) ? [] : null;
        } elseif (is_numeric($this->typeId)) {
            $this->typeId = [(int)$this->typeId];
        } elseif (!is_array($this->typeId) || !Arr::isNumeric($this->typeId)) {
            $this->typeId = DB::table(Table::DIGITALPRODUCTTYPES)
                ->whereParam('id', $this->typeId)
                ->pluck('id')
                ->all();
        }
    }
}
