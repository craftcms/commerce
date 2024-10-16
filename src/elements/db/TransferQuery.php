<?php

namespace craft\commerce\elements\db;

use craft\commerce\db\Table;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\models\InventoryLocation;
use craft\elements\db\ElementQuery;

/**
 * Transfer query
 */
class TransferQuery extends ElementQuery
{
    /**
     * @var mixed|null
     */
    public mixed $transferStatus = null;


    /**
     * @var mixed
     */
    public mixed $destinationLocation = null;

    /**
     * @var mixed|null
     */
    public mixed $originLocation = null;

    /**
     * @param string|TransferStatusType|null $value
     * @return static
     */
    public function transferStatus($value): self
    {
        if ($value instanceof TransferStatusType) {
            $value = $value->value;
        }

        $this->transferStatus = $value;
        return $this;
    }

    /**
     * @param string|int|InventoryLocation|null $value
     * @return static
     */
    public function originLocation($value): self
    {
        if ($value instanceof InventoryLocation) {
            $value = $value->id;
        }

        $this->originLocation = $value;
        return $this;
    }

    /**
     * @param string|int|InventoryLocation|null $value
     * @return static
     */
    public function destinationLocation($value): self
    {
        if ($value instanceof InventoryLocation) {
            $value = $value->id;
        }

        $this->destinationLocation = $value;
        return $this;
    }

    /**
     * @var bool|null Whether to only return entries that the user has permission to save.
     * @used-by savable()
     * @since 4.4.0
     */
    public ?bool $savable = null;

    protected function beforePrepare(): bool
    {
        $this->joinElementTable(Table::TRANSFERS);

        // add selects
        $this->query->select([
            Table::TRANSFERS . '.transferStatus',
            Table::TRANSFERS . '.originLocationId',
            Table::TRANSFERS . '.destinationLocationId',
        ]);

        if ($this->transferStatus) {
            $this->subQuery->andWhere(['transferStatus' => $this->transferStatus]);
        }

        if ($this->originLocation) {
            $this->subQuery->andWhere(['originLocationId' => $this->originLocation]);
        }

        if ($this->destinationLocation) {
            $this->subQuery->andWhere(['destinationLocationId' => $this->destinationLocation]);
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    public function populate($rows): array
    {
        foreach ($rows as &$row) {
            $row['transferStatus'] = TransferStatusType::from($row['transferStatus']);
        }
        return parent::populate($rows);
    }
}
