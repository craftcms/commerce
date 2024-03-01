<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\ShippingMethod as BaseShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\validators\UniqueValidator;
use Illuminate\Support\Collection;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Shipping method model.
 *
 * @property string $cpEditUrl the control panel URL to manage this method and its rules
 * @property bool $isEnabled whether the shipping method is enabled for listing and selection by customers
 * @property array|ShippingRule[] $shippingRules rules that meet the `ShippingRules` interface
 * @property string $type the type of Shipping Method
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethod extends BaseShippingMethod
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::class,
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'name' => AttributeTypecastBehavior::TYPE_STRING,
                'handle' => AttributeTypecastBehavior::TYPE_STRING,
                'enabled' => AttributeTypecastBehavior::TYPE_BOOLEAN,
            ],
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return Craft::t('commerce', 'Custom');
    }

    /**
     * @inheritdoc
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return (string)$this->name;
    }

    /**
     * @inheritdoc
     */
    public function getHandle(): string
    {
        return (string)$this->handle;
    }

    /**
     * @inheritdoc
     */
    public function getShippingRules(): Collection
    {
        if ($this->id === null) {
            return collect();
        }

        return Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($this->id);
    }

    /**
     * @inheritdoc
     */
    public function getIsEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('shippingmethods/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name'], UniqueValidator::class, 'targetClass' => ShippingMethodRecord::class];
        $rules[] = [['handle'], UniqueValidator::class,
            'targetClass' => ShippingMethodRecord::class,
            'targetAttribute' => ['handle', 'storeId'],
            'message' => '{attribute} "{value}" has already been taken.',
        ];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'shippingRules';

        return $fields;
    }
}
