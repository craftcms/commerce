<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\models\Site;
use yii\base\InvalidConfigException;

/**
 * Product type locale model class.
 *
 * @property ProductType $productType the Product Type
 * @property Site $site the Site
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
     * @var Site
     */
    private $_site;

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
     * @throws InvalidConfigException if [[productTypeId]] is missing or invalid
     */
    public function getProductType(): ProductType
    {
        if ($this->_productType !== null) {
            return $this->_productType;
        }

        if (!$this->productTypeId) {
            throw new InvalidConfigException('Product type site is missing its product type ID');
        }

        if (($this->_productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($this->productTypeId)) === null) {
            throw new InvalidConfigException('Invalid product type ID: ' . $this->productTypeId);
        }

        return $this->_productType;
    }

    /**
     * Sets the Product Type.
     *
     * @param ProductType $productType
     */
    public function setProductType(ProductType $productType)
    {
        $this->_productType = $productType;
    }

    /**
     * @return Site
     * @throws InvalidConfigException if [[siteId]] is missing or invalid
     */
    public function getSite(): Site
    {
        if ($this->_site !== null) {
            return $this->_site;
        }

        if (!$this->siteId) {
            throw new InvalidConfigException('Product type site is missing its site ID');
        }

        if (($this->_site = Craft::$app->getSites()->getSiteById($this->siteId)) === null) {
            throw new InvalidConfigException('Invalid site ID: ' . $this->siteId);
        }

        return $this->_site;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
