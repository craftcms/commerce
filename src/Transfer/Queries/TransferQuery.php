<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Queries;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use Override;

/**
 * @extends ElementQuery<Transfer>
 */
class TransferQuery extends ElementQuery
{
    #[Override]
    protected string $table = Table::TRANSFERS;

    /** @var array<string, int> */
    #[Override]
    protected array $defaultOrderBy = [
        'elements.dateCreated' => SORT_DESC,
        'elements.id' => SORT_DESC,
    ];

    public mixed $transferStatus = null;

    public mixed $originLocation = null;

    public mixed $destinationLocation = null;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct(Transfer::class, $config);

        $this->query->addSelect([
            'commerce_transfers.transferStatus',
            'commerce_transfers.originLocationId',
            'commerce_transfers.destinationLocationId',
        ]);

        $this->beforeQuery(function(self $query) {
            if ($query->transferStatus) {
                $query->whereParam('commerce_transfers.transferStatus', $query->transferStatus);
            }

            if ($query->originLocation) {
                $query->whereParam('commerce_transfers.originLocationId', $query->originLocation);
            }

            if ($query->destinationLocation) {
                $query->whereParam('commerce_transfers.destinationLocationId', $query->destinationLocation);
            }
        });
    }

    /**
     * Narrows the query results based on the transfers' statuses.
     */
    public function transferStatus(mixed $value): static
    {
        if ($value instanceof TransferStatusType) {
            $value = $value->value;
        }

        $this->transferStatus = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the transfers' origin inventory location.
     */
    public function originLocation(mixed $value): static
    {
        if ($value instanceof InventoryLocation) {
            $value = $value->id;
        }

        $this->originLocation = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the transfers' destination inventory location.
     */
    public function destinationLocation(mixed $value): static
    {
        if ($value instanceof InventoryLocation) {
            $value = $value->id;
        }

        $this->destinationLocation = $value;
        return $this;
    }

    /** @param array<string, mixed> $row */
    #[Override]
    public function createElement(array $row): ElementInterface
    {
        if (isset($row['transferStatus']) && is_string($row['transferStatus'])) {
            $row['transferStatus'] = TransferStatusType::from($row['transferStatus']);
        }

        return parent::createElement($row);
    }
}
