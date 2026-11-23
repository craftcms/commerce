<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Activity;

use CraftCms\Cms\Activity\Data\ActivityActor;
use CraftCms\Cms\Activity\Models\ActivityEvent;
use CraftCms\Cms\User\Contracts\CraftUser;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;

use function CraftCms\Cms\t;

/**
 * A payment, authorization, capture or refund on an order, whether a gateway took it or staff recorded it by hand.
 *
 * @since 6.0.0
 */
class OrderTransactionSaved extends OrderActivityEventType
{
    protected const string LABEL = 'Transaction';

    protected const string ICON = 'credit-card';

    /**
     * @param array{transactionId: int|null, type: string, status: string, amount: string, manual: bool, gateway: string|null, reference: string|null, message: string|null} $details
     */
    public function __construct(
        Order $subject,
        private readonly array $details,
        CraftUser|ActivityActor|null $actor = null,
    ) {
        parent::__construct(subject: $subject, actor: $actor);
    }

    public function data(): array
    {
        return $this->details;
    }

    public static function format(ActivityEvent $event): string
    {
        $data = $event->data;
        $params = [
            'amount' => $data['amount'],
            'source' => $data['gateway'] ?: t('Gateway', category: 'commerce'),
        ];

        $text = match (true) {
            $data['status'] === TransactionRecord::STATUS_FAILED => t('A {amount} {type} through {source} failed', [...$params, 'type' => self::typeLabel($data['type'])], category: 'commerce'),
            $data['manual'] && $data['type'] === TransactionRecord::TYPE_REFUND => t('Recorded a {amount} refund', $params, category: 'commerce'),
            $data['manual'] => t('Recorded a {amount} payment', $params, category: 'commerce'),
            $data['type'] === TransactionRecord::TYPE_REFUND => t('Refunded {amount} through {source}', $params, category: 'commerce'),
            $data['type'] === TransactionRecord::TYPE_CAPTURE => t('Captured {amount} through {source}', $params, category: 'commerce'),
            $data['type'] === TransactionRecord::TYPE_AUTHORIZE => t('Authorized {amount} through {source}', $params, category: 'commerce'),
            default => t('Received a {amount} payment through {source}', $params, category: 'commerce'),
        };

        $detail = array_filter([$data['reference'] ? t('Reference: {reference}', ['reference' => $data['reference']], category: 'commerce') : null]);

        return implode(' · ', [$text, ...$detail]);
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            TransactionRecord::TYPE_REFUND => t('refund', category: 'commerce'),
            TransactionRecord::TYPE_CAPTURE => t('capture', category: 'commerce'),
            TransactionRecord::TYPE_AUTHORIZE => t('authorization', category: 'commerce'),
            default => t('payment', category: 'commerce'),
        };
    }
}
