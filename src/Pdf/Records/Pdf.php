<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Pdf\Records;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

/**
 * Thin Eloquent persistence model for the `commerce_pdfs` table.
 *
 * This holds no business logic — it's used internally by {@see \CraftCms\Commerce\Pdf\Pdfs} to
 * read/write rows, which are then hydrated into (or persisted from) the business
 * {@see \CraftCms\Commerce\Pdf\Models\Pdf} object that the rest of the codebase actually works
 * with.
 *
 * The legacy `craft\commerce\records\Pdf` stays alongside this class — it's still `use`d by
 * `StoreRecordTrait`, which several other still-legacy records depend on — this class only
 * replaces `src/`'s own usage.
 */
class Pdf extends BaseModel
{
    public const string LOCALE_ORDER_LANGUAGE = 'orderLanguage';

    public const string PAPER_ORIENTATION_PORTRAIT = 'portrait';

    public const string PAPER_ORIENTATION_LANDSCAPE = 'landscape';

    #[\Override]
    protected $table = Table::PDFS;

    #[\Override]
    protected $casts = [
        'storeId' => 'integer',
        'enabled' => 'boolean',
        'isDefault' => 'boolean',
        'linkExpiry' => 'integer',
        'sortOrder' => 'integer',
    ];
}
