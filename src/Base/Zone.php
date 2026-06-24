<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Base;

use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;

abstract class Zone extends Component implements HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $description = null;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    private ?ZoneAddressCondition $_condition = null;

    abstract public function getCpEditUrl(): string;

    public function getCondition(): ZoneAddressCondition
    {
        /** @phpstan-ignore-next-line */
        return $this->_condition ?? new ZoneAddressCondition(Address::class);
    }

    public function setCondition(ZoneAddressCondition|string|array|null $condition): void
    {
        if ($condition === null) {
            /** @phpstan-ignore-next-line */
            $condition = new ZoneAddressCondition(Address::class);
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ZoneAddressCondition) {
            $condition['class'] = ZoneAddressCondition::class;
            $condition['elementType'] = Address::class;

            /** @var ZoneAddressCondition $condition */
            $condition = Conditions::createCondition($condition);
        }

        /** @phpstan-ignore-next-line */
        $condition->forProjectConfig = false;

        $this->_condition = $condition;
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'condition' => ['required'],
            'storeId' => ['required', 'integer'],
        ];
    }
}
