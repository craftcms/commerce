<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use yii\db\Connection;
use yii\db\Expression;

/**
 * ProductQuery represents a SELECT SQL statement for products in a way that is independent of DBMS.
 *
 * @method Product[]|array all($db = null)
 * @method Product|array|null one($db = null)
 * @method Product|array|null nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @doc-path products-variants.md
 * @prefix-doc-params
 * @replace {element} product
 * @replace {elements} products
 * @replace {twig-method} craft.products()
 * @replace {myElement} myProduct
 * @replace {element-class} \craft\commerce\elements\Product
 * @supports-site-params
 * @supports-title-param
 * @supports-slug-param
 * @supports-uri-param
 * @supports-status-param
 * @supports-structure-params
 */
class ProductQuery extends ElementQuery
{
    /**
     * @var bool Whether to only return products that the user has permission to edit.
     */
    public bool $editable = false;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public mixed $expiryDate = null;

    /**
     * @var mixed The default price the resulting products must have.
     */
    public mixed $defaultPrice = null;

    /**
     * @var mixed The default height the resulting products must have.
     */
    public mixed $defaultHeight = null;

    /**
     * @var mixed The default length the resulting products must have.
     */
    public mixed $defaultLength = null;

    /**
     * @var mixed The default width the resulting products must have.
     */
    public mixed $defaultWidth = null;

    /**
     * @var mixed The default weight the resulting products must have.
     */
    public mixed $defaultWeight = null;

    /**
     * @var mixed The default sku the resulting products must have.
     */
    public mixed $defaultSku = null;

    /**
     * @var mixed only return products that match the resulting variant query.
     */
    public mixed $hasVariant = null;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public mixed $postDate = null;

    /**
     * @var mixed The product type ID(s) that the resulting products must have.
     */
    public mixed $typeId = null;

    /**
     * @inheritdoc
     */
    protected array $defaultOrderBy = [
        'commerce_products.postDate' => SORT_DESC,
        'elements.id' => SORT_DESC,
    ];

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = 'live';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        if (!isset($this->withStructure)) {
            $this->withStructure = true;
        }

