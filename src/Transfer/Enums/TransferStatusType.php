<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Enums;

use CraftCms\Commerce\Base\EnumHelpersTrait;

use function CraftCms\Cms\t;

enum TransferStatusType: string
{
    use EnumHelpersTrait;

    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case RECEIVED = 'received';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => t('Draft', category: 'commerce'),
            self::PENDING => t('Pending', category: 'commerce'),
            self::PARTIAL => t('Partial', category: 'commerce'),
            self::RECEIVED => t('Received', category: 'commerce'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'blue',
            self::PENDING => 'yellow',
            self::PARTIAL => 'orange',
            self::RECEIVED => 'green',
        };
    }
}
