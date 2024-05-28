<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\adjusters;

use Craft;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\elements\Address;
use Exception;
use Ibericode\Vat\Countries;
use Ibericode\Vat\Validator;
use function in_array;

/**
 * Tax Adjustments
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read TaxRate[] $taxRates
 * @property Validator $vatValidator
 */
class Tax extends Component implements AdjusterInterface
{
    public const ADJUSTMENT_TYPE = 'tax';

    /**
     * @var Validator|null
     */
    private ?Validator $_vatValidator = null;

    /**
     * @var Order
     */
    private Order $_order;

    /**
     * @var Address|null
     */
    private ?Address $_address = null;

    /**
     * @var TaxRate[]
     */
    private array $_taxRates;

    /**
     * @var bool
     */
    private bool $_isEstimated = false;

    /**
     * Track the additional discounts created inside the tax adjuster per line item
     *
     * @var array
     */
    private array $_costRemovedByLineItem = [];

    /**
     * Track the additional discounts created inside the tax adjuster for order shipping costs
     *
     * @var float
     */
    private float $_costRemovedForOrderShipping = 0;

    /**
     * Track the additional discounts created inside the tax adjuster for order total price
     *
     * @var float
     */
    private float $_costRemovedForOrderTotalPrice = 0;

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;
        $this->_address = $this->_getTaxAddress();
        $this->_taxRates = $this->getTaxRates();

        return $this->_adjustInternal();
    }

    private function _adjustInternal(): array
    {
        $adjustments = [];

        foreach ($this->_taxRates as $rate) {
            $newAdjustments = $this->_getAdjustments($rate);
            if ($newAdjustments) {
                $adjustments[] = $newAdjustments;
            }
        }

        if ($adjustments) {
            $adjustments = array_merge(...$adjustments);
        }

        return $adjustments;
    }


    /**
     * @return OrderAdjustment[]
     */
    private function _getAdjustments(TaxRate $taxRate): array
    {
        $adjustments = [];
        $hasValidEuVatId = false;

        $zoneMatches = $taxRate->getIsEverywhere() || ($taxRate->getTaxZone() && $this->_matchAddress($taxRate->getTaxZone()));

        if ($zoneMatches && $taxRate->isVat) {
            $hasValidEuVatId = $this->_hasValidVatOrganizationTaxId();
        }

        $removeIncluded = (!$zoneMatches && $taxRate->removeIncluded);
        $removeDueToVat = ($zoneMatches && $hasValidEuVatId && $taxRate->removeVatIncluded);
        if ($removeIncluded || $removeDueToVat) {

            // Is this an order level tax rate?
            if (in_array($taxRate->taxable, TaxRateRecord::ORDER_TAXABALES, false)) {
                $orderTaxableAmount = 0;

                if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                    $orderTaxableAmount = $this->_getOrderTotalTaxablePrice($this->_order);
                } elseif ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                    $orderTaxableAmount = $this->_order->getTotalShippingCost();
                }

                $amount = -$this->_getTaxAmount($orderTaxableAmount, $taxRate->rate, $taxRate->include);

                if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                    $this->_costRemovedForOrderTotalPrice += $amount;
                } elseif ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                    $this->_costRemovedForOrderShipping += $amount;
                }

                $adjustment = $this->_createAdjustment($taxRate);
                // We need to display the adjustment that removed the included tax
                $adjustment->name = Craft::t('site', $taxRate->name) . ' ' . Craft::t('commerce', 'Removed');
                $adjustment->amount = $amount;
                $adjustment->type = 'discount'; // TODO Not use a discount adjustment, but modify the price of the item instead. #COM-26
                $adjustment->included = false;

                $adjustments[] = $adjustment;
            }

            if (!in_array($taxRate->taxable, TaxRateRecord::ORDER_TAXABALES, false)) {
                // Not an order level taxable, add tax adjustments to the line items.
                foreach ($this->_order->getLineItems() as $item) {
                    if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                        if ($taxRate->taxable == TaxRateRecord::TAXABLE_PURCHASABLE) {
                            $taxableAmount = $item->salePrice - Currency::round($item->getDiscount() / $item->qty);
                            $amount = -($taxableAmount - ($taxableAmount / (1 + $taxRate->rate)));
                            $amount = $amount * $item->qty;
                        } else {
                            $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                            $amount = -($taxableAmount - ($taxableAmount / (1 + $taxRate->rate)));
                        }
                        $amount = Currency::round($amount);
                        $adjustment = $this->_createAdjustment($taxRate);
                        // We need to display the adjustment that removed the included tax
                        $adjustment->name = Craft::t('site', $taxRate->name) . ' ' . Craft::t('commerce', 'Removed');
                        $adjustment->amount = $amount;
                        $adjustment->setLineItem($item);
                        $adjustment->type = 'discount';
                        $adjustment->included = false;

                        $objectId = spl_object_hash($item); // We use this ID since some line items are not saved in the DB yet and have no ID.

                        if (isset($this->_costRemovedByLineItem[$objectId])) {
                            $this->_costRemovedByLineItem[$objectId] += $amount;
                        } else {
                            $this->_costRemovedByLineItem[$objectId] = $amount;
                        }

                        $adjustments[] = $adjustment;
                    }
                }
            }
            // Return the removed included taxes as discounts.
            return $adjustments;
        }

        if (!$zoneMatches || ($taxRate->isVat && $hasValidEuVatId)) {
            return [];
        }

        // We have taxes to add!

        // Is this an order level tax rate?
        if (in_array($taxRate->taxable, TaxRateRecord::ORDER_TAXABALES, false)) {
            $allItemsTaxFree = true;
            foreach ($this->_order->getLineItems() as $item) {
                if ($item->getIsTaxable()) {
                    $allItemsTaxFree = false;
                }
            }

            // Will not have any taxes, even for order level taxes.
            if ($allItemsTaxFree) {
                return [];
            }

            $orderTaxableAmount = 0;

            if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                $orderTaxableAmount = $this->_getOrderTotalTaxablePrice($this->_order);
                $orderTaxableAmount += $this->_costRemovedForOrderTotalPrice;
            }

            if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                $orderTaxableAmount = $this->_order->getTotalShippingCost();
                $orderTaxableAmount += $this->_costRemovedForOrderShipping;
            }

            $orderTax = $this->_getTaxAmount($orderTaxableAmount, $taxRate->rate, $taxRate->include);

            $adjustment = $this->_createAdjustment($taxRate);
            // We need to display the adjustment that removed the included tax
            $adjustment->amount = $orderTax;

            if ($taxRate->include) {
                $adjustment->included = true;
            }

            return [$adjustment];
        }

        // not an order level tax rate, create line item adjustments.
        foreach ($this->_order->getLineItems() as $item) {
            if ($item->taxCategoryId == $taxRate->taxCategoryId && $item->getIsTaxable()) {
                // We use this ID since some line items are not saved in the DB yet and have no ID.
                $objectId = spl_object_hash($item);
                /**
                 * Any reduction in price to the line item we have added while inside this adjuster needs to be deducted,
                 * since the discount adjustments we just added won't be picked up in getTaxableSubtotal()
                 */
                if ($taxRate->taxable == TaxRateRecord::TAXABLE_PURCHASABLE) {
                    $purchasableAmount = $item->salePrice - Currency::round($item->getDiscount() / $item->qty);
                    $purchasableAmount += Currency::round(($this->_costRemovedByLineItem[$objectId] ?? 0) / $item->qty);
                    $purchasableTax = $this->_getTaxAmount($purchasableAmount, $taxRate->rate, $taxRate->include);
                    $itemTax = $purchasableTax * $item->qty; //already rounded
                } else {
                    $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                    $taxableAmount += $this->_costRemovedByLineItem[$objectId] ?? 0;
                    $itemTax = $this->_getTaxAmount($taxableAmount, $taxRate->rate, $taxRate->include);
                }

                $adjustment = $this->_createAdjustment($taxRate);
                // We need to display the adjustment that removed the included tax
                $adjustment->amount = $itemTax;
                $adjustment->setLineItem($item);

                if ($taxRate->include) {
                    $adjustment->included = true;
                }

                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /**
     * @return TaxRate[]
     */
    protected function getTaxRates(): array
    {
        return Plugin::getInstance()->getTaxRates()->getAllTaxRates();
    }

    /**
     * @param $taxableAmount
     * @param $rate
     * @param $included
     * @return float
     * @since 3.1
     */
    private function _getTaxAmount($taxableAmount, $rate, $included): float
    {
        if (!$included) {
            $incTax = $taxableAmount * (1 + $rate);
            $incTax = Currency::round($incTax);
            $tax = $incTax - $taxableAmount;
        } else {
            $exTax = $taxableAmount / (1 + $rate);
            $exTax = Currency::round($exTax);
            $tax = $taxableAmount - $exTax;
        }

        return $tax;
    }

    /**
     * @param TaxAddressZone $zone
     * @return bool
     */
    private function _matchAddress(TaxAddressZone $zone): bool
    {
        //when having no address check default tax zones only
        if (!$this->_address) {
            return $zone->default;
        }

        return $zone->getCondition()->matchElement($this->_address);
    }

    /**
     * @return bool
     */
    private function _hasValidVatOrganizationTaxId(): bool
    {
        if (!$this->_address) {
            return false;
        }

        if (!$this->_address->organizationTaxId) {
            return false;
        }

        if (!$this->_address->getCountryCode()) {
            return false;
        }

        if (!$this->getVatValidator()->validateCountryCode($this->_address->getCountryCode())) {
            return false;
        }

        if (!(new Countries)->isCountryCodeInEU($this->_address->getCountryCode())) {
            return false;
        }

        $validOrganizationTaxIdExistsInCache = Craft::$app->getCache()->exists('commerce:validVatId:' . $this->_address->organizationTaxId);

        // If we do not have a VAT ID in cache, set it from the API
        if (!$validOrganizationTaxIdExistsInCache) {

            $valid = $this->validateVatNumber($this->_address->organizationTaxId) ? '1' : '0';
            Craft::$app->getCache()->set('commerce:validVatId:' . $this->_address->organizationTaxId, $valid);
        }

        return Craft::$app->getCache()->get('commerce:validVatId:' . $this->_address->organizationTaxId) === '1';
    }

    /**
     * @param string $businessVatId
     * @return bool
     * @deprecated in 4.6.2 Use the `$this->_getVatValidator()->validateVatNumber()` method instead.
     */
    protected function validateVatNumber(string $businessVatId): bool
    {
        try {
            return $this->getVatValidator()->validateVatNumber($businessVatId);
        } catch (Exception $e) {
            Craft::error('Communication with VAT API failed: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    /**
     * @return Validator
     */
    protected function getVatValidator(): Validator
    {
        if ($this->_vatValidator === null) {
            $this->_vatValidator = new Validator();
        }

        return $this->_vatValidator;
    }

    /**
     * @return Validator
     * @deprecated in 4.6.2 Use the `$this->getVatValidator()` method instead.
     */
    protected function _getVatValidator(): Validator
    {
        return $this->getVatValidator();
    }

    private function _createAdjustment(TaxRate $rate): OrderAdjustment
    {
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = Craft::t('site', $rate->name);
        $adjustment->description = $rate->rate * 100 . '%';
        $adjustment->setOrder($this->_order);
        $adjustment->isEstimated = $this->_isEstimated;
        $adjustment->sourceSnapshot = $rate->toArray();

        return $adjustment;
    }

    /**
     * Returns the total price of the order, minus any tax adjustments.
     */
    private function _getOrderTotalTaxablePrice(Order $order): float
    {
        $itemTotal = $order->getItemSubtotal();

        $allNonIncludedAdjustmentsTotal = $order->getAdjustmentsTotal();
        $taxAdjustments = $order->getTotalTax();
        $includedTaxAdjustments = $order->getTotalTaxIncluded();

        return $itemTotal + $allNonIncludedAdjustmentsTotal - ($taxAdjustments + $includedTaxAdjustments);
    }

    /**
     * @return Address|null
     */
    private function _getTaxAddress(): ?Address
    {
        $this->_isEstimated = false;
        if (!Plugin::getInstance()->getSettings()->useBillingAddressForTax) {
            $address = $this->_order->getShippingAddress();
            if (!$address) {
                $address = $this->_order->getEstimatedShippingAddress();
                $this->_isEstimated = true;
            }
        } else {
            $address = $this->_order->getBillingAddress();
            if (!$address) {
                $address = $this->_order->getEstimatedBillingAddress();
                $this->_isEstimated = true;
            }
        }

        return $address;
    }
}
