<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Adjuster;

use craft\commerce\Plugin;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Commerce\Order\Adjuster\Contracts\AdjusterInterface;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Models\OrderAdjustment;
use CraftCms\Commerce\Store\Exceptions\StoreNotFoundException;
use CraftCms\Commerce\Tax\Contracts\TaxIdValidatorInterface;
use CraftCms\Commerce\Tax\Models\EuVatIdValidator;
use CraftCms\Commerce\Tax\Models\TaxAddressZone;
use CraftCms\Commerce\Tax\Models\TaxRate;
use CraftCms\Commerce\Tax\Records\TaxRate as TaxRateRecord;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Money\Teller;
use yii\base\InvalidConfigException;

use function CraftCms\Cms\t;
use function in_array;

/**
 * Tax Adjustments
 */
class Tax implements AdjusterInterface
{
    public const ADJUSTMENT_TYPE = 'tax';

    private Order $_order;

    private ?Address $_address = null;

    /**
     * @var Collection<TaxRate>
     */
    private Collection $_taxRates;

    private bool $_isEstimated = false;

    /**
     * Track the additional discounts created inside the tax adjuster per line item
     */
    private array $_costRemovedByLineItem = [];

    /**
     * Track the additional discounts created inside the tax adjuster for order shipping costs
     */
    private float $_costRemovedForOrderShipping = 0;

    /**
     * Track the additional discounts created inside the tax adjuster for total price
     *
     * @internal This should not be modified directly, use _addAmountRemovedForOrderShipping() instead
     * @see _addAmountRemovedForOrderTotalPrice()
     */
    private float $_costRemovedForOrderTotalPrice = 0;

    /**
     * The way to internally interact with the _costRemovedForOrderShipping property
     *
     * @throws Exception
     */
    private function _addAmountRemovedForOrderShipping(float $amount): void
    {
        if ($amount > 0) {
            throw new Exception('Amount added to the total removed shipping must be a negative number');
        }

        $this->_costRemovedForOrderShipping = (float)$this->_getTeller()->add($this->_costRemovedForOrderShipping, $amount);
    }

    /**
     * The way to interact with the _costRemovedForOrderTotalPrice property
     *
     * @throws Exception
     */
    private function _addAmountRemovedForOrderTotalPrice(float $amount): void
    {
        if ($amount > 0) {
            throw new Exception('Amount added to the total removed price must be a negative number');
        }

        $this->_costRemovedForOrderTotalPrice = (float)$this->_getTeller()->add($this->_costRemovedForOrderTotalPrice, $amount);
    }

    #[\Override]
    public function adjust(Order $order): array
    {
        $this->_order = $order;
        $this->_address = $this->_getTaxAddress();
        $this->_taxRates = $this->getTaxRates($order->storeId);

        return $this->_adjustInternal();
    }

    private function _adjustInternal(): array
    {
        $adjustments = [];

        foreach ($this->_taxRates as $rate) {
            if (!$rate->enabled) {
                continue;
            }
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
        $teller = $this->_getTeller();
        $hasValidTaxId = false;

        $zoneMatches = $taxRate->getIsEverywhere() || ($taxRate->getTaxZone() && $this->_matchAddress($taxRate->getTaxZone()));

        if ($zoneMatches && $taxRate->hasTaxIdValidators()) {
            $hasValidTaxId = $this->organizationTaxIdIsValidTaxId($taxRate->getSelectedEnabledTaxIdValidators());
        }

        $removeIncluded = (!$zoneMatches && $taxRate->removeIncluded);
        $removeDueToVatId = ($zoneMatches && $hasValidTaxId && $taxRate->removeVatIncluded);
        if ($removeIncluded || $removeDueToVatId) {

            // Remove included tax for order level taxable.
            if (in_array($taxRate->taxable, TaxRateRecord::ORDER_TAXABALES, false)) {
                $orderTaxableAmount = 0;

                if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                    $orderTaxableAmount = $this->_getOrderTotalTaxablePrice($this->_order);
                } elseif ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                    $orderTaxableAmount = $this->_order->getTotalShippingCost();
                }

                $orderLevelAmountToBeRemovedByDiscount = -$this->_getTaxAmount($orderTaxableAmount, $taxRate->rate, $taxRate->include);

                if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) {
                    $this->_addAmountRemovedForOrderTotalPrice($orderLevelAmountToBeRemovedByDiscount);
                } elseif ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                    $this->_addAmountRemovedForOrderShipping($orderLevelAmountToBeRemovedByDiscount);
                }