        parent::init();
    }


    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'type':
                $this->type($value);
                break;
            case 'before':
                $this->before($value);
                break;
            case 'after':
                $this->after($value);
                break;
            case 'defaultHeight':
                $this->defaultHeight($value);
                break;
            case 'defaultLength':
                $this->defaultLength($value);
                break;
            case 'defaultWidth':
                $this->defaultWidth($value);
                break;
            case 'defaultWeight':
                $this->defaultWeight($value);
                break;
            case 'defaultSku':
                $this->defaultSku($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Narrows the query results based on the products’ default variant price.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `10` | of a price of 10.
     * | `['and', '>= ' ~ 100, '<= ' ~ 2000]` | of a default variant price between 100 and 2000
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product type with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .defaultPrice(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the product type with an ID of 1
     * ${elements-var} = {php-method}
     *     ->defaultPrice(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function defaultPrice(mixed $value): static
    {
        $this->defaultPrice = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant height dimension IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a type with a dimension of 1.
     * | `'not 1'` | not a dimension of 1.
     * | `[1, 2]` | of a a dimension 1 or 2.
     * | `['and', '>= ' ~ 100, '<= ' ~ 2000]` | of a dimension between 100 and 2000
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product default dimension of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .defaultHeight(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the product default dimension of 1
     * ${elements-var} = {php-method}
     *     ->defaultHeight(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function defaultHeight(mixed $value): static
    {
        $this->defaultHeight = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant length dimension IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a type with a dimension of 1.
     * | `'not 1'` | not a dimension of 1.
     * | `[1, 2]` | of a a dimension 1 or 2.
     * | `['and', '>= ' ~ 100, '<= ' ~ 2000]` | of a dimension between 100 and 2000
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product default dimension of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .defaultLength(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the  product default dimension of 1
     * ${elements-var} = {php-method}
     *     ->defaultLength(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function defaultLength(mixed $value): static
    {
        $this->defaultLength = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant width dimension IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a type with a dimension of 1.
     * | `'not 1'` | not a dimension of 1.
     * | `[1, 2]` | of a a dimension 1 or 2.
     * | `['and', '>= ' ~ 100, '<= ' ~ 2000]` | of a dimension between 100 and 2000
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product default dimension of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .defaultWidth(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the  product default dimension of 1
     * ${elements-var} = {php-method}
     *     ->defaultWidth(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function defaultWidth(mixed $value): static
    {
        $this->defaultWidth = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the products’ default variant weight dimension IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a type with a dimension of 1.
     * | `'not 1'` | not a dimension of 1.
     * | `[1, 2]` | of a a dimension 1 or 2.
     * | `['and', '>= ' ~ 100, '<= ' ~ 2000]` | of a dimension between 100 and 2000
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product default dimension of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .defaultWeight(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the  product default dimension of 1
     * ${elements-var} = {php-method}
     *     ->defaultWeight(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function defaultWeight(mixed $value): static
    {
        $this->defaultWeight = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the default productvariants defaultSku
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `xxx-001` | of products default SKU of `xxx-001`.
     * | `'not xxx-001'` | not a default SKU of `xxx-001`.
     * | `['not xxx-001', 'not xxx-002']` | of a default SKU of xxx-001 or xxx-002.
     * | `['not', `xxx-001`, `xxx-002`]` | not a product default SKU of `xxx-001` or `xxx-001`.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product default SKU of `xxx-001` #}
     * {% set {elements-var} = {twig-method}
     *   .defaultSku('xxx-001')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements}  of the product default SKU of `xxx-001`
     * ${elements-var} = {php-method}
     *     ->defaultSku('xxx-001')
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function defaultSku(mixed $value): static
    {
        $this->defaultSku = $value;

        return $this;
    }

    /**
     * Narrows the query results based on the products’ types.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'foo'` | of a type with a handle of `foo`.
     * | `'not foo'` | not of a type with a handle of `foo`.
     * | `['foo', 'bar']` | of a type with a handle of `foo` or `bar`.
     * | `['not', 'foo', 'bar']` | not of a type with a handle of `foo` or `bar`.
     * | an [[ProductType|ProductType]] object | of a type represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} with a Foo product type #}
     * {% set {elements-var} = {twig-method}
     *   .type('foo')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with a Foo product type
     * ${elements-var} = {php-method}
     *     ->type('foo')
     *     ->all();
     * ```
     *
     * @param ProductType|string|null|array<string> $value The property value
     * @return static self reference
     */
    public function type(mixed $value): static
    {
        // If the value is a product type handle, swap it with the product type
        if (is_string($value) && ($productType = Plugin::getInstance()->getProductTypes()->getProductTypeByHandle($value))) {
            $value = $productType;
        }

        if ($value instanceof ProductType) {
            $this->typeId = [$value->id];
        } elseif ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from([Table::PRODUCTTYPES])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Narrows the query results to only products that were posted before a certain date.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'2018-04-01'` | that were posted before 2018-04-01.
     * | a [[\DateTime|DateTime]] object | that were posted before the date represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} posted before this month #}
     * {% set firstDayOfMonth = date('first day of this month') %}
     *
     * {% set {elements-var} = {twig-method}
     *   .before(firstDayOfMonth)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} posted before this month
     * $firstDayOfMonth = new \DateTime('first day of this month');
     *
     * ${elements-var} = {php-method}
     *     ->before($firstDayOfMonth)
     *     ->all();
     * ```
     *
     * @param string|DateTime $value The property value
     * @return static self reference
     */
    public function before(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '<' . $value;

        return $this;
    }

    /**
     * Narrows the query results to only products that were posted on or after a certain date.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'2018-04-01'` | that were posted after 2018-04-01.
     * | a [[\DateTime|DateTime]] object | that were posted after the date represented by the object.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} posted this month #}
     * {% set firstDayOfMonth = date('first day of this month') %}
     *
     * {% set {elements-var} = {twig-method}
     *   .after(firstDayOfMonth)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} posted this month
     * $firstDayOfMonth = new \DateTime('first day of this month');
     *
     * ${elements-var} = {php-method}
     *     ->after($firstDayOfMonth)
     *     ->all();
     * ```
     *
     * @param string|DateTime $value The property value
     * @return static self reference
     */
    public function after(DateTime|string $value): static
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '>=' . $value;

        return $this;
    }

    /**
     * Sets the [[editable]] property.
     *
     * @param bool $value The property value (defaults to true)
     * @return static self reference
     */
    public function editable(bool $value = true): static
    {
        $this->editable = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ types, per the types’ IDs.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `1` | of a type with an ID of 1.
     * | `'not 1'` | not of a type with an ID of 1.
     * | `[1, 2]` | of a type with an ID of 1 or 2.
     * | `['not', 1, 2]` | not of a type with an ID of 1 or 2.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} of the product type with an ID of 1 #}
     * {% set {elements-var} = {twig-method}
     *   .typeId(1)
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} of the product type with an ID of 1
     * ${elements-var} = {php-method}
     *     ->typeId(1)
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function typeId(mixed $value): static
    {
        $this->typeId = $value;
        return $this;
    }

    /**
     * Narrows the query results to only products that have certain variants.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | a [[VariantQuery|VariantQuery]] object | with variants that match the query.
     *
     * @param VariantQuery|array $value The property value
     * @return static self reference
     * @noinspection PhpUnused
     */
    public function hasVariant(mixed $value): static
    {
        $this->hasVariant = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ post dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2018-04-01'` | that were posted on or after 2018-04-01.
     * | `'< 2018-05-01'` | that were posted before 2018-05-01
     * | `['and', '>= 2018-04-04', '< 2018-05-01']` | that were posted between 2018-04-01 and 2018-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} posted last month #}
     * {% set start = date('first day of last month')|atom %}
     * {% set end = date('first day of this month')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *   .postDate(['and', ">= #{start}", "< #{end}"])
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} posted last month
     * $start = new \DateTime('first day of next month')->format(\DateTime::ATOM);
     * $end = new \DateTime('first day of this month')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->postDate(['and', ">= {$start}", "< {$end}"])
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function postDate(mixed $value): static
    {
        $this->postDate = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the products’ expiry dates.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'>= 2020-04-01'` | that will expire on or after 2020-04-01.
     * | `'< 2020-05-01'` | that will expire before 2020-05-01
     * | `['and', '>= 2020-04-04', '< 2020-05-01']` | that will expire between 2020-04-01 and 2020-05-01.
     *
     * ---
     *
     * ```twig
     * {# Fetch {elements} expiring this month #}
     * {% set nextMonth = date('first day of next month')|atom %}
     *
     * {% set {elements-var} = {twig-method}
     *   .expiryDate("< #{nextMonth}")
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} expiring this month
     * $nextMonth = new \DateTime('first day of next month')->format(\DateTime::ATOM);
     *
     * ${elements-var} = {php-method}
     *     ->expiryDate("< {$nextMonth}")
     *     ->all();
     * ```
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function expiryDate(mixed $value): static
    {
        $this->expiryDate = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the {elements}’ statuses.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'live'` _(default)_ | that are live.
     * | `'pending'` | that are pending (enabled with a Post Date in the future).
     * | `'expired'` | that are expired (enabled with an Expiry Date in the past).
     * | `'disabled'` | that are disabled.
     * | `['live', 'pending']` | that are live or pending.
     *
     * ---
     *
     * ```twig
     * {# Fetch disabled {elements} #}
     * {% set {elements-var} = {twig-method}
     *   .status('disabled')
     *   .all() %}
     * ```
     *
     * ```php
     * // Fetch disabled {elements}
     * ${elements-var} = {element-class}::find()
     *     ->status('disabled')
     *     ->all();
     * ```
     */
    public function status(array|string|null $value): static
    {
        parent::status($value);
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function afterPrepare(): bool
    {
        // Store dependent related joins to the sub query need to be done after the `elements_sites` is joined in the base `ElementQuery` class.
        $customerId = Craft::$app->getUser()->getIdentity()?->id;

        $catalogPricesQuery = Plugin::getInstance()
            ->getCatalogPricing()
            ->createCatalogPricesQuery(userId: $customerId)
            ->addSelect(['cp.purchasableId', 'cp.storeId'])
            ->leftJoin(['purvariants' => Table::VARIANTS], '[[purvariants.id]] = [[cp.purchasableId]]')
            ->andWhere(['purvariants.isDefault' => true]);

        $this->subQuery->leftJoin(['sitestores' => Table::SITESTORES], '[[elements_sites.siteId]] = [[sitestores.siteId]]');
        $this->subQuery->leftJoin(['catalogprices' => $catalogPricesQuery], '[[catalogprices.purchasableId]] = [[commerce_products.defaultVariantId]] AND [[catalogprices.storeId]] = [[sitestores.storeId]]');

        return parent::afterPrepare();
    }

    /**
     * @inheritdoc
     * @throws QueryAbortedException
     */
    protected function beforePrepare(): bool
    {
        $this->_normalizeTypeId();

        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        $this->joinElementTable('commerce_products');

        $this->query->select([
            'commerce_products.id',
            'commerce_products.typeId',
            'commerce_products.postDate',
            'commerce_products.expiryDate',
            'subquery.price as defaultPrice',
            'commerce_products.defaultPrice as defaultBasePrice',
            'commerce_products.defaultVariantId',
            'commerce_products.defaultSku',
            'commerce_products.defaultWeight',
            'commerce_products.defaultLength',
            'commerce_products.defaultWidth',
            'commerce_products.defaultHeight',
            'sitestores.storeId',
        ]);

        // Join in sites stores to get product's store for current request
        $this->query->leftJoin(['sitestores' => Table::SITESTORES], '[[elements_sites.siteId]] = [[sitestores.siteId]]');

        $this->subQuery->addSelect(['catalogprices.price']);

        if (isset($this->postDate)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_products.postDate', $this->postDate));
        }

        if (isset($this->expiryDate)) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_products.expiryDate', $this->expiryDate));
        }

        $this->_applyProductTypeIdParam();

        if (isset($this->defaultPrice)) {
            $this->subQuery->andWhere(Db::parseParam('catalogprices.price', $this->defaultPrice));
        }

        if (isset($this->defaultHeight)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultHeight', $this->defaultHeight));
        }

        if (isset($this->defaultLength)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultLength', $this->defaultLength));
        }

        if (isset($this->defaultWidth)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultWidth', $this->defaultWidth));
        }

        if (isset($this->defaultWeight)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultWeight', $this->defaultWeight));
        }

        if (isset($this->defaultSku)) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultSku', $this->defaultSku));
        }

        $this->_applyHasVariantParam();
        $this->_applyEditableParam();
        $this->_applyRefParam();

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status): mixed
    {
        $currentTimeDb = Db::prepareDateForDb(new DateTime());

        return match ($status) {
            Product::STATUS_LIVE => [
                'and',
                [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                ],
                ['<=', 'commerce_products.postDate', $currentTimeDb],
                [
                    'or',
                    ['commerce_products.expiryDate' => null],
                    ['>', 'commerce_products.expiryDate', $currentTimeDb],
                ],
            ],
            Product::STATUS_PENDING => [
                'and',
                [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                ],
                ['>', 'commerce_products.postDate', $currentTimeDb],
            ],
            Product::STATUS_EXPIRED => [
                'and',
                [
                    'elements.enabled' => true,
                    'elements_sites.enabled' => true,
                ],
                ['not', ['commerce_products.expiryDate' => null]],
                ['<=', 'commerce_products.expiryDate', $currentTimeDb],
            ],
            default => parent::statusCondition($status),
        };
    }

    /**
     * Normalizes the typeId param to an array of IDs or null
     */
    private function _normalizeTypeId(): void
    {
        if (empty($this->typeId)) {
            $this->typeId = is_array($this->typeId) ? [] : null;
        } elseif (is_numeric($this->typeId)) {
            $this->typeId = [$this->typeId];
        } elseif (!is_array($this->typeId) || !ArrayHelper::isNumeric($this->typeId)) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from([Table::PRODUCTTYPES])
                ->where(Db::parseParam('id', $this->typeId))
                ->column();
        }
    }

    /**
     * Applies the 'editable' param to the query being prepared.
     *
     * @throws QueryAbortedException
     */
    private function _applyEditableParam(): void
    {
        if (!$this->editable) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new QueryAbortedException('Could not execute query for product when no user found');
        }

        // Limit the query to only the sections the user has permission to edit
        $this->subQuery->andWhere([
            'commerce_products.typeId' => Plugin::getInstance()->getProductTypes()->getEditableProductTypeIds(),
        ]);
    }

    /**
     * Applies the 'productTypeId' param to the query being prepared.
     */
    private function _applyProductTypeIdParam(): void
    {
        if ($this->typeId) {
            $this->subQuery->andWhere(['commerce_products.typeId' => $this->typeId]);

            // Should we set the structureId param?
            if (
                $this->withStructure !== false &&
                !isset($this->structureId) &&
                count($this->typeId) === 1
            ) {
                $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById(reset($this->typeId));
                if ($productType && $productType->type === ProductType::TYPE_ORDERABLE) {
                    $this->structureId = $productType->structureId;
                } else {
                    $this->withStructure = false;
                }
            }
        }
    }

    /**
     * Applies the hasVariant query condition
     */
    private function _applyHasVariantParam(): void
    {
        if ($this->hasVariant === null) {
            return;
        }

        if ($this->hasVariant instanceof VariantQuery) {
            $variantQuery = $this->hasVariant;
        } elseif (is_array($this->hasVariant)) {
            $query = Variant::find();
            $variantQuery = Craft::configure($query, $this->hasVariant);
        } else {
            throw new QueryAbortedException('Invalid param used. ProductQuery::hasVariant param only expects a variant query or variant query config.');
        }

        $variantQuery->limit = null;
        $variantQuery->select('commerce_variants.primaryOwnerId');

        // Remove any blank product IDs (if any)
        $variantQuery->andWhere(['not', ['commerce_variants.primaryOwnerId' => null]]);

        // Uses exists subquery for speed to check for the variant
        $existsQuery = (new Query())
            ->from(['existssub' => $variantQuery])
            ->where(['existssub.primaryOwnerId' => new Expression('[[commerce_products.id]]')]);
        $this->subQuery->andWhere(['exists', $existsQuery]);
    }

    /**
     * Applies the 'ref' param to the query being prepared.
     */
    private function _applyRefParam(): void
    {
        if (!$this->ref) {
            return;
        }

        $refs = ArrayHelper::toArray($this->ref);
        $joinSections = false;
        $condition = ['or'];

        foreach ($refs as $ref) {
            $parts = array_filter(explode('/', $ref));

            if (!empty($parts)) {
                if (count($parts) == 1) {
                    $condition[] = Db::parseParam('elements_sites.slug', $parts[0]);
                } else {
                    $condition[] = [
                        'and',
                        Db::parseParam('commerce_producttypes.handle', $parts[0]),
                        Db::parseParam('elements_sites.slug', $parts[1]),
                    ];
                    $joinSections = true;
                }
            }
        }

        $this->subQuery->andWhere($condition);

        if ($joinSections) {
            $this->subQuery->innerJoin(Table::PRODUCTTYPES . ' commerce_producttypes', '[[producttypes.id]] = [[products.typeId]]');
        }
    }

    /**
     * @inheritdoc
     * @since 3.5.0
     */
    protected function cacheTags(): array
    {
        $tags = [];

        if ($this->typeId) {
            foreach ($this->typeId as $typeId) {
                $tags[] = "productType:$typeId";
            }
        }

        return $tags;
    }
}
