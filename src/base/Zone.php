<?php

namespace craft\commerce\base;


use Craft;
use craft\base\conditions\ConditionInterface;
use craft\base\Model as BaseModel;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\elements\conditions\addresses\AddressCondition;
use craft\elements\conditions\ElementConditionInterface;
use craft\helpers\Json;
use craft\validators\UniqueValidator;
use DateTime;

/**
 * @property string $cpEditUrl
 * @property ConditionInterface|string $condition
 */
abstract class Zone extends BaseModel
{
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
     * @var ?DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var ?ElementConditionInterface
     */
    private ?ElementConditionInterface $_condition;

    abstract public function getCpEditUrl(): string;

    /**
     * @return ElementConditionInterface
     */
    public function getCondition(): ElementConditionInterface
    {
        return $this->_condition ?? new AddressCondition();
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     */
    public function setCondition(ElementConditionInterface|string|array $condition): void
    {
        if(is_string($condition)){
            $condition = Json::decodeIfJson($condition);
        }

        if(!$condition instanceof ElementConditionInterface){
            $condition['class'] = AddressCondition::class;
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
        ];
    }
}