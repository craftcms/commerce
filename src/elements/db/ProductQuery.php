<?php
namespace craft\commerce\elements\db;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\Tag;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\models\TagGroup;
use yii\db\Connection;

/**
 * TagQuery represents a SELECT SQL statement for tags in a way that is independent of DBMS.
 *
 * @property string|string[]|TagGroup $group The handle(s) of the tag group(s) that resulting tags must belong to.
 *
 * @method Tag[]|array all($db = null)
 * @method Tag|array|null one($db = null)
 * @method Tag|array|null nth(int $n, Connection $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class ProductQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    // General parameters
    // -------------------------------------------------------------------------

    /**
     * @var bool Whether to only return entries that the user has permission to edit.
     */
    public $editable = false;

    /**
     * @var int|int[]|null The section ID(s) that the resulting entries must be in.
     */
    public $sectionId;

    /**
     * @var int|int[]|null The entry type ID(s) that the resulting entries must have.
     */
    public $typeId;

    /**
     * @var int|int[]|null The user ID(s) that the resulting entries’ authors must have.
     */
    public $authorId;

    /**
     * @var int|int[]|null The user group ID(s) that the resulting entries’ authors must be in.
     */
    public $authorGroupId;

    /**
     * @var mixed The Post Date that the resulting entries must have.
     */
    public $postDate;

    /**
     * @var mixed The Expiry Date that the resulting entries must have.
     */
    public $expiryDate;


