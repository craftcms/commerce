<?php

namespace craft\commerce\base;

use Craft;
use craft\base\conditions\ConditionInterface;
use craft\base\Model as BaseModel;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\elements\Address;
use craft\helpers\Json;
use craft\validators\UniqueValidator;
use DateTime;

/**
 * @property string $cpEditUrl
 * @property ConditionInterface|string $condition
 */
abstract class Zone extends BaseModel implements ZoneInterface
{
    use StoreTrait;

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Description
     */
    public ?string $description = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var ?ZoneAddressCondition
     */
    private ?ZoneAddressCondition $_condition;

    abstract public function getCpEditUrl(): string;

    /**
     * @return ZoneAddressCondition
     */
    public function getCondition(): ZoneAddressCondition
    {
        return $this->_condition ?? new ZoneAddressCondition(Address::class);
    }

    /**
     * @param ZoneAddressCondition|string|array|null $condition
     * @return void
     */
    public function setCondition(ZoneAddressCondition|string|array|null $condition): void
    {
        if ($condition === null) {
            $condition = new ZoneAddressCondition();
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ZoneAddressCondition) {
            $condition['class'] = ZoneAddressCondition::class;
            /** @var ZoneAddressCondition $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_condition = $condition;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name'], 'required'],
            [['condition'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => TaxZoneRecord::class, 'targetAttribute' => ['name']],
            [['storeId', 'id', 'description', 'dateCreated', 'dateUpdated'], 'safe'],
        ];
    }
}
