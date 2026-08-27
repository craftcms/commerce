<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Data\OrderHistory;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Email\Data\Email;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Email\Emails::class)` instead.
 */
class Emails extends Component
{
    public const EVENT_BEFORE_SEND_MAIL = \CraftCms\Commerce\Email\Emails::EVENT_BEFORE_SEND_MAIL;

    public const EVENT_AFTER_SEND_MAIL = \CraftCms\Commerce\Email\Emails::EVENT_AFTER_SEND_MAIL;

    public const EVENT_BEFORE_SAVE_EMAIL = \CraftCms\Commerce\Email\Emails::EVENT_BEFORE_SAVE_EMAIL;

    public const EVENT_AFTER_SAVE_EMAIL = \CraftCms\Commerce\Email\Emails::EVENT_AFTER_SAVE_EMAIL;

    public const EVENT_BEFORE_DELETE_EMAIL = \CraftCms\Commerce\Email\Emails::EVENT_BEFORE_DELETE_EMAIL;

    public const EVENT_AFTER_DELETE_EMAIL = \CraftCms\Commerce\Email\Emails::EVENT_AFTER_DELETE_EMAIL;

    public const CONFIG_EMAILS_KEY = \CraftCms\Commerce\Email\Emails::CONFIG_EMAILS_KEY;

    public function getEmailById(int $id, ?int $storeId = null): ?Email
    {
        return app(\CraftCms\Commerce\Email\Emails::class)->getEmailById($id, $storeId);
    }

    /**
     * @return Collection<int, Email>
     */
    public function getAllEmails(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Email\Emails::class)->getAllEmails($storeId);
    }

    /**
     * @return Collection<int, Email>
     */
    public function getAllEnabledEmails(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Email\Emails::class)->getAllEnabledEmails($storeId);
    }

    public function saveEmail(Email $email, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Email\Emails::class)->saveEmail($email, $runValidation);
    }

    /**
     * @throws Throwable if reasons
     */
    public function handleChangedEmail(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Email\Emails::class)->handleChangedEmail($event);
    }

    public function deleteEmailById(int $id): bool
    {
        return app(\CraftCms\Commerce\Email\Emails::class)->deleteEmailById($id);
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedEmail(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Email\Emails::class)->handleDeletedEmail($event);
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function sendEmail(Email $email, Order $order, ?OrderHistory $orderHistory = null, ?array $orderData = null, string &$error = ''): bool
    {
        return app(\CraftCms\Commerce\Email\Emails::class)->sendEmail($email, $order, $orderHistory, $orderData, $error);
    }

    /**
     * @return Email[]
     */
    public function getAllEmailsByOrderStatusId(int $id): array
    {
        return app(\CraftCms\Commerce\Email\Emails::class)->getAllEmailsByOrderStatusId($id);
    }
}
