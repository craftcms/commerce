<?php

namespace craft\commerce\base;

use craft\base\ComponentInterface;

/**
 * Tax Engine Interface
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
interface TaxEngineInterface extends ComponentInterface
{
    /**
     * Return class name for the Adjuster to be used for tax
     */
    public function taxAdjusterClass(): string;

    /**
     * Whether Craft Commerce should show the tax categories interface
     * and allow tax categories to be edited.
     */
    public function viewTaxCategories(): bool;

    /**
     * Whether Craft Commerce should allow tax categories to be created by users.
     * will not be called if viewTaxCategories is returned as false;
     */
    public function createTaxCategories(): bool;

    /**
     * Whether Craft Commerce should allow tax categories to be edited.
     * will not be called if viewTaxCategories is returned as false;
     */
    public function editTaxCategories(): bool;

    /**
     * Whether Craft Commerce should allow tax categories to be deleted.
     * will not be called if viewTaxCategories is returned as false;
     */
    public function deleteTaxCategories(): bool;

    /**
     * Any action html to be added to the tax categories index header
     */
    public function taxCategoryActionHtml(): string;

    /**
     * Whether Craft Commerce should show the tax zones interface
     * and allow tax zones to be edited.
     */
    public function viewTaxZones(): bool;

    /**
     * Whether Craft Commerce should allow tax zones to be created by users.
     * will not be called if viewTaxZones is returned as false;
     */
    public function createTaxZones(): bool;

    /**
     * Whether Craft Commerce should allow tax zones to be edited.
     * will not be called if viewTaxZones is returned as false;
     */
    public function editTaxZones(): bool;

    /**
     * Whether Craft Commerce should allow tax zones to be deleted.
     * will not be called if viewTaxZones is returned as false;
     */
    public function deleteTaxZones(): bool;

    /**
     * Any action html to be added to the tax zones index header
     */
    public function taxZoneActionHtml(): string;


    /**
     * Whether Craft Commerce should show the tax rates interface
     * and allow tax rates to be edited.
     */
    public function viewTaxRates(): bool;

    /**
     * Whether Craft Commerce should allow tax rates to be created by users.
     * will not be called if viewTaxRates is returned as false;
     */
    public function createTaxRates(): bool;

    /**
     * Whether Craft Commerce should allow tax rates to be edited.
     * will not be called if viewTaxRates is returned as false;
     */
    public function editTaxRates(): bool;

    /**
     * Whether Craft Commerce should allow tax rates to be deleted.
     * will not be called if viewTaxRates is returned as false;
     */
    public function deleteTaxRates(): bool;

    /**
     * Any action html to be added to the tax rates index header
     */
    public function taxRateActionHtml(): string;

    /**
     * The tax subNav items
     */
    public function cpTaxNavSubItems(): array;
}
