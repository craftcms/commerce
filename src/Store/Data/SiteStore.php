<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Data;

use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Store\Concerns\StoreTrait;
use CraftCms\Commerce\Store\Contracts\HasStoreInterface;
use Illuminate\Support\Facades\DB;

class SiteStore extends Component implements HasStoreInterface
{
    use StoreTrait;

    public int $siteId;

    public ?string $uid = null;

    public function getSite(): ?Site
    {
        return Sites::getSiteById($this->siteId);
    }

    public function getStoreUid(): ?string
    {
        if (!$this->storeId) {
            return null;
        }

        return DB::table(Table::STORES)->uidById($this->storeId) ?: null;
    }

    public function getConfig(): array
    {
        return [
            'store' => $this->getStoreUid(),
        ];
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'storeId' => ['required', 'integer'],
            'siteId' => ['required', 'integer'],
        ];
    }
}
