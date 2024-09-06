<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\Purchasable;
use craft\commerce\base\StoreTrait;
use craft\commerce\elements\conditions\customers\CatalogPricingRuleCustomerCondition;
use craft\commerce\elements\conditions\products\CatalogPricingRuleProductCondition;
use craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCondition;
use craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use craft\commerce\records\CatalogPricingRule as PricingCatalogRuleRecord;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\User;
use craft\helpers\Json;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Catalog Pricing Rule model.
 *
 * @property string|false $cpEditUrl
 * @property string $applyAmountAsFlat
 * @property string $applyAmountAsPercent
 * @property string|array|ElementConditionInterface $customerCondition
 * @property string|array|ElementConditionInterface $purchasableCondition
 * @property string|array|ElementConditionInterface $productCondition
 * @property string|array|ElementConditionInterface $variantCondition
 * @property array $purchasableIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRule extends Model implements HasStoreInterface
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
     * @var DateTime|null Date From
     */
    public ?DateTime $dateFrom = null;

    /**
     * @var DateTime|null Date To
     */
    public ?DateTime $dateTo = null;

    /**
     * @var string How the sale should be applied
     */
    public string $apply = PricingCatalogRuleRecord::APPLY_BY_PERCENT;

    /**
     * @var float|null The amount field used by the apply option
     */
    public ?float $applyAmount = null;

    /**
     * @var string
     */
    public string $applyPriceType = PricingCatalogRuleRecord::APPLY_PRICE_TYPE_PRICE;

    /**
     * @var ElementConditionInterface|null
     * @see getCustomerCondition()
     * @see setCustomerCondition()
     */
    public null|ElementConditionInterface $_customerCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @see getProductCondition()
     * @see setProductCondition()
     */
    public null|ElementConditionInterface $_productCondition = null;
    /**
     * @var ElementConditionInterface|null
     * @see getVariantCondition()
     * @see setVariantCondition()
     */
    public null|ElementConditionInterface $_variantCondition = null;

    /**
     * @var ElementConditionInterface|null
     * @see getPurchasableCondition()
     * @see setPurchasableCondition()
     */
    public null|ElementConditionInterface $_purchasableCondition = null;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var bool
     */
    public bool $isPromotionalPrice = false;

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
     * @var int[]|null Product Ids
     */
    private ?array $_purchasableIds = null;

    /**
     * @var int[]|null
     */
    private ?array $_userIds = null;

    /**
     * @var array
     * @TODO remove at next major version
     */
    private array $_metadata = [];

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['apply'], 'in', 'range' => ['toPercent', 'toFlat', 'byPercent', 'byFlat']],
            [['enabled'], 'boolean'],
            [['name', 'apply'], 'required'],
            [[
                'applyAmount',
                'applyPriceType',
                'customerCondition',
                'dateFrom',
                'dateTo',
                'id',
                'isPromotionalPrice',
                'metadata',
                'productCondition',
                'purchasableCondition',
                'storeId',
                'variantCondition',
            ], 'safe'],
        ];
    }

    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('pricing-rules/' . $this->id);
    }

    /**
     * @return array
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'purchasableIds';

        return $fields;
    }

    /**
     * @return string
     */
    public function getApplyAmountAsPercent(): string
    {
        return Craft::$app->getFormatter()->asPercent(-($this->applyAmount ?? 0.0));
    }

    /**
     * @return string
     */
    public function getApplyAmountAsFlat(): string
    {
        return $this->applyAmount !== null ? (string)($this->applyAmount * -1) : '0';
    }

    /**
     * @param string|array $metadata
     * @return void
     */
    public function setMetadata(string|array $metadata): void
    {
        $metadata = Json::decodeIfJson($metadata);

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $this->_metadata = $metadata;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->_metadata;
    }

    /**
     * @return int[]|null
     */
    public function getPurchasableIds(): ?array
    {
        if ($this->_purchasableIds === null) {
            $productVariantIds = null;

            if (!empty($this->getProductCondition()->getConditionRules())) {
                $productQuery = Product::find();
                /** @var CatalogPricingRuleProductCondition $productCondition */
                $productCondition = $this->getProductCondition();
                $productCondition->modifyQuery($productQuery);

                $productVariantIds = [];
                if ($productIds = $productQuery->ids()) {
                    $productVariantIds = Variant::find()->productId($productIds)->ids();
                }
            }

            // If there are product condition rules and they have returned no variant IDs that means there are no products that matched
            // We can skip out early as the rest of the conditions will not be met
            if ($productVariantIds === []) {
                $this->_purchasableIds = [];
                return $this->_purchasableIds;
            }

            $this->_purchasableIds = $productVariantIds;

            $variantIds = $productVariantIds;
            if (!empty($this->getVariantCondition()->getConditionRules())) {
                $variantQuery = Variant::find();
                /** @var CatalogPricingRuleVariantCondition $variantCondition */
                $variantCondition = $this->getVariantCondition();
                $variantCondition->modifyQuery($variantQuery);

                // If there are product condition rules we need to ensure the variant is in the list of product variants
                if ($productVariantIds !== null) {
                    $variantQuery->andWhere(['commerce_variants.id' => $productVariantIds]);
                }

                $variantIds = $variantQuery->ids();
            }

            // If there are variant condition rules and they have returned no variant IDs that means there are no variants that matched
            // We can skip out early as the rest of the conditions will not be met
            if ($variantIds === []) {
                $this->_purchasableIds = [];
                return $this->_purchasableIds;
            }

            $this->_purchasableIds = $variantIds;

            if (!empty($this->getPurchasableCondition()->getConditionRules())) {
                $purchasableQuery = Purchasable::find();
                /** @var CatalogPricingRulePurchasableCondition $purchasableCondition */
                $purchasableCondition = $this->getPurchasableCondition();
                $purchasableCondition->modifyQuery($purchasableQuery);

                // If there are product/variant condition rules we need to ensure the purchasable is in the list of product variants
                if ($variantIds !== null) {
                    $purchasableQuery->andWhere(['id' => $variantIds]);
                }

                $this->_purchasableIds = $purchasableQuery->ids();
            }
        }

        return $this->_purchasableIds;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getCustomerCondition(): ElementConditionInterface
    {
        $condition = $this->_customerCondition ?? new CatalogPricingRuleCustomerCondition();
        $condition->mainTag = 'div';
        $condition->name = 'customerCondition';

        return $condition;
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setCustomerCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRuleCustomerCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var CatalogPricingRuleCustomerCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_customerCondition = $condition;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getPurchasableCondition(): ElementConditionInterface
    {
        $condition = $this->_purchasableCondition ?? new CatalogPricingRulePurchasableCondition();
        $condition->mainTag = 'div';
        $condition->name = 'purchasableCondition';

        return $condition;
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setPurchasableCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRulePurchasableCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var CatalogPricingRulePurchasableCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_purchasableCondition = $condition;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getProductCondition(): ElementConditionInterface
    {
        $condition = $this->_productCondition ?? new CatalogPricingRuleProductCondition();
        $condition->mainTag = 'div';
        $condition->name = 'productCondition';
        $condition->elementType = Product::class;

        return $condition;
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setProductCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRuleProductCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var CatalogPricingRuleProductCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_productCondition = $condition;
    }

    /**
     * @return ElementConditionInterface
     */
    public function getVariantCondition(): ElementConditionInterface
    {
        $condition = $this->_variantCondition ?? new CatalogPricingRuleVariantCondition();
        $condition->mainTag = 'div';
        $condition->name = 'variantCondition';
        $condition->elementType = Variant::class;

        return $condition;
    }

    /**
     * @param ElementConditionInterface|string|array $condition
     * @return void
     * @throws InvalidConfigException
     */
    public function setVariantCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRuleVariantCondition::class;
            $condition = Craft::$app->getConditions()->createCondition($condition);
            /** @var CatalogPricingRuleVariantCondition $condition */
        }
        $condition->forProjectConfig = false;

        $this->_variantCondition = $condition;
    }

    /**
     * @return int[]|null
     */
    public function getUserIds(): ?array
    {
        if ($this->_userIds === null && !empty($this->getCustomerCondition()->getConditionRules())) {
            $userQuery = User::find();
            $this->getCustomerCondition()->modifyQuery($userQuery);
            $this->_userIds = $userQuery->ids();
        }

        return $this->_userIds;
    }

    /**
     * @param float $price
     * @return float
     */
    public function getRulePriceFromPrice(float $price): float
    {
        $price = match ($this->apply) {
            PricingCatalogRuleRecord::APPLY_BY_PERCENT => $price * (1 + $this->applyAmount),
            PricingCatalogRuleRecord::APPLY_BY_FLAT => $price + $this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_PERCENT => $price * -$this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_FLAT => -$this->applyAmount,
            default => $price,
        };

        $price = (float)Plugin::getInstance()->getCurrencies()->getTeller($this->getStore()->getCurrency())->convertToString($price);

        return max($price, 0);
    }
}