                $adjustment = $this->_createAdjustment($taxRate);
                // We need to display the adjustment that removed the included tax
                $adjustment->name = t($taxRate->name, category: 'site') . ' ' . t('Removed', category: 'commerce');
                $adjustment->amount = $orderLevelAmountToBeRemovedByDiscount;
                $adjustment->type = 'discount'; // @TODO Stop using a discount adjustment for removed included tax and instead modify the item price directly #COM-26
                $adjustment->included = false;

                $adjustments[] = $adjustment;
            }

            // Not an order level taxable, add tax adjustments to the line items.
            if (!in_array($taxRate->taxable, TaxRateRecord::ORDER_TAXABALES, false)) {
                // Not an order level taxable, add tax adjustments to the line items.
                foreach ($this->_order->getLineItems() as $item) {
                    if ($item->taxCategoryId == $taxRate->taxCategoryId) {
                        if ($taxRate->taxable == TaxRateRecord::TAXABLE_PURCHASABLE) {
                            // taxableAmount = salePrice - (discount / qty)
                            $taxableAmount = $teller->subtract(
                                $item->salePrice,
                                $teller->divide(
                                    $item->getDiscount(), // float amount of discount
                                    $item->qty
                                )
                            );

                            // amount = taxableAmount - (taxableAmount / (1 + taxRate))
                            $amount = $teller->subtract(
                                $taxableAmount,
                                $teller->divide(
                                    $taxableAmount,
                                    (1 + $taxRate->rate)
                                )
                            );

                            $amount = -(float)$teller->multiply($amount, $item->qty);
                        } else {
                            $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                            // amount = taxableAmount - (taxableAmount / (1 + taxRate))
                            $amount = $teller->subtract(
                                $taxableAmount,
                                $teller->divide(
                                    $taxableAmount,
                                    (1 + $taxRate->rate)
                                )
                            );

                            $amount = -(float)$amount;
                        }
                        $adjustment = $this->_createAdjustment($taxRate);
                        // We need to display the adjustment that removed the included tax
                        $adjustment->name = t($taxRate->name, category: 'site') . ' ' . t('Removed', category: 'commerce');
                        $adjustment->amount = $amount;
                        $adjustment->setLineItem($item);
                        $adjustment->type = 'discount';
                        $adjustment->included = false;

                        $objectId = spl_object_hash($item); // We use this ID since some line items are not saved in the DB yet and have no ID.

                        if (isset($this->_costRemovedByLineItem[$objectId])) {
                            $this->_costRemovedByLineItem[$objectId] = (float)$this->_getTeller()->add($this->_costRemovedByLineItem[$objectId], $amount);
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

        if (!$zoneMatches || ($taxRate->hasTaxIdValidators() && $hasValidTaxId)) {
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
                $orderTaxableAmount = (float)$this->_getTeller()->add($orderTaxableAmount, $this->_costRemovedForOrderTotalPrice);
            }

            if ($taxRate->taxable === TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING) {
                $orderTaxableAmount = $this->_order->getTotalShippingCost();
                $orderTaxableAmount = (float)$this->_getTeller()->add($orderTaxableAmount, $this->_costRemovedForOrderShipping);
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
                    $purchasableAmount = $this->_getTeller()->subtract(
                        $item->salePrice,
                        $this->_getTeller()->divide(
                            $item->getDiscount(),
                            $item->qty
                        )
                    );

                    $purchasableAmount = $this->_getTeller()->add(
                        $purchasableAmount,
                        $this->_getTeller()->divide(
                            ($this->_costRemovedByLineItem[$objectId] ?? 0),
                            $item->qty
                        )
                    );
                    $purchasableTax = $this->_getTaxAmount((float)$purchasableAmount, $taxRate->rate, $taxRate->include);
                    $itemTax = $this->_getTeller()->multiply($purchasableTax, $item->qty); //already rounded
                } else {
                    $taxableAmount = $item->getTaxableSubtotal($taxRate->taxable);
                    $taxableAmount = (float)$this->_getTeller()->add(
                        $taxableAmount,
                        $this->_costRemovedByLineItem[$objectId] ?? 0
                    );
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
     * @throws StoreNotFoundException
     * @throws InvalidConfigException
     */
    protected function getTaxRates(?int $storeId = null): Collection
    {
        return Plugin::getInstance()->getTaxRates()->getAllEnabledTaxRates($storeId);
    }

    private function _getTaxAmount($taxableAmount, $rate, $included): float
    {
        $teller = $this->_getTeller();
        if (!$included) {
            $incTax = $teller->multiply($taxableAmount, (1 + $rate));
            $tax = $teller->subtract($incTax, $taxableAmount);
        } else {
            $exTax = $teller->divide($taxableAmount, (1 + $rate));
            $tax = $teller->subtract($taxableAmount, $exTax);
        }

        return (float)$tax;
    }

    private function _matchAddress(TaxAddressZone $zone): bool
    {
        //when having no address check default tax zones only
        if (!$this->_address) {
            return $zone->default;
        }

        return $zone->getCondition()->matchElement($this->_address);
    }

    private function organizationTaxIdIsValidTaxId(array $validators): bool
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

        $validOrganizationTaxId = Cache::has('commerce:validVatId:' . $this->_address->organizationTaxId);

        // If we do not have a valid VAT ID in cache, see if we can get one from the API
        if (!$validOrganizationTaxId) {
            $validOrganizationTaxId = $this->validateTaxIdNumber($this->_address->organizationTaxId, $validators);
        }

        if ($validOrganizationTaxId) {
            Cache::forever('commerce:validVatId:' . $this->_address->organizationTaxId, '1');
            return true;
        }

        Cache::forget('commerce:validVatId:' . $this->_address->organizationTaxId);
        return false;
    }

    #[\Deprecated(message: 'in 5.3.0. Use `validateTaxIdNumber()` instead, passing the validators you want to check the ID with.')]
    protected function validateVatNumber(string $businessVatId): bool
    {
        $oldValidator = [new EuVatIdValidator()];
        return $this->validateTaxIdNumber($businessVatId, $oldValidator);
    }

    /**
     * @param TaxIdValidatorInterface[] $validators
     */
    protected function validateTaxIdNumber(string $organizationTaxId, array $validators = []): bool
    {
        try {
            foreach ($validators as $validator) {
                if ($validator->validate($organizationTaxId)) {
                    return true;
                }
            }
        } catch (Exception $e) {
            Log::error('Communication with VAT API failed: ' . $e->getMessage());

            return false;
        }

        return false;
    }

    private function _createAdjustment(TaxRate $rate): OrderAdjustment
    {
        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = t($rate->name, category: 'site');
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

        $totals = (float)$this->_getTeller()->add($itemTotal, $allNonIncludedAdjustmentsTotal);
        $adjustments = (float)$this->_getTeller()->add($taxAdjustments, $includedTaxAdjustments);

        return (float)$this->_getTeller()->subtract(
            $totals,
            $adjustments
        );
    }

    private function _getTaxAddress(): ?Address
    {
        $this->_isEstimated = false;
        if (!$this->_order->getStore()->getUseBillingAddressForTax()) {
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

    /**
     * @throws InvalidConfigException
     */
    private function _getTeller(): Teller
    {
        return Plugin::getInstance()->getCurrencies()->getTeller($this->_order->currency);
    }
}
