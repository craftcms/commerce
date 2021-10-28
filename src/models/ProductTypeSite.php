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
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int Product type ID
     */
    public int $productTypeId;

    /**
     * @var int Site ID
     */
    public int $siteId;

    /**
     * @var bool Has Urls
     */
    public bool $hasUrls = false;

    /**
     * @var string URL Format
     */
    public string $uriFormat;

    /**
     * @var string Template Path
     */
    public string $template;

    /**
     * @var ProductType|null
     */
    private ?ProductType $_productType = null;

    /**
     * @var Site|null
     */
    private ?Site $_site = null;

    /**
     * @var bool
     */
    public bool $uriFormatIsRequired = true;


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
    public function setProductType(ProductType $productType): void
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
    protected function defineRules(): array
    {
        $rules = [];

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
