<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_emails` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Email\Emails} to read/write rows, which are then hydrated into (or
 * persisted from) the business {@see \CraftCms\Commerce\Email\Data\Email} object that the
 * rest of the codebase actually works with.
 */
class Email extends BaseModel
{
    public const string LOCALE_ORDER_LANGUAGE = 'orderLanguage';

    public const string TYPE_CUSTOMER = 'customer';

    public const string TYPE_CUSTOM = 'custom';

    #[\Override]
    protected $table = Table::EMAILS;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'renderSiteId' => 'integer',
        'pdfId' => 'integer',
        'enabled' => 'boolean',
    ];
}
