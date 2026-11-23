<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\CollectPaymentLink;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use DateTimeInterface;
use Override;

/**
 * A Dummy gateway that can create hosted payment links and has a virtual terminal.
 */
class LinkGateway extends Dummy
{
    /**
     * @var list<string>
     */
    public static array $expired = [];

    #[Override]
    public function supportsCollectPaymentLink(): bool
    {
        return true;
    }

    #[Override]
    public function createCollectPaymentLink(Transaction $transaction, Order $order, DateTimeInterface $expiresAt): CollectPaymentLink
    {
        return new CollectPaymentLink('https://pay.example.test/' . $transaction->hash, 'link_' . $transaction->hash, $expiresAt);
    }

    #[Override]
    public function expireCollectPaymentLink(Transaction $transaction): bool
    {
        self::$expired[] = (string)$transaction->reference;

        return true;
    }

    #[Override]
    public function getVirtualTerminalUrl(Order $order): ?string
    {
        return 'https://terminal.example.test/?ref=' . $order->number;
    }
}
