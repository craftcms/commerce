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
use craft\commerce\models\Address;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use DvK\Vat\Validator;
use Exception;
use function in_array;

/**
 * Tax Adjustments
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property Validator $vatValidator
 */
class Tax extends Component implements AdjusterInterface
{
    // Constants
    // =========================================================================

    const ADJUSTMENT_TYPE = 'tax';

    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_vatValidator;

    /**
     * @var Order
     */
    private $_order;

    /**
     * @var Address
     */
    private $_address;

    /**
     * @var bool
     */
    private $_isEstimated = false;

    /**
     * Track the additional discounts created inside the tax adjuster per line item
     *
     * @var array
     */
    private $_costRemovedByLineItem = [];

    /**
     * Track the additional discounts created inside the tax adjuster for order shipping costs
     *
     * @var float
     */
    private $_costRemovedForOrderShipping = 0;

    /**
     * Track the additional discounts created inside the tax adjuster for order total price
     *
     * @var array
     */
    private $_costRemovedForOrderTotalPrice = 0;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $this->_order = $order;

        $this->_address = $this->_order->getShippingAddress();
        if (!$this->_address) {
            $this->_address = $this->_order->getEstimatedShippingAddress();
            $this->_isEstimated = true;
        }

        if (Plugin::getInstance()->getSettings()->useBillingAddressForTax) {
            $this->_address = $this->_order->getBillingAddress();
            $this->_isEstimated = false;

            if (!$this->_address) {
                $this->_address = $this->_order->getEstimatedBillingAddress();
                $this->_isEstimated = true;
            }
        }

        $adjustments = [];
        $taxRates = Plugin::getInstance()->getTaxRates()->getAllTaxRates();

        /** @var TaxRate $rate */
        foreach ($taxRates as $rate) {
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

    // Private Methods
    // =========================================================================

    /**
     * @param TaxRate $taxRate
     * @return OrderAdjustment[]|false
     */
    private function _getAdjustments(TaxRate $taxRate)
    {
        $zone = $taxRate->taxZone;
        $adjustments = [];
        $removeVat = false;

        $vatIdOnAddress = ($this->_address && $this->_address->businessTaxId && $this->_address->country);

        // Do not bother checking VAT ID if the address doesn't match the zone anyway.
        $useZone = ($zone && $this->_matchAddress($zone));
        if ($taxRate->isVat && $vatIdOnAddress && ($useZone || $taxRate->getIsEverywhere())) {

            // Do we have a valid VAT ID in our cache?
            $validBusinessTaxId = Craft::$app->getCache()->exists('commerce:validVatId:' . $this->_address->businessTaxId);

            // If we do not have a valid VAT ID in cache, see if we can get one from the API
            if (!$validBusinessTaxId) {
                $validBusinessTaxId = $this->_validateVatNumber($this->_address->businessTaxId);
            }

            if ($validBusinessTaxId) {
                Craft::$app->getCache()->set('commerce:validVatId:' . $this->_address->businessTaxId, '1');
                $removeVat = true;
            }

            // Clean up if the API returned false and the item was still in cache
            if (!$validBusinessTaxId) {
                Craft::$app->getCache()->delete('commerce:validVatId:' . $this->_address->businessTaxId);
            }
        }

        //Address doesn't match zone or we should remove the VAT
        $doesNotMatchZone = (($zone && !$this->_matchAddress($zone)) && !$taxRate->getIsEverywhere());
        if ($doesNotMatchZone || $removeVat) {
            // Since the address doesn't match or it's a removable vat tax,
            // before we return false (no taxes) remove the tax if it was included in the taxable amount.
            if ($taxRate->include) {
                // Is this an order level tax rate?
                if (in_array($taxRate->taxable, TaxRateRecord::ORDER_TAXABALES, false)) {
                    $orderTaxableAmount = 0;

                    if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                        $orderTaxableAmount = $this->_getOrderTotalTaxablePrice($this->_order);
                    } else if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                        $orderTaxableAmount = $this->_order->getTotalShippingCost();
                    }

                    $amount = -($orderTaxableAmount - ($orderTaxableAmount / (1 + $taxRate->rate)));
                    $amount = Currency::round($amount);

                    if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                        $this->_costRemovedForOrderTotalPrice += $amount;
                    } else if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                        $this->_costRemovedForOrderShipping += $amount;
                    }

                    $adjustment = $this->_createAdjustment($taxRate);
                    // We need to display the adjustment that removed the included tax
                    $adjustment->name = $taxRate->name . ' ' . Craft::t('commerce', 'Removed');
                    $adjustment->amount = $amount;
                    $adjustment->type = 'discount'; // @TODO Not use a discount adjustment, but modify the price of the item instead.
                    $adjustment->included = false;

                    $adjustments[] = $adjustment;
                }

