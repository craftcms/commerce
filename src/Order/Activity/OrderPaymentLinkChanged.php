<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Activity;

use CraftCms\Cms\Activity\Data\ActivityActor;
use CraftCms\Cms\Activity\Models\ActivityEvent;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Order\Elements\Order;

use function CraftCms\Cms\t;

/**
 * A hosted payment link was created for an order, emailed to the customer, or expired.
 *
 * @since 6.0.0
 */
class OrderPaymentLinkChanged extends OrderActivityEventType
{
    public const string CREATED = 'created';

    public const string EMAILED = 'emailed';

    public const string EXPIRED = 'expired';

    protected const string LABEL = 'Payment link';

    protected const string ICON = 'link';

    public function __construct(
        Order $subject,
        private readonly string $action,
        private readonly string $amount,
        private readonly ?string $gateway,
        private readonly ?string $email = null,
        CraftUser|ActivityActor|null $actor = null,
    ) {
        parent::__construct(subject: $subject, actor: $actor);
    }

    public function data(): array
    {
        return [
            'action' => $this->action,
            'amount' => $this->amount,
            'gateway' => $this->gateway,
            'email' => $this->email,
        ];
    }

    public static function format(ActivityEvent $event): string
    {
        $params = [
            'amount' => $event->data['amount'],
            'gateway' => $event->data['gateway'] ?? t('the gateway', category: 'commerce'),
            'email' => $event->data['email'],
        ];

        return match ($event->data['action']) {
            self::EMAILED => t('Emailed a {amount} payment link with the “{email}” email', $params, category: 'commerce'),
            self::EXPIRED => t('A {amount} payment link through {gateway} expired', $params, category: 'commerce'),
            default => t('Created a {amount} payment link through {gateway}', $params, category: 'commerce'),
        };
    }
}
