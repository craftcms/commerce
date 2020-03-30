<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\engines;

use craft\commerce\base\TaxEngineInterface;
use craft\commerce\adjusters\Tax as TaxAdjuster;
use craft\commerce\Plugin;

class Tax implements TaxEngineInterface
{

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return 'Commerce Tax Engine';
    }

    public function taxAdjusterClass(): string
    {
        return TaxAdjuster::class;
    }

    /**
     * @inheritDoc
     */
    public function viewTaxCategories(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function createTaxCategories(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function editTaxCategories(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteTaxCategories(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function taxCategoryActionHtml(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function viewTaxZones(): bool
    {
        return true;
    }

    public function editTaxZones(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function viewTaxRates(): bool
    {
        return true;
    }

    public function editTaxRates(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function cpTaxNavSubItems(): array
    {
        return [];
    }
}