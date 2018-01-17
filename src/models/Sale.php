<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Sale model.
 *
 * @property string       $discountAmountAsFlat
 * @property string|false $cpEditUrl
 * @property array        $categoryIds
 * @property array        $purchasableIds
 * @property string       $discountAmountAsPercent
 * @property array        $userGroupIds
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Sale extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var \DateTime|null Date From
     */
    public $dateFrom;

    /**
     * @var \DateTime|null Date To
     */
    public $dateTo;

    /**
     * @var string Discount Type
     */
    public $discountType;

    /**
     * @var float Discount amount
     */
    public $discountAmount;

    /**
     * @var bool Match all groups
     */
    public $allGroups = false;

    /**
     * @var bool Match all purchasables
     */
    public $allPurchasables = false;

    /**
     * @var bool Match all categories
     */
    public $allCategories = false;

    /**
     * @var bool Enabled
     */
    public $enabled = true;

    /**
     * @var int[] Product Ids
     */
    private $_purchsableIds;

    /**
     * @var int[] Product Type IDs
     */
    private $_categoryIds;

    /**
     * @var int[] Group IDs
     */
    private $_userGroupIds;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [
                ['discountType'],
                'in',
                'range' => [
                    'percent',
                    'flat'
                ],
            ],
            [['default', 'enabled'], 'boolean'],
            [['discountType', 'allGroups', 'allPurchasables', 'allCategories'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateFrom';
        $attributes[] = 'dateTo';
        return $attributes;
    }

    /**
     * @return string|false
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/promotions/sales/'.$this->id);
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsPercent(): string
    {
        if ($this->discountAmount !== 0) {
            return Craft::$app->formatter->asPercent(-$this->discountAmount);
        }

        return Craft::$app->formatter->asPercent(0);
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsFlat(): string
    {
        return $this->discountAmount !== 0 ? (string)($this->discountAmount * -1) : '0';
    }

    /**
     * @param float $price
     *
     * @return float
     */
    public function calculateTakeoff($price): float
    {
        if ($this->discountType === 'flat') {
            $takeOff = $this->discountAmount;
        } else {
            $takeOff = $this->discountAmount * $price;
        }

        return $takeOff;
    }

    /**
     * @return array
     */
    public function getCategoryIds(): array
    {
        if (null === $this->_categoryIds) {
            $this->_loadRelations();
        }

        return $this->_categoryIds;
    }

    /**
     * @return array
     */
    public function getPurchasableIds(): array
    {
        if (null === $this->_purchsableIds) {
            $this->_loadRelations();
        }

        return $this->_purchsableIds;
    }

    /**
     * @return array
     */
    public function getUserGroupIds(): array
    {
        if (null === $this->_userGroupIds) {
            $this->_loadRelations();
        }

        return $this->_userGroupIds;
    }

    /**
     * Set the related category ids
     *
     * @param array $ids
     *
     * @return void
     */
    public function setCategoryIds(array $ids)
    {
        $this->_categoryIds = array_unique($ids);
    }

    /**
     * Set the related purchasable ids
     *
     * @param array $purchasableIds
     *
     * @return void
     */
    public function setPurchasableIds(array $purchasableIds)
    {
        $this->_purchsableIds = array_unique($purchasableIds);
    }

    /**
     * Set the related user group ids
     *
     * @param array $userGroupIds
     *
     * @return void
     */
    public function setUserGroupIds(array $userGroupIds)
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }

    // Private Methods
    // =========================================================================

    /**
     * Load the sale relations
     *
     * @return void
     */
    private function _loadRelations()
    {
        Plugin::getInstance()->getSales()->populateSaleRelations($this);
    }
}
