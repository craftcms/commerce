<?php

namespace craft\commerce\elements\db;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;

/**
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class ProductQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether to only return products that the user has permission to edit.
     */
    public $editable = false;

    /**
     * @var ProductType The product type the resulting products must belong to.
     */
    public $type;

    /**
     * @var int|int[]|null The product type ID(s) that the resulting products must have.
     */
    public $typeId;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $postDate;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $expiryDate;

    /**
     * @var mixed The Post Date that the resulting products must be after.
     */
    public $after;

    /**
     * @var mixed The Post Date that the resulting products must be before.
     */
    public $before;

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
     * @var float The default sku the resulting products must have.
     */
    public $defaultSku;

    /**
     * @var VariantQuery only return products that match the resulting variant query.
     */
    public $hasVariant;

    /**
     * @var bool The sale status the resulting products should have.
     */
    public $hasSales;

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
     * Sets the [[typeId]] property based on a given product types(s)’s handle(s).
     *
     * @param string|string[]|ProductType|null $value The property value
     *
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
     * Sets the [[postDate]] property to only allow products whose Post Date is before the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return static self reference
     */
    public function before($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '<'.$value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is after the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return static self reference
     */
    public function after($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '>='.$value;

        return $this;
    }

    /**
     * Sets the [[editable]] property.
     *
     * @param bool $value The property value (defaults to true)
     *
     * @return static self reference
     */
    public function editable(bool $value = true)
    {
        $this->editable = $value;

        return $this;
    }

    /**
     * Sets the [[typeId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function typeId($value)
    {
        $this->typeId = $value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property.
     *
     * @param mixed $value The property value
     *
     * @return static self reference
     */
    public function postDate($value)
    {
        $this->postDate = $value;

        return $this;
    }

    /**
     * Sets the [[expiryDate]] property.
     *
     * @param mixed $value The property value
     *
     * @return static self reference
     */
    public function expiryDate($value)
    {
        $this->expiryDate = $value;

        return $this;
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

        $this->_applyEditableParam();
        $this->_applyRefParam();
        $this->_applyHasSalesParam();

        if (!$this->orderBy) {
            $this->orderBy = 'postDate desc';
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        $currentTimeDb = Db::prepareDateForDb(new \DateTime());

        switch ($status) {
            case Product::STATUS_LIVE:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_sites.enabled' => '1'
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
                        'elements.enabled' => '1',
                        'elements_sites.enabled' => '1',
                    ],
                    ['>', 'commerce_products.postDate', $currentTimeDb]
                ];
            case Product::STATUS_EXPIRED:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_sites.enabled' => '1'
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
     * @return void
     * @throws QueryAbortedException
     */
    private function _applyEditableParam()
    {
        if (!$this->editable) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new QueryAbortedException();
        }

        // Limit the query to only the sections the user has permission to edit
        $this->subQuery->andWhere([
            'commerce_products.typeId' => Plugin::getInstance()->getProductTypes()->getEditableProductTypeIds()
        ]);
    }

    /**
     * Applies the 'ref' param to the query being prepared.
     *
     * @return void
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

    /**
     * @return void
     */
    private function _applyHasSalesParam()
    {
        if (null !== $this->hasSales) {
            $productsQuery = Product::find();
            $productsQuery->hasSales = null;
            $productsQuery->limit = null;
            /** @var Product[] $products */
            $products = $productsQuery->all();

            $productIds = [];
            foreach ($products as $product) {
                $sales = Plugin::getInstance()->getSales()->getSalesForProduct($product);

                if ($this->hasSales === true && count($sales) > 0) {
                    $productIds[] = $product->id;
                }

                if ($this->hasSales === false && count($sales) == 0) {
                    $productIds[] = $product->id;
                }
            }

            // Remove any blank product IDs (if any)
            $productIds = array_filter($productIds);

            $this->subQuery->andWhere(['in', 'commerce_products.id', $productIds]);
        }
    }
}
