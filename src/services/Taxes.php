<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\base\Component;
use craft\commerce\base\TaxEngineInterface;
use craft\commerce\engines\Tax;
use craft\commerce\events\TaxEngineEvent;
use yii\base\InvalidConfigException;

/**
 * Class Taxes
 *
 * @package craft\commerce\services
 * @property TaxEngineInterface $engine
 */
class Taxes extends Component implements TaxEngineInterface
{
    /**
     * @event TaxEngineEvent The event that is triggered when determining the tax engine.
     * @since 3.1
     */
    public const EVENT_REGISTER_TAX_ENGINE = 'registerTaxEngine';

    /**
     * Get the current tax engine.
     */
    public function getEngine(): TaxEngineInterface
    {
        $event = new TaxEngineEvent(['engine' => new Tax()]);

        // Only allow third party tax engines for PRO edition
        if ($this->hasEventHandlers(self::EVENT_REGISTER_TAX_ENGINE)) {
            $this->trigger(self::EVENT_REGISTER_TAX_ENGINE, $event);
        }

        // Give plugins a chance to register the tax engine
        if (!$event->engine instanceof TaxEngineInterface) {
            throw new InvalidConfigException('No tax engine has been registered.');
        }

        return $event->engine;
    }

    /**
     * @inheritDoc
     */
    public function taxAdjusterClass(): string
    {
        return $this->getEngine()->taxAdjusterClass();
    }

    /**
     * @inheritDoc
     */
    public function viewTaxCategories(): bool
    {
        return $this->getEngine()->viewTaxCategories();
    }

    /**
     * @inheritDoc
     */
    public function createTaxCategories(): bool
    {
        return $this->getEngine()->createTaxCategories();
    }

    /**
     * @inheritDoc
     */
    public function editTaxCategories(): bool
    {
        return $this->getEngine()->editTaxCategories();
    }

    /**
     * @inheritDoc
     */
    public function deleteTaxCategories(): bool
    {
        return $this->getEngine()->deleteTaxCategories();
    }

    /**
     * @inheritDoc
     */
    public function taxCategoryActionHtml(): string
    {
        return $this->getEngine()->taxCategoryActionHtml();
    }

    /**
     * @inheritDoc
     */
    public function viewTaxZones(): bool
    {
        return $this->getEngine()->viewTaxZones();
    }

    /**
     * @inheritDoc
     */
    public function editTaxZones(): bool
    {
        return $this->getEngine()->editTaxZones();
    }

    /**
     * @inheritDoc
     */
    public function viewTaxRates(): bool
    {
        return $this->getEngine()->viewTaxRates();
    }

    /**
     * @inheritDoc
     */
    public function editTaxRates(): bool
    {
        return $this->getEngine()->editTaxRates();
    }

    /**
     * @inheritDoc
     */
    public function cpTaxNavSubItems(): array
    {
        return $this->getEngine()->cpTaxNavSubItems();
    }

    /**
     * @inheritDoc
     */
    public function createTaxZones(): bool
    {
        return $this->getEngine()->createTaxZones();
    }

    /**
     * @inheritDoc
     */
    public function deleteTaxZones(): bool
    {
        return $this->getEngine()->deleteTaxZones();
    }

    /**
     * @inheritDoc
     */
    public function taxZoneActionHtml(): string
    {
        return $this->getEngine()->taxZoneActionHtml();
    }

    /**
     * @inheritDoc
     */
    public function createTaxRates(): bool
    {
        return $this->getEngine()->createTaxRates();
    }

    /**
     * @inheritDoc
     */
    public function deleteTaxRates(): bool
    {
        return $this->getEngine()->deleteTaxRates();
    }

    /**
     * @inheritDoc
     */
    public function taxRateActionHtml(): string
    {
        return $this->getEngine()->taxRateActionHtml();
    }
}
