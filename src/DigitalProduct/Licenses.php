<?php

declare(strict_types=1);

namespace CraftCms\Commerce\DigitalProduct;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Events\UserActivated;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use CraftCms\Commerce\DigitalProduct\Elements\License;
use CraftCms\Commerce\DigitalProduct\Events\LicenseKeyGenerating;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\OrderCompleted;
use CraftCms\Commerce\Order\Events\OrderPaid;
use CraftCms\Commerce\Payment\Events\PaymentProcessing;
use CraftCms\Commerce\Plugin;
use Illuminate\Container\Attributes\Singleton;

use function CraftCms\Cms\currentUser;

/**
 * Issues licenses for purchased digital products.
 */
#[Singleton]
class Licenses
{
    /**
     * Generates a unique license key for a new license. A `LicenseKeyGenerating` listener may provide the key instead.
     */
    public function generateLicenseKey(License $license): string
    {
        event($event = new LicenseKeyGenerating($license));

        if ($event->licenseKey !== null) {
            return $event->licenseKey;
        }

        $settings = app(Plugin::class)->getSettings();
        $alphabet = $settings->licenseKeyCharacters;
        $maxIndex = strlen($alphabet) - 1;

        do {
            $licenseKey = '';
            for ($i = 0; $i < $settings->licenseKeyLength; $i++) {
                $licenseKey .= $alphabet[random_int(0, $maxIndex)];
            }
        } while (!$this->isLicenseKeyUnique($licenseKey));

        return $licenseKey;
    }

    public function isLicenseKeyUnique(string $licenseKey): bool
    {
        return !License::find()
            ->licenseKey($licenseKey)
            ->status(null)
            ->exists();
    }

    /**
     * Issues one license per unit of each digital product in the order.
     */
    public function licenseOrder(Order $order): void
    {
        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();

            if (!$purchasable instanceof DigitalProduct) {
                continue;
            }

            for ($i = 0; $i < $lineItem->qty; $i++) {
                $this->licenseProductByOrder($purchasable, $order);
            }
        }
    }

    /**
     * Issues a license for a digital product to the order's customer.
     */
    public function licenseProductByOrder(DigitalProduct $product, Order $order): bool
    {
        $customer = $order->getCustomer();

        if (!$customer) {
            return false;
        }

        $license = new License();
        $license->productId = $product->id;
        $license->orderId = $order->id;
        $license->ownerEmail = $customer->email;
        $license->ownerName = $customer->getName();
        $license->userId = $customer->id;

        return Elements::saveElement($license);
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        if (app(DigitalProducts::class)->isEnabled() && app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid) {
            $this->licenseOrder($event->order);
        }
    }

    public function handleOrderCompleted(OrderCompleted $event): void
    {
        if (app(DigitalProducts::class)->isEnabled() && !app(Plugin::class)->getSettings()->generateLicenseOnOrderPaid) {
            $this->licenseOrder($event->order);
        }
    }

    /**
     * Blocks guests from paying for orders that contain digital products, when logged-in users are required.
     */
    public function handlePaymentProcessing(PaymentProcessing $event): void
    {
        if (
            !app(DigitalProducts::class)->isEnabled()
            || !app(Plugin::class)->getSettings()->requireLoggedInUserForDigitalProducts
            || currentUser() !== null
        ) {
            return;
        }

        foreach ($event->order->getLineItems() as $lineItem) {
            if ($lineItem->getPurchasable() instanceof DigitalProduct) {
                $event->isValid = false;

                return;
            }
        }
    }

    /**
     * Assigns unassigned licenses to a newly activated user whose email matches.
     */
    public function handleUserActivated(UserActivated $event): void
    {
        if (!app(DigitalProducts::class)->isEnabled() || !app(Plugin::class)->getSettings()->autoAssignLicensesOnUserRegistration) {
            return;
        }

        /** @var License[] $licenses */
        $licenses = License::find()
            ->ownerEmail($event->user->email)
            ->ownerId(':empty:')
            ->status(null)
            ->all();

        foreach ($licenses as $license) {
            $license->userId = $event->user->id;
            Elements::saveElement($license);
        }
    }
}
