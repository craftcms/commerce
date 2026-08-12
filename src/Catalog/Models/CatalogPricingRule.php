<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Models;

use craft\commerce\base\Purchasable;
use craft\commerce\elements\conditions\customers\CatalogPricingRuleCustomerCondition;
use craft\commerce\elements\conditions\products\CatalogPricingRuleProductCondition;
use craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCondition;
use craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use craft\commerce\Plugin;
use craft\commerce\records\CatalogPricingRule as PricingCatalogRuleRecord;
use craft\elements\db\ElementQuery;
use CraftCms\Cms\User\Elements\User;
use craft\events\CancelableEvent;
use craft\helpers\Db;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use DateTime;
use Illuminate\Validation\Rule;

class CatalogPricingRule extends Component implements HasStoreInterface
{
    use StoreTrait;

    public ?int $id = null;

    public ?string $name = null;

    public ?string $description = null;

    public ?DateTime $dateFrom = null;

    public ?DateTime $dateTo = null;

    public string $apply = PricingCatalogRuleRecord::APPLY_BY_PERCENT;

    public ?float $applyAmount = null;

    public string $applyPriceType = PricingCatalogRuleRecord::APPLY_PRICE_TYPE_PRICE;

    public null|ElementConditionInterface $_customerCondition = null;

    public null|ElementConditionInterface $_productCondition = null;

    public null|ElementConditionInterface $_variantCondition = null;

    public null|ElementConditionInterface $_purchasableCondition = null;

    public bool $enabled = true;

    public bool $isPromotionalPrice = false;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    private ?array $_purchasableIds = null;

    private ?array $_userIds = null;

    /**
     * @var array
     * @todo Remove the unused $_metadata property in Commerce 6.0
     */
    private array $_metadata = [];

    #[\Override]
    public function getRules(): array
    {
        return [
            'apply' => ['required', Rule::in([
                PricingCatalogRuleRecord::APPLY_TO_PERCENT,
                PricingCatalogRuleRecord::APPLY_TO_FLAT,
                PricingCatalogRuleRecord::APPLY_BY_PERCENT,
                PricingCatalogRuleRecord::APPLY_BY_FLAT,
            ])],
            'enabled' => ['boolean'],
            'name' => ['required', 'string'],
        ];
    }

    public function getCpEditUrl(): string
    {
        return $this->getStore()->getStoreSettingsUrl('pricing-rules/' . $this->id);
    }

    public function getApplyAmountAsPercent(): string
    {
        return I18N::getFormatter()->asPercent(-($this->applyAmount ?? 0.0));
    }

    public function getApplyAmountAsFlat(): string
    {
        return $this->applyAmount !== null ? (string)($this->applyAmount * -1) : '0';
    }

    public function setMetadata(string|array $metadata): void
    {
        $metadata = Json::decodeIfJson($metadata);

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $this->_metadata = $metadata;
    }

    public function getMetadata(): array
    {
        return $this->_metadata;
    }

    public function getPurchasableIds(): ?array
    {
        if ($this->_purchasableIds === null) {
            $siteIds = $this->getStore()->getSites()->map(fn($site) => $site->id)->all();
            $productVariantIds = null;

            // TODO: migrate condition classes once in src/
            if (!empty($this->getProductCondition()->getConditionRules())) {
                $productQuery = Product::find();
                $productQuery->siteId($siteIds);
                $productCondition = $this->getProductCondition();
                /** @phpstan-ignore-next-line */
                $productCondition->modifyQuery($productQuery);

                $productVariantIds = [];
                if ($productIds = $productQuery->ids()) {
                    $productVariantIdsQuery = Variant::find()
                        ->siteId($siteIds)
                        ->productId($productIds);

                    if ($this->isPromotionalPrice) {
                        $productVariantIdsQuery->andWhere(Db::parseBooleanParam('purchasables_stores.promotable', true));
                    }

                    $productVariantIds = $productVariantIdsQuery->ids();
                }
            }

            if ($productVariantIds === []) {
                $this->_purchasableIds = [];
                return $this->_purchasableIds;
            }

            $this->_purchasableIds = $productVariantIds;

            $variantIds = $productVariantIds;
            if (!empty($this->getVariantCondition()->getConditionRules())) {
                $variantQuery = Variant::find();
                $variantQuery->siteId($siteIds);
                $variantCondition = $this->getVariantCondition();
                /** @phpstan-ignore-next-line */
                $variantCondition->modifyQuery($variantQuery);

                if ($this->isPromotionalPrice) {
                    $variantQuery->andWhere(Db::parseBooleanParam('purchasables_stores.promotable', true));
                }

                if ($productVariantIds !== null) {
                    $variantQuery->andWhere(['commerce_variants.id' => $productVariantIds]);
                }

                $variantIds = $variantQuery->ids();
            }

            if ($variantIds === []) {
                $this->_purchasableIds = [];
                return $this->_purchasableIds;
            }

            $this->_purchasableIds = $variantIds;

            if (!empty($this->getPurchasableCondition()->getConditionRules())) {
                /** @phpstan-ignore-next-line */
                $purchasableQuery = Purchasable::find();
                $purchasableCondition = $this->getPurchasableCondition();
                /** @phpstan-ignore-next-line */
                $purchasableCondition->modifyQuery($purchasableQuery);

                if ($variantIds !== null) {
                    /** @phpstan-ignore-next-line */
                    $purchasableQuery->andWhere(['id' => $variantIds]);
                }

                if ($this->isPromotionalPrice) {
                    /** @phpstan-ignore-next-line */
                    $purchasableQuery->andWhere(Db::parseBooleanParam('purchasables_stores.promotable', true));
                }

                /** @phpstan-ignore-next-line */
                $purchasableQuery->on(ElementQuery::EVENT_AFTER_PREPARE, $this->afterPreparePurchasableQuery(...), ['siteIds' => $siteIds]);
                /** @phpstan-ignore-next-line */
                $this->_purchasableIds = $purchasableQuery->ids();
                /** @phpstan-ignore-next-line */
                $purchasableQuery->off(ElementQuery::EVENT_AFTER_PREPARE, $this->afterPreparePurchasableQuery(...));
            }

            $this->_purchasableIds = $this->_purchasableIds !== null ? array_unique($this->_purchasableIds) : null;
        }

        return $this->_purchasableIds;
    }