//'after' => AttributeType::Mixed,
//'before' => AttributeType::Mixed,
//'defaultPrice' => AttributeType::Mixed,
//'editable' => AttributeType::Bool,
//'expiryDate' => AttributeType::Mixed,
//'order' => [AttributeType::String, 'default' => 'postDate desc'],
//'postDate' => AttributeType::Mixed,
//'status' => [AttributeType::String, 'default' => Product::LIVE],
//'type' => AttributeType::Mixed,
//'typeId' => AttributeType::Mixed,
//'withVariant' => AttributeType::Mixed,
//'hasVariant' => AttributeType::Mixed,
//'hasSales' => AttributeType::Mixed,
//'defaultHeight' => AttributeType::Mixed,
//'defaultLength' => AttributeType::Mixed,
//'defaultWidth' => AttributeType::Mixed,
//'defaultWeight' => AttributeType::Mixed

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
            case 'section':
                $this->section($value);
                break;
            case 'type':
                $this->type($value);
                break;
            case 'authorGroup':
                $this->authorGroup($value);
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
     * Sets the [[sectionId]] property based on a given section(s)’s handle(s).
     *
     * @param string|string[]|Section|null $value The property value
     *
     * @return static self reference
     */
    public function section($value)
    {
        if ($value instanceof Section) {
            $this->structureId = ($value->structureId ?: false);
            $this->sectionId = $value->id;
        } else if ($value !== null) {
            $this->sectionId = (new Query())
                ->select(['id'])
                ->from(['{{%sections}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->sectionId = null;
        }

        return $this;
    }

    /**
     * Sets the [[typeId]] property based on a given entry type(s)’s handle(s).
     *
     * @param string|string[]|EntryType|null $value The property value
     *
     * @return static self reference
     */
    public function type($value)
    {
        if ($value instanceof EntryType) {
            $this->typeId = $value->id;
        } else if ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from(['{{%entrytypes}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Sets the [[authorGroupId]] property based on a given user group(s)’s handle(s).
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function authorGroup($value)
    {
        if ($value instanceof UserGroup) {
            $this->authorGroupId = $value->id;
        } else if ($value !== null) {
            $this->authorGroupId = (new Query())
                ->select(['id'])
                ->from(['{{%usergroups}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->authorGroupId = null;
        }

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow entries whose Post Date is before the given value.
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
     * Sets the [[postDate]] property to only allow entries whose Post Date is after the given value.
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
     * Sets the [[sectionId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function sectionId($value)
    {
        $this->sectionId = $value;

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
     * Sets the [[authorId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function authorId($value)
    {
        $this->authorId = $value;

        return $this;
    }

    /**
     * Sets the [[authorGroupId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function authorGroupId($value)
    {
        $this->authorGroupId = $value;

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
        // See if 'section', 'type', or 'authorGroup' were set to invalid handles
        if ($this->sectionId === [] || $this->typeId === [] || $this->authorGroupId === []) {
            return false;
        }

        $this->joinElementTable('entries');

        $this->query->select([
            'entries.sectionId',
            'entries.typeId',
            'entries.authorId',
            'entries.postDate',
            'entries.expiryDate',
        ]);

        if ($this->postDate) {
            $this->subQuery->andWhere(Db::parseDateParam('entries.postDate', $this->postDate));
        }

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('entries.expiryDate', $this->expiryDate));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('entries.typeId', $this->typeId));
        }

        if (Craft::$app->getEdition() >= Craft::Client) {
            if ($this->authorId) {
                $this->subQuery->andWhere(Db::parseParam('entries.authorId', $this->authorId));
            }

            if ($this->authorGroupId) {
                $this->subQuery
                    ->innerJoin('{{%usergroups_users}} usergroups_users', '[[usergroups_users.userId]] = [[entries.authorId]]')
                    ->andWhere(Db::parseParam('usergroups_users.groupId', $this->authorGroupId));
            }
        }

        $this->_applyEditableParam();
        $this->_applySectionIdParam();
        $this->_applyRefParam();

        if (!$this->orderBy && !$this->structureId && !$this->fixedOrder) {
            $this->orderBy = 'postDate desc';
        }

        return parent::beforePrepare();
    }

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
            'entries.sectionId' => Craft::$app->getSections()->getEditableSectionIds()
        ]);

        // Enforce the editPeerEntries permissions for non-Single sections
        foreach (Craft::$app->getSections()->getEditableSections() as $section) {
            if ($section->type != Section::TYPE_SINGLE && !$user->can('editPeerEntries:'.$section->id)) {
                $this->subQuery->andWhere([
                    'or',
                    ['not', ['entries.sectionId' => $section->id]],
                    ['entries.authorId' => $user->id]
                ]);
            }
        }
    }

//    public function getElementQueryStatusCondition(DbCommand $query, $status)
//    {
//        $currentTimeDb = DateTimeHelper::currentTimeForDb();
//
//        switch ($status) {
//            case Product::LIVE: {
//                return [
//                    'and',
//                    'elements.enabled = 1',
//                    'elements_i18n.enabled = 1',
//                    "products.postDate <= '{$currentTimeDb}'",
//                    ['or', 'products.expiryDate is null', "products.expiryDate > '{$currentTimeDb}'"]
//                ];
//            }
//
//            case Product::PENDING: {
//                return [
//                    'and',
//                    'elements.enabled = 1',
//                    'elements_i18n.enabled = 1',
//                    "products.postDate > '{$currentTimeDb}'"
//                ];
//            }
//
//            case Product::EXPIRED: {
//                return [
//                    'and',
//                    'elements.enabled = 1',
//                    'elements_i18n.enabled = 1',
//                    'products.expiryDate is not null',
//                    "products.expiryDate <= '{$currentTimeDb}'"
//                ];
//            }
//        }
//    }

    // Private Methods
    // =========================================================================

    /**
     * Applies the 'sectionId' param to the query being prepared.
     */
    private function _applySectionIdParam()
    {
        if ($this->sectionId) {
            // Should we set the structureId param?
            if ($this->structureId === null && (!is_array($this->sectionId) || count($this->sectionId) === 1)) {
                $structureId = (new Query())
                    ->select(['structureId'])
                    ->from(['{{%sections}}'])
                    ->where(Db::parseParam('id', $this->sectionId))
                    ->scalar();
                $this->structureId = $structureId ? (int)$structureId : false;
            }

            $this->subQuery->andWhere(Db::parseParam('entries.sectionId', $this->sectionId));
        }
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
                    $condition[] = Db::parseParam('elements_i18n.slug', $parts[0]);
                } else {
                    $condition[] = [
                        'and',
                        Db::parseParam('sections.handle', $parts[0]),
                        Db::parseParam('elements_i18n.slug', $parts[1])
                    ];
                    $joinSections = true;
                }
            }
        }

        $this->subQuery->andWhere($condition);

        if ($joinSections) {
            $this->subQuery->innerJoin('{{%sections}} sections', '[[sections.id]] = [[entries.sectionId]]');
        }
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        $currentTimeDb = Db::prepareDateForDb(new \DateTime());

        switch ($status) {
            case Entry::STATUS_LIVE:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_i18n.enabled' => '1'
                    ],
                    ['<=', 'entries.postDate', $currentTimeDb],
                    [
                        'or',
                        ['entries.expiryDate' => null],
                        ['>', 'entries.expiryDate', $currentTimeDb]
                    ]
                ];
            case Entry::STATUS_PENDING:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_i18n.enabled' => '1',
                    ],
                    ['>', 'entries.postDate', $currentTimeDb]
                ];
            case Entry::STATUS_EXPIRED:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_i18n.enabled' => '1'
                    ],
                    ['not', ['entries.expiryDate' => null]],
                    ['<=', 'entries.expiryDate', $currentTimeDb]
                ];
            default:
                return parent::statusCondition($status);
        }
    }

//    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
//    {
//        $query
//            ->addSelect("products.id, products.typeId, products.promotable, products.freeShipping, products.postDate, products.expiryDate, products.defaultPrice, products.defaultVariantId, products.defaultSku, products.defaultWeight, products.defaultLength, products.defaultWidth, products.defaultHeight, products.taxCategoryId, products.shippingCategoryId")
//            ->join('commerce_products products', 'products.id = elements.id')
//            ->join('commerce_producttypes producttypes', 'producttypes.id = products.typeId');
//
//        if ($criteria->postDate) {
//            $query->andWhere(DbHelper::parseDateParam('products.postDate', $criteria->postDate, $query->params));
//        } else {
//            if ($criteria->after) {
//                $query->andWhere(DbHelper::parseDateParam('products.postDate', '>='.$criteria->after, $query->params));
//            }
//
//            if ($criteria->before) {
//                $query->andWhere(DbHelper::parseDateParam('products.postDate', '<'.$criteria->before, $query->params));
//            }
//        }
//
//        if ($criteria->expiryDate) {
//            $query->andWhere(DbHelper::parseDateParam('products.expiryDate', $criteria->expiryDate, $query->params));
//        }
//
//        if ($criteria->type) {
//            if ($criteria->type instanceof ProductType) {
//                $criteria->typeId = $criteria->type->id;
//                $criteria->type = null;
//            } else {
//                $query->andWhere(DbHelper::parseParam('producttypes.handle', $criteria->type, $query->params));
//            }
//        }
//
//        if ($criteria->typeId) {
//            $query->andWhere(DbHelper::parseParam('products.typeId', $criteria->typeId, $query->params));
//        }
//
//        if ($criteria->defaultPrice) {
//            $query->andWhere(DbHelper::parseParam('products.defaultPrice', $criteria->defaultPrice, $query->params));
//        }
//
//        if ($criteria->defaultHeight) {
//            $query->andWhere(DbHelper::parseParam('products.defaultHeight', $criteria->defaultHeight, $query->params));
//        }
//
//        if ($criteria->defaultLength) {
//            $query->andWhere(DbHelper::parseParam('products.defaultLength', $criteria->defaultLength, $query->params));
//        }
//
//        if ($criteria->defaultWidth) {
//            $query->andWhere(DbHelper::parseParam('products.defaultWidth', $criteria->defaultWidth, $query->params));
//        }
//
//        if ($criteria->defaultWeight) {
//            $query->andWhere(DbHelper::parseParam('products.defaultWeight', $criteria->defaultWeight, $query->params));
//        }
//
//        if ($criteria->withVariant) {
//            $criteria->hasVariant = $criteria->withVariant;
//            craft()->deprecator->log('Commerce:withVariant_param', 'The withVariant product param has been deprecated. Use hasVariant instead.');
//            $criteria->withVariant = null;
//        }
//
//        if ($criteria->hasVariant) {
//            if ($criteria->hasVariant instanceof ElementCriteriaModel) {
//                $variantCriteria = $criteria->hasVariant;
//            } else {
//                $variantCriteria = Craft::$app->getElements()->getCriteria('Variant', $criteria->hasVariant);
//            }
//
//            $variantCriteria->limit = null;
//            $elementQuery = Craft::$app->getElements()->buildElementsQuery($variantCriteria);
//            $productIds = null;
//            if ($elementQuery) {
//                $productIds = Craft::$app->getElements()->buildElementsQuery($variantCriteria)
//                    ->selectDistinct('productId')
//                    ->queryColumn();
//            }
//
//            if (!$productIds) {
//                return false;
//            }
//
//            // Remove any blank product IDs (if any)
//            $productIds = array_filter($productIds);
//
//            $query->andWhere(['in', 'products.id', $productIds]);
//        }
//
//        if ($criteria->editable) {
//            $user = Craft::$app->getUser()->getUser();
//
//            if (!$user) {
//                return false;
//            }
//
//            // Limit the query to only the sections the user has permission to edit
//            $editableProductTypeIds = Plugin::getInstance()->getProductTypes()->getEditableProductTypeIds();
//
//            if (!$editableProductTypeIds) {
//                return false;
//            }
//
//            $query->andWhere(['in', 'products.typeId', $editableProductTypeIds]);
//        }
//
//
//        if ($criteria->hasSales !== null) {
//            $productsCriteria = Craft::$app->getElements()->getCriteria('Product', $criteria);
//            $productsCriteria->hasSales = null;
//            $productsCriteria->limit = null;
//            $products = $productsCriteria->find();
//
//            $productIds = [];
//            foreach ($products as $product) {
//                $sales = Plugin::getInstance()->getSales()->getSalesForProduct($product);
//
//                if ($criteria->hasSales === true && count($sales) > 0) {
//                    $productIds[] = $product->id;
//                }
//
//                if ($criteria->hasSales === false && count($sales) == 0) {
//                    $productIds[] = $product->id;
//                }
//            }
//
//            // Remove any blank product IDs (if any)
//            $productIds = array_filter($productIds);
//
//            $query->andWhere(['in', 'products.id', $productIds]);
//        }
//
//        return true;
//    }


//    public function getEagerLoadingMap($sourceElements, $handle)
//    {
//        if ($handle == 'variants') {
//            // Get the source element IDs
//            $sourceElementIds = [];
//
//            foreach ($sourceElements as $sourceElement) {
//                $sourceElementIds[] = $sourceElement->id;
//            }
//
//            $map = Craft::$app->getDb()->createCommand()
//                ->select('productId as source, id as target')
//                ->from('commerce_variants')
//                ->where(['in', 'productId', $sourceElementIds])
//                ->order('sortOrder asc')
//                ->queryAll();
//
//            return [
//                'elementType' => 'Variant',
//                'map' => $map
//            ];
//        }
//
//        return parent::getEagerLoadingMap($sourceElements, $handle);
//    }
}