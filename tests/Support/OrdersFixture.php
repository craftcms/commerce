<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Catalog\Data\ProductTypeSite;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Shipping\Data\ShippingMethod;
use CraftCms\Commerce\Shipping\Data\ShippingRule;
use CraftCms\Commerce\Shipping\ShippingMethods;
use CraftCms\Commerce\Shipping\ShippingRules;
use CraftCms\Commerce\Store\Stores;
use DateTime;
use RuntimeException;

/**
 * Builds the same order graph as the legacy `tests-yii2/fixtures/OrdersFixture.php` +
 * `data/orders.php` (customer1@crafttest.com, two Hypercolor T-Shirt variants, three completed
 * orders — one from "yesterday", two from "today") so the ported stat tests can assert against
 * the same numbers the legacy Codeception tests did.
 */
class OrdersFixture
{
    public User $customer;

    public Product $product;

    public Variant $white;

    public Variant $blue;

    public int $storeId;

    public int $shippedOrderStatusId;

    /** @var array<string, Order> */
    public array $orders = [];

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $site = Sites::getCurrentSite();
        $this->storeId = app(Stores::class)->getPrimaryStore()->id;

        $customer = new User();
        $customer->username = 'customer1';
        $customer->email = 'customer1@crafttest.com';
        $customer->active = true;
        if (!Elements::saveElement($customer)) {
            throw new RuntimeException('Could not save customer: ' . json_encode($customer->errors()->all()));
        }
        $this->customer = $customer;

        $shippingMethod = new ShippingMethod();
        $shippingMethod->name = 'US Shipping';
        $shippingMethod->handle = 'usShipping';
        $shippingMethod->storeId = $this->storeId;
        $shippingMethod->enabled = true;
        if (!app(ShippingMethods::class)->saveShippingMethod($shippingMethod)) {
            throw new RuntimeException('Could not save shipping method: ' . json_encode($shippingMethod->errors()->all()));
        }

        // No zone on the rule means it matches every address; no rates set means $0 shipping.
        $shippingRule = new ShippingRule();
        $shippingRule->name = 'US Shipping';
        $shippingRule->methodId = $shippingMethod->id;
        $shippingRule->enabled = true;
        $shippingRule->priority = 0;
        if (!app(ShippingRules::class)->saveShippingRule($shippingRule)) {
            throw new RuntimeException('Could not save shipping rule: ' . json_encode($shippingRule->errors()->all()));
        }

        $shippedStatus = new OrderStatus();
        $shippedStatus->name = 'Shipped';
        $shippedStatus->handle = 'shipped';
        $shippedStatus->color = 'purple';
        $shippedStatus->storeId = $this->storeId;
        if (!app(OrderStatuses::class)->saveOrderStatus($shippedStatus)) {
            throw new RuntimeException('Could not save order status: ' . json_encode($shippedStatus->errors()->all()));
        }
        $this->shippedOrderStatusId = $shippedStatus->id;

        $productType = new ProductType();
        $productType->name = 'T-Shirts';
        $productType->handle = 'tShirts';
        $productType->hasVariantTitleField = false;
        $productType->variantTitleFormat = '{product.title}';

        $siteSettings = new ProductTypeSite();
        $siteSettings->siteId = $site->id;
        $siteSettings->hasUrls = false;
        $siteSettings->enabledByDefault = true;
        $productType->setSiteSettings([$site->id => $siteSettings]);

        if (!app(ProductTypes::class)->saveProductType($productType)) {
            throw new RuntimeException('Could not save product type: ' . json_encode($productType->errors()->all()));
        }

        $product = new Product();
        $product->typeId = $productType->id;
        $product->title = 'Hypercolor T-Shirt';
        $product->enabled = true;
        $product->siteId = $site->id;
        if (!Elements::saveElement($product)) {
            throw new RuntimeException('Could not save product: ' . json_encode($product->errors()->all()));
        }
        $this->product = $product;

        $white = new Variant();
        $white->title = 'White';
        $white->setPrimaryOwner($product);
        $white->setSku('hct-white');
        $white->setBasePrice(19.99);
        $white->isDefault = true;
        $white->siteId = $site->id;
        if (!Elements::saveElement($white)) {
            throw new RuntimeException('Could not save white variant: ' . json_encode($white->errors()->all()));
        }

