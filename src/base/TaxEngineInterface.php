<?php

namespace craft\commerce\base;

use yii\base\ComponentInterface;

/**
 * Tax Engine Interface
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
interface TaxEngineInterface extends \craft\base\ComponentInterface
{
    /**
     * Return class name for the Adjuster to be used for tax
     *
     * @return string
     */
    public function taxAdjusterClass(): string;

    /**
     * Whether Craft Commerce should show the tax categories interface
     * and allow tax categories to be edited.
     *
     * @return bool
     */
    public function viewTaxCategories(): bool;

    /**
     * Whether Craft Commerce should allow tax categories to be created by users.
     * will not be called if viewTaxCategories is returned as false;
     *
     * @return bool
     */
    public function createTaxCategories(): bool;

    /**
     * Whether Craft Commerce should allow tax categories to be edited.
     * will not be called if viewTaxCategories is returned as false;
     *
     * @return bool
     */
    public function editTaxCategories(): bool;

    /**
     * Whether Craft Commerce should allow tax categories to be deleted.
     * will not be called if viewTaxCategories is returned as false;
     *
     * @return bool
     */
    public function deleteTaxCategories(): bool;

    /**
     * Any action html to be added to the tax categories index header
     *
     * @return string
     */
    public function taxCategoryActionHtml(): string;

    /**
     * Whether Craft Commerce should show the tax zones interface
     * and allow tax zones to be edited.
     *
     * @return bool
     */
    public function viewTaxZones(): bool;

    /**
     * Whether Craft Commerce should allow tax zones to be created by users.
     * will not be called if viewTaxZones is returned as false;
     *
     * @return bool
     */
    public function createTaxZones(): bool;

    /**
     * Whether Craft Commerce should allow tax zones to be edited.
     * will not be called if viewTaxZones is returned as false;
     *
     * @return bool
     */
    public function editTaxZones(): bool;

    /**
     * Whether Craft Commerce should allow tax zones to be deleted.
     * will not be called if viewTaxZones is returned as false;
     *
     * @return bool
     */
    public function deleteTaxZones(): bool;

    /**
     * Any action html to be added to the tax zones index header
     *
     * @return string
     */
    public function taxZoneActionHtml(): string;


    /**
     * Whether Craft Commerce should show the tax rates interface
     * and allow tax rates to be edited.
     *
     * @return bool
     */
    public function viewTaxRates(): bool;

    /**
     * Whether Craft Commerce should allow tax rates to be created by users.
     * will not be called if viewTaxRates is returned as false;
     *
     * @return bool
     */
    public function createTaxRates(): bool;

    /**
     * Whether Craft Commerce should allow tax rates to be edited.
     * will not be called if viewTaxRates is returned as false;
     *
     * @return bool
     */
    public function editTaxRates(): bool;

    /**
     * Whether Craft Commerce should allow tax rates to be deleted.
     * will not be called if viewTaxRates is returned as false;
     *
     * @return bool
     */
    public function deleteTaxRates(): bool;

    /**
     * Any action html to be added to the tax rates index header
     *
     * @return string
     */
    public function taxRateActionHtml(): string;

    /**
     * The tax subNav items
     *
     * @return array
     */
    public function cpTaxNavSubItems(): array;
}