                // Not an order level taxable, add tax adjustments to the line items.
                foreach ($this->_order->getLineItems() as $item) {
                    if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                        $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                        $amount = -($taxableAmount - ($taxableAmount / (1 + $taxRate->rate)));
                        $amount = Currency::round($amount);

                        $adjustment = $this->_createAdjustment($taxRate);
                        // We need to display the adjustment that removed the included tax
                        $adjustment->name = $taxRate->name . ' ' . Craft::t('commerce', 'Removed');
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

                // Return the removed included taxes as discounts.
                return $adjustments;
            }

            return false;
        }

        // Is this an order level tax rate?
        if (in_array($taxRate->taxable, TaxRateRecord::ORDER_TAXABALES, false)) {

            $allItemsTaxFree = true;
            foreach ($this->_order->getLineItems() as $item) {
                if ($item->getPurchasable()->getIsTaxable()) {
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

            if (!$taxRate->include) {
                $amount = $taxRate->rate * $orderTaxableAmount;
                $orderTax = Currency::round($amount);
            } else {
                $amount = $orderTaxableAmount - ($orderTaxableAmount / (1 + $taxRate->rate));
                $orderTax = Currency::round($amount);
            }

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
            if ($item->taxCategoryId == $taxRate->taxCategoryId && $item->getPurchasable()->getIsTaxable()) {
                /**
                 * Any reduction in price to the line item we have added while inside this adjuster needs to be deducted,
                 * since the discount adjustments we just added won't be picked up in getTaxableSubtotal()
                 */
                $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                $objectId = spl_object_hash($item); // We use this ID since some line items are not saved in the DB yet and have no ID.
                $taxableAmount += $this->_costRemovedByLineItem[$objectId] ?? 0;

                if (!$taxRate->include) {
                    $amount = $taxRate->rate * $taxableAmount;
                    $itemTax = Currency::round($amount);
                } else {
                    $amount = $taxableAmount - ($taxableAmount / (1 + $taxRate->rate));
                    $itemTax = Currency::round($amount);
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
     * @param TaxAddressZone $zone
     * @return bool
     */
    private function _matchAddress(TaxAddressZone $zone): bool
    {
        //when having no address check default tax zones only
        if (!$this->_address) {
            return (bool)$zone->default;
        }

        return Plugin::getInstance()->getAddresses()->addressWithinZone($this->_address, $zone);
    }

    /**
     * @param string $businessVatId
     * @return bool
     */
    private function _validateVatNumber($businessVatId)
    {
        try {
            return $this->_getVatValidator()->validate($businessVatId);
        } catch (Exception $e) {
            Craft::error('Communication with VAT API failed: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    /**
     * @return Validator
     */
    private function _getVatValidator(): Validator
    {
        if ($this->_vatValidator === null) {
            $this->_vatValidator = new Validator();
        }

        return $this->_vatValidator;
    }

    /**
     * @param TaxRate $rate
     * @return OrderAdjustment
     */
    private function _createAdjustment(TaxRate $rate): OrderAdjustment
    {
        $adjustment = new OrderAdjustment;
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = $rate->name;
        $adjustment->description = $rate->rate * 100 . '%' . ($rate->include ? ' inc' : '');
        $adjustment->setOrder($this->_order);
        $adjustment->isEstimated = $this->_isEstimated;
        $adjustment->sourceSnapshot = $rate->toArray();

        return $adjustment;
    }

    /**
     * Returns the total price of the order, minus any tax adjustments.
     *
     * @return float
     */
    private function _getOrderTotalTaxablePrice(Order $order): float
    {
        $itemTotal = $order->getItemSubtotal();

        $allNonIncludedAdjustmentsTotal = $order->getAdjustmentsTotal();
        $taxAdjustments = $order->getTotalTax();
        $includedTaxAdjustments = $order->getTotalTaxIncluded();

        return $itemTotal + $allNonIncludedAdjustmentsTotal - ($taxAdjustments + $includedTaxAdjustments);
    }
}
