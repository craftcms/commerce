<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;

/**
 * Sale model.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Sale extends Model
{
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
     * @var bool Match all products
     */
    public $allProducts = false;

    /**
     * @var bool Match all product types
     */
    public $allProductTypes = false;

    /**
     * @var bool Enabled
     */
    public $enabled = true;

    /**
     * @var int[] Product Ids
     */
    private $_productIds;

    /**
     * @var int[] Product Type IDs
     */
    private $_productTypeIds;

    /**
     * @var int[] Group IDs
     */
    private $_userGroupIds;

    /**
     * @inheritdoc
     */
    public function rules()
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
            [['discountType', 'allGroups', 'allProducts', 'allProductTypes'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $names = parent::datetimeAttributes();
        $names[] = 'dateFrom';
        $names[] = 'dateTo';

        return $names;
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
    public function getDiscountAmountAsPercent()
    {
        $localeData = Craft::$app->getLocale();
        $percentSign = $localeData->getNumberSymbol(Locale::SYMBOL_PERCENT);

        if ($this->discountAmount != 0) {
            return -$this->discountAmount * 100 ."".$percentSign;
        }

        return "0".$percentSign;
    }

    /**
     * @return string
     */
    public function getDiscountAmountAsFlat()
    {
        return $this->discountAmount != 0 ? $this->discountAmount * -1 : 0;
    }

    /**
     * @param float $price
     *
     * @return float
     * @throws Exception
     */
    public function calculateTakeoff($price)
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
    public function getProductTypeIds(): array
    {
        if (null === $this->_productTypeIds) {
            $this->_loadRelations();
        }

        return $this->_productTypeIds;
    }

    /**
     * @return array
     */
    public function getProductIds(): array
    {
        if (null === $this->_productIds) {
            $this->_loadRelations();
        }

        return $this->_productIds;
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
     * Set the related product type ids
     *
     * @param array $ids
     *
     * @return void
     */
    public function setProductTypeIds(array $ids)
    {
        $this->_productTypeIds = array_unique($ids);
    }

     /**
     * Set the related product ids
     *
     * @param array $productIds
     *
     * @return void
     */
    public function setProductIds(array $productIds)
    {
        $this->_productIds = array_unique($productIds);
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