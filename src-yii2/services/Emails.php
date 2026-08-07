<?php

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Email\Models\Email;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\Emails::class)` instead.
 */
class Emails extends Component
{
    public const EVENT_BEFORE_SEND_MAIL = \CraftCms\Commerce\Services\Emails::EVENT_BEFORE_SEND_MAIL;

    public const EVENT_AFTER_SEND_MAIL = \CraftCms\Commerce\Services\Emails::EVENT_AFTER_SEND_MAIL;

    public const EVENT_BEFORE_SAVE_EMAIL = \CraftCms\Commerce\Services\Emails::EVENT_BEFORE_SAVE_EMAIL;

    public const EVENT_AFTER_SAVE_EMAIL = \CraftCms\Commerce\Services\Emails::EVENT_AFTER_SAVE_EMAIL;

    public const EVENT_BEFORE_DELETE_EMAIL = \CraftCms\Commerce\Services\Emails::EVENT_BEFORE_DELETE_EMAIL;

    public const EVENT_AFTER_DELETE_EMAIL = \CraftCms\Commerce\Services\Emails::EVENT_AFTER_DELETE_EMAIL;

    public const CONFIG_EMAILS_KEY = \CraftCms\Commerce\Services\Emails::CONFIG_EMAILS_KEY;

    public function getEmailById(int $id, ?int $storeId = null): ?Email
    {
        return app(\CraftCms\Commerce\Services\Emails::class)->getEmailById($id, $storeId);
    }

    /**
     * @return Collection<int, Email>
     */
    public function getAllEmails(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\Emails::class)->getAllEmails($storeId);
    }

    /**
     * @return Collection<int, Email>
     */
    public function getAllEnabledEmails(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\Emails::class)->getAllEnabledEmails($storeId);
    }

    public function saveEmail(Email $email, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\Emails::class)->saveEmail($email, $runValidation);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleChangedEmail(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Emails::class)->handleChangedEmail($event);
    }

    public function deleteEmailById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\Emails::class)->deleteEmailById($id);
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedEmail(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Services\Emails::class)->handleDeletedEmail($event);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function sendEmail(Email $email, Order $order, ?OrderHistory $orderHistory = null, ?array $orderData = null, string &$error = ''): bool
    {
        return app(\CraftCms\Commerce\Services\Emails::class)->sendEmail($email, $order, $orderHistory, $orderData, $error);
    }

    /**
     * @return Email[]
     */
    public function getAllEmailsByOrderStatusId(int $id): array
    {
        return app(\CraftCms\Commerce\Services\Emails::class)->getAllEmailsByOrderStatusId($id);
    }
}
