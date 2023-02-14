<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Zone;
use craft\commerce\records\TaxZone;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

/**
 * Tax zone model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $cpEditUrl
 */
class TaxAddressZone extends Zone
{
    /**
     * @var bool Default
     */
    public bool $default = false;

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/tax/taxzones/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name'], UniqueValidator::class, 'targetClass' => TaxZone::class, 'targetAttribute' => ['name', 'storeId']];

        return $rules;

    }
}
