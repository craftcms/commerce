<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Data;

use DateTimeInterface;

/**
 * A hosted payment page a gateway created for a pending transaction, for a customer to pay on.
 *
 * @since 6.0.0
 */
readonly class CollectPaymentLink
{
    public function __construct(
        public string $url,
        public ?string $reference = null,
        public ?DateTimeInterface $expiresAt = null,
    ) {
    }
}