        $blue = new Variant();
        $blue->title = 'Blue';
        $blue->setPrimaryOwner($product);
        $blue->setSku('hct-blue');
        $blue->setBasePrice(21.99);
        $blue->siteId = $site->id;
        if (!Elements::saveElement($blue)) {
            throw new RuntimeException('Could not save blue variant: ' . json_encode($blue->errors()->all()));
        }

        // Re-query fresh (rather than reuse the in-memory instances) so each variant's owner
        // lookup round-trips through the database, same as it would for a real request.
        $this->white = Variant::find()->id($white->id)->one();
        $this->blue = Variant::find()->id($blue->id)->one();

        // Match `Stat`'s own date-range resolution (`new DateTime()`, no explicit timezone) so
        // "today"/"yesterday" here line up with what the stat queries consider "today", whatever
        // the environment's default timezone is.
        $yesterday = new DateTime('now');
        $yesterday->modify('-1 day');
        $yesterday->setTime(23, 59, 59);

        $apple = [
            'firstName' => 'Tim', 'lastName' => 'Cook',
            'addressLine1' => 'One Apple Park Way', 'locality' => 'Cupertino',
            'postalCode' => '95014', 'countryCode' => 'US', 'administrativeArea' => 'CA',
        ];
        $bttf = [
            'firstName' => 'Emmett', 'lastName' => 'Brown',
            'addressLine1' => '1640 Riverside Drive', 'locality' => 'Hill Valley',
            'postalCode' => '88', 'countryCode' => 'US', 'administrativeArea' => 'CA',
        ];
        $bob = [
            'firstName' => 'Bob', 'lastName' => 'Belcher',
            'addressLine1' => '101 Ocean Avenue', 'locality' => 'Long Island',
            'postalCode' => '12345', 'countryCode' => 'US', 'administrativeArea' => 'NY',
        ];

        $this->orders['completed-new'] = $this->createOrder(
            lineItems: [[$this->white->id, 1], [$this->blue->id, 4]],
            billingAddress: $apple,
            shippingAddress: $apple,
            shippingMethodHandle: 'usShipping',
        );

        $this->orders['completed-new-past'] = $this->createOrder(
            lineItems: [[$this->white->id, 1], [$this->blue->id, 4]],
            billingAddress: $bttf,
            shippingAddress: $bttf,
            dateOrdered: $yesterday,
        );

        $this->orders['completed-shipped'] = $this->createOrder(
            lineItems: [[$this->white->id, 1]],
            billingAddress: $bttf,
            shippingAddress: $bob,
            orderStatusId: $this->shippedOrderStatusId,
        );
    }

    /** @param array<int, array{0: int, 1: int}> $lineItems */
    private function createOrder(
        array $lineItems,
        array $billingAddress,
        array $shippingAddress,
        ?string $shippingMethodHandle = null,
        ?DateTime $dateOrdered = null,
        ?int $orderStatusId = null,
    ): Order {
        $order = new Order();
        $order->number = bin2hex(random_bytes(16));
        $order->storeId = $this->storeId;
        $order->setCustomerId($this->customer->id);

        if ($shippingMethodHandle) {
            $order->shippingMethodHandle = $shippingMethodHandle;
        }

        if ($orderStatusId) {
            $order->orderStatusId = $orderStatusId;
        }

        if (!Elements::saveElement($order, false)) {
            throw new RuntimeException('Could not save order: ' . json_encode($order->errors()->all()));
        }

        $items = [];
        foreach ($lineItems as [$purchasableId, $qty]) {
            $items[] = app(LineItems::class)->create($order, [
                'purchasableId' => $purchasableId,
                'qty' => $qty,
            ]);
        }
        $order->setLineItems($items);
        $order->setBillingAddress($billingAddress);
        $order->setShippingAddress($shippingAddress);

        if (!Elements::saveElement($order, false)) {
            throw new RuntimeException('Could not re-save order: ' . json_encode($order->errors()->all()));
        }

        if (!$order->markAsComplete()) {
            throw new RuntimeException('Could not complete order: ' . json_encode($order->errors()->all()));
        }

        if ($dateOrdered) {
            $order->dateOrdered = $dateOrdered;
            if (!Elements::saveElement($order, false)) {
                throw new RuntimeException('Could not update dateOrdered: ' . json_encode($order->errors()->all()));
            }
        }

        return Order::find()->id($order->id)->one();
    }
}
