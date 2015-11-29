<?php
namespace Craft;

/**
 * Product type locale model class.
 *
 * @property int $id
 * @property int $productTypeId
 * @property string $locale
 * @property string $urlFormat
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */

class Commerce_ProductTypeLocaleModel extends BaseModel
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $urlFormatIsRequired = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc BaseModel::rules()
     *
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();

        if ($this->urlFormatIsRequired) {
            $rules[] = ['urlFormat', 'required'];
        }

        return $rules;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc BaseModel::defineAttributes()
     *
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'productTypeId' => AttributeType::Number,
            'locale' => AttributeType::Locale,
            'urlFormat' => [AttributeType::UrlFormat, 'label' => 'URL Format']
        ];
    }
}
