<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\engines;

use craft\base\Component;
use craft\commerce\base\TaxEngineInterface;
use craft\commerce\adjusters\Tax as TaxAdjuster;

/**
 * Class Tax
 *
 * @package craft\commerce\engines
 * @since 3.1
 */
class Tax extends Component implements TaxEngineInterface
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

    /**
     * @inheritDoc
     */
    public function editTaxZones(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function createTaxZones(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteTaxZones(): bool
    {
        return true;
    }


    /**
     * @inheritDoc
     */
    public function taxZoneActionHtml(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function viewTaxRates(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function createTaxRates(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteTaxRates(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function editTaxRates(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function taxRateActionHtml(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function cpTaxNavSubItems(): array
    {
        return [];
    }
}
