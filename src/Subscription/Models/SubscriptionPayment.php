<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Subscription\Models;

use CraftCms\Cms\Component\Component;
use DateTime;
use Money\Currency;

class SubscriptionPayment extends Component
{
    public float $paymentAmount;

    public Currency $paymentCurrency;

    public DateTime $paymentDate;

    public string $paymentReference;

    public bool $paid = false;

    public string $response;
}
