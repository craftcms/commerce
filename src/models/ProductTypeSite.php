<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * Product type locale model class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
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
     * @var string Locale
     */
    public $site;

    /**
     * @var string URL Format
     */
    public $urlFormat;

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
}
