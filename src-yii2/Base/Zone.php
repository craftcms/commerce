<?php

namespace craft\commerce\base;

use Craft;
use craft\base\conditions\ConditionInterface;
use craft\base\Model as BaseModel;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\elements\Address;
use craft\helpers\Json;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * @property string $cpEditUrl
 * @property ConditionInterface|string $condition
 */
abstract class Zone extends BaseModel implements ZoneInterface, HasStoreInterface
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
    private ?ZoneAddressCondition $_condition = null;

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
     * @throws InvalidConfigException
     */
    public function setCondition(ZoneAddressCondition|string|array|null $condition): void
    {
        if ($condition === null) {
            $condition = new ZoneAddressCondition(Address::class);
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ZoneAddressCondition) {
            $condition['class'] = ZoneAddressCondition::class;

            // @TODO remove at next breaking change. Fix for misconfiguration during 3.x -> 4.x migration
            $condition['elementType'] = Address::class;

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
            [['name', 'condition', 'storeId'], 'required'],
            [['storeId', 'id', 'description', 'dateCreated', 'dateUpdated'], 'safe'],
        ];
    }
}
