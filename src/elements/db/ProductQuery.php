<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use Craft;
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

/**
 * ProductQuery represents a SELECT SQL statement for products in a way that is independent of DBMS.
 *
 * @method Product[]|array all($db = null)
 * @method Product|array|null one($db = null)
 * @method Product|array|null nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
 */
class ProductQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether the product is available for purchase
     */
    public $availableForPurchase;

    /**
     * @var bool Whether to only return products that the user has permission to edit.
     */
    public $editable = false;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $expiryDate;

    /**
     * @var float The default price the resulting products must have.
     */
    public $defaultPrice;

    /**
     * @var float The default height the resulting products must have.
     */
    public $defaultHeight;

    /**
     * @var float The default length the resulting products must have.
     */
    public $defaultLength;

    /**
     * @var float The default width the resulting products must have.
     */
    public $defaultWidth;

    /**
     * @var float The default weight the resulting products must have.
     */
    public $defaultWeight;

    /**
     * @var mixed The default sku the resulting products must have.
     */
    public $defaultSku;

    /**
     * @var VariantQuery|array only return products that match the resulting variant query.
     */
    public $hasVariant;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $postDate;

    /**
     * @var int|int[]|null The product type ID(s) that the resulting products must have.
     */
    public $typeId;

    /**
     * @inheritdoc
     */
    protected $defaultOrderBy = ['commerce_products.postDate' => SORT_DESC];

    // Public Methods
    // =========================================================================

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
            default:
                parent::__set($name, $value);
        }
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
     *     .type('foo')
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch {elements} with a Foo product type
     * ${elements-var} = {php-method}
     *     ->type('foo')
     *     ->all();
     * ```
     *
     * @param string|string[]|ProductType|null $value The property value
     * @return static self reference
     */
    public function type($value)
    {
        if ($value instanceof ProductType) {
            $this->typeId = $value->id;
        } else if ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from(['{{%commerce_producttypes}}'])
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
     *     .before(firstDayOfMonth)
     *     .all() %}
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
    public function before($value)
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
     *     .after(firstDayOfMonth)
     *     .all() %}
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
    public function after($value)
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
    public function editable(bool $value = true)
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
     *     .typeId(1)
     *     .all() %}
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
    public function typeId($value)
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
     */
    public function hasVariant($value)
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
     *     .postDate(['and', ">= #{start}", "< #{end}"])
     *     .all() %}
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
    public function postDate($value)
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
     *     .expiryDate("< #{nextMonth}")
     *     .all() %}
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
    public function expiryDate($value)
    {
        $this->expiryDate = $value;
        return $this;
    }

    /**
     * Narrows the query results to only products that are available for purchase.
     *
     * ---
     *
     * ```twig
     * {# Fetch products that are available for purchase #}
     * {% set {elements-var} = {twig-function}
     *     .availableForPurchase()
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch products that are available for purchase
     * ${elements-var} = {element-class}::find()
     *     ->availableForPurchase()
     *     ->all();
     * ```
     *
     * @param bool $value The property value
     * @return static self reference
     */
    public function availableForPurchase(bool $value = true)
    {
        $this->availableForPurchase = $value;
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
     * {% set {elements-var} = {twig-function}
     *     .status('disabled')
     *     .all() %}
     * ```
     *
     * ```php
     * // Fetch disabled {elements}
     * ${elements-var} = {element-class}::find()
     *     ->status('disabled')
     *     ->all();
     * ```
     */
    public function status($value)
    {
        return parent::status($value);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        $this->joinElementTable('commerce_products');

        $this->query->select([
            'commerce_products.id',
            'commerce_products.typeId',
            'commerce_products.promotable',
            'commerce_products.freeShipping',
            // TODO: uncomment after next breakpoint
            //'commerce_products.availableForPurchase',
            'commerce_products.postDate',
            'commerce_products.expiryDate',
            'commerce_products.defaultPrice',
            'commerce_products.defaultVariantId',
            'commerce_products.defaultSku',
            'commerce_products.defaultWeight',
            'commerce_products.defaultLength',
            'commerce_products.defaultWidth',
            'commerce_products.defaultHeight',
            'commerce_products.taxCategoryId',
            'commerce_products.shippingCategoryId'
        ]);

        // TODO: remove after next breakpoint
        $commerce = Craft::$app->getPlugins()->getStoredPluginInfo('commerce');
        if ($commerce && version_compare($commerce['version'], '2.0.0-beta.5', '>=')) {
            $this->query->addSelect(['commerce_products.availableForPurchase']);

            if ($this->availableForPurchase) {
                $this->subQuery->andWhere(['commerce_products.availableForPurchase' => true]);
            }
        }

        if ($this->postDate) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_products.postDate', $this->postDate));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('commerce_products.expiryDate', $this->expiryDate));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.typeId', $this->typeId));
        }

        if ($this->defaultPrice) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultPrice', $this->defaultPrice));
        }

        if ($this->defaultHeight) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultHeight', $this->defaultHeight));
        }

        if ($this->defaultLength) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultLength', $this->defaultLength));
        }

        if ($this->defaultWidth) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultWidth', $this->defaultWidth));
        }

        if ($this->defaultWeight) {
            $this->subQuery->andWhere(Db::parseParam('commerce_products.defaultWeight', $this->defaultWeight));
        }

        if ($this->defaultSku) {
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
    protected function statusCondition(string $status)
    {
        $currentTimeDb = Db::prepareDateForDb(new DateTime());

        switch ($status) {
            case Product::STATUS_LIVE:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'elements_sites.enabled' => true
                    ],
                    ['<=', 'commerce_products.postDate', $currentTimeDb],
                    [
                        'or',
                        ['commerce_products.expiryDate' => null],
                        ['>', 'commerce_products.expiryDate', $currentTimeDb]
                    ]
                ];
            case Product::STATUS_PENDING:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'elements_sites.enabled' => true,
                    ],
                    ['>', 'commerce_products.postDate', $currentTimeDb]
                ];
            case Product::STATUS_EXPIRED:
                return [
                    'and',
                    [
                        'elements.enabled' => true,
                        'elements_sites.enabled' => true
                    ],
                    ['not', ['commerce_products.expiryDate' => null]],
                    ['<=', 'commerce_products.expiryDate', $currentTimeDb]
                ];
            default:
                return parent::statusCondition($status);
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Applies the 'editable' param to the query being prepared.
     *
     * @throws QueryAbortedException
     */
    private function _applyEditableParam()
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
            'commerce_products.typeId' => Plugin::getInstance()->getProductTypes()->getEditableProductTypeIds()
        ]);
    }

    /**
     * Applies the hasVariant query condition
     */
    private function _applyHasVariantParam()
    {
        if ($this->hasVariant) {
            if ($this->hasVariant instanceof VariantQuery) {
                $variantQuery = $this->hasVariant;
            } else {
                $query = Variant::find();
                $variantQuery = Craft::configure($query, $this->hasVariant);
            }

            $variantQuery->limit = null;
            $variantQuery->select('commerce_variants.productId');
            $productIds = $variantQuery->asArray()->column();

            // Remove any blank product IDs (if any)
            $productIds = array_filter($productIds);

            $this->subQuery->andWhere(['commerce_products.id' => array_values($productIds)]);
        }
    }

    /**
     * Applies the 'ref' param to the query being prepared.
     */
    private function _applyRefParam()
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
                        Db::parseParam('elements_sites.slug', $parts[1])
                    ];
                    $joinSections = true;
                }
            }
        }

        $this->subQuery->andWhere($condition);

        if ($joinSections) {
            $this->subQuery->innerJoin('{{%commerce_producttypes}} commerce_producttypes', '[[producttypes.id]] = [[products.typeId]]');
        }
    }
}
