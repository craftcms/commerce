<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \CraftCms\Commerce\Email\Data\Email|null getEmailById(int $id, int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllEmails(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllEnabledEmails(int|null $storeId = null)
 * @method static bool saveEmail(\CraftCms\Commerce\Email\Data\Email $email, bool $runValidation = true)
 * @method static void handleChangedEmail(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool deleteEmailById(int $id)
 * @method static void handleDeletedEmail(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool sendEmail(\CraftCms\Commerce\Email\Data\Email $email, \CraftCms\Commerce\Order\Elements\Order $order, \CraftCms\Commerce\Order\Data\OrderHistory|null $orderHistory = null, array|null $orderData = null, string $error = '')
 * @method static \CraftCms\Commerce\Email\Data\Email[] getAllEmailsByOrderStatusId(int $id)
 *
 * @see \CraftCms\Commerce\Email\Emails
 */
class Emails extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Email\Emails::class;
    }
}
