<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

/**
 * Product type locale model class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 *
 * @property ProductType $productType
 */
class ProductTypeSite extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Product type ID
     */
    public $productTypeId;

    /**
     * @var int Site ID
     */
    public $siteId;

    /**
     * @var bool Has Urls
     */
    public $hasUrls;

    /**
     * @var string URL Format
     */
    public $uriFormat;

    /**
     * @var string Template Path
     */
    public $template;

    /**
     * @var ProductType
     */
    private $_productType;

    /**
     * @var bool
     */
    public $uriFormatIsRequired = true;

    // Public Methods
    // =========================================================================

    /**
     * Returns the Product Type.
     *
     * @return ProductType
     * @throws InvalidConfigException if [[groupId]] is missing or invalid
     */
    public function getProductType(): ProductType
    {
        if ($this->_productType !== null) {
            return $this->_productType;
        }

        if (!$this->productTypeId) {
            throw new InvalidConfigException('Category is missing its group ID');
        }

        if (($this->_productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($this->productTypeId)) === null) {
            throw new InvalidConfigException('Invalid group ID: '.$this->productTypeId);
        }

        return $this->_productType;
    }

    /**
     * Sets the Product Type.
     *
     * @param ProductType $productType
     *
     * @return void
     */
    public function setProductType(ProductType $productType)
    {
        $this->_productType = $productType;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