    public function afterPreparePurchasableQuery(CancelableEvent $event): void
    {
        /** @phpstan-ignore-next-line */
        foreach ($event->sender->subQuery->where as &$value) {
            if (is_array($value) && isset($value['elements_sites.siteId'])) {
                /** @phpstan-ignore-next-line */
                $value['elements_sites.siteId'] = $event->data['siteIds'];
            }
        }

        /** @phpstan-ignore-next-line */
        $event->sender->subQuery->join[] = ['LEFT JOIN', ['sitestores' => Table::SITESTORES], '[[elements_sites.siteId]] = [[sitestores.siteId]]'];
        /** @phpstan-ignore-next-line */
        $event->sender->subQuery->join[] = ['LEFT JOIN', ['purchasables_stores' => Table::PURCHASABLES_STORES], '[[purchasables_stores.storeId]] = [[sitestores.storeId]] AND [[purchasables_stores.purchasableId]] = [[elements.id]]'];
    }

    public function getCustomerCondition(): ElementConditionInterface
    {
        $condition = $this->_customerCondition ?? new CatalogPricingRuleCustomerCondition();
        $condition->mainTag = 'div';
        $condition->name = 'customerCondition';

        return $condition;
    }

    public function setCustomerCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRuleCustomerCondition::class;
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;
        /** @phpstan-ignore-next-line */
        $this->_customerCondition = $condition;
    }

    public function getPurchasableCondition(): ElementConditionInterface
    {
        $condition = $this->_purchasableCondition ?? new CatalogPricingRulePurchasableCondition();
        $condition->mainTag = 'div';
        $condition->name = 'purchasableCondition';

        return $condition;
    }

    public function setPurchasableCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRulePurchasableCondition::class;
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;
        /** @phpstan-ignore-next-line */
        $this->_purchasableCondition = $condition;
    }

    public function getProductCondition(): ElementConditionInterface
    {
        $condition = $this->_productCondition ?? new CatalogPricingRuleProductCondition();
        $condition->mainTag = 'div';
        $condition->name = 'productCondition';
        $condition->elementType = Product::class;

        return $condition;
    }

    public function setProductCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRuleProductCondition::class;
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;
        /** @phpstan-ignore-next-line */
        $this->_productCondition = $condition;
    }

    public function getVariantCondition(): ElementConditionInterface
    {
        $condition = $this->_variantCondition ?? new CatalogPricingRuleVariantCondition();
        $condition->mainTag = 'div';
        $condition->name = 'variantCondition';
        $condition->elementType = Variant::class;

        return $condition;
    }

    public function setVariantCondition(ElementConditionInterface|string|array $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ElementConditionInterface) {
            $condition['class'] = CatalogPricingRuleVariantCondition::class;
            $condition = Conditions::createCondition($condition);
        }
        $condition->forProjectConfig = false;
        /** @phpstan-ignore-next-line */
        $this->_variantCondition = $condition;
    }

    public function getUserIds(): ?array
    {
        if ($this->_userIds === null && !empty($this->getCustomerCondition()->getConditionRules())) {
            $userQuery = User::find();
            /** @phpstan-ignore-next-line */
            $this->getCustomerCondition()->modifyQuery($userQuery);
            $this->_userIds = $userQuery->ids();
        }

        return $this->_userIds;
    }

    public function getRulePriceFromPrice(float $price): float
    {
        $price = match ($this->apply) {
            PricingCatalogRuleRecord::APPLY_BY_PERCENT => $price * (1 + $this->applyAmount),
            PricingCatalogRuleRecord::APPLY_BY_FLAT => $price + $this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_PERCENT => $price * -$this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_FLAT => -$this->applyAmount,
            default => $price,
        };

        // TODO: migrate to app(Currencies::class) once service migrated to src/
        /** @phpstan-ignore-next-line */
        $price = (float)Plugin::getInstance()->getCurrencies()->getTeller($this->getStore()->getCurrency())->convertToString($price);

        return max($price, 0);
    }
}
