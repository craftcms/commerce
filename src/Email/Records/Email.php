<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Email\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_emails` table.
 *
 * This holds no business logic — it's used internally by
 * {@see \CraftCms\Commerce\Email\Emails} to read/write rows, which are then hydrated into (or
 * persisted from) the business {@see \CraftCms\Commerce\Email\Models\Email} object that the
 * rest of the codebase actually works with.
 *
 * The legacy `craft\commerce\records\Email` stays alongside this class — it's still `use`d by
 * `StoreRecordTrait`, which several other still-legacy records depend on — this class only
 * replaces `src/`'s own usage.
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
