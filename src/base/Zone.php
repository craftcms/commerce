<?php

namespace src\base;


use Craft;
use craft\base\conditions\ConditionInterface;
use craft\base\Model as BaseModel;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\elements\conditions\addresses\AddressCondition;
use craft\validators\UniqueValidator;
use DateTime;

/**
 * @property string $cpEditUrl
 * @property ConditionInterface $conditionBuilder
 * @property bool $isCountryBased
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
     * @var bool Default
     */
    public bool $default = false;

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
     * @var ?ConditionInterface
     */
    private ?ConditionInterface $_conditionBuilder;

    abstract public function getCpEditUrl(): string;

    /**
     * @return ConditionInterface
     */
    public function getConditionBuilder(): ConditionInterface
    {
        return $this->_conditionBuilder ?? new AddressCondition();
    }

    /**
     * @param ConditionInterface|string $conditionBuilder
     * @return void
     */
    public function setConditionBuilder(ConditionInterface|string $conditionBuilder): void
    {
        if(!$conditionBuilder instanceof ConditionInterface){
            Craft::$app->getConditions()->createCondition($conditionBuilder);
        }

        $this->_conditionBuilder = $conditionBuilder;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name'], 'required'],
            [['conditionBuilder'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => TaxZoneRecord::class, 'targetAttribute' => ['name']],
        ];
    }
}