<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Store\Models\SiteStore} */
class_alias(\CraftCms\Commerce\Store\Models\SiteStore::class, 'craft\commerce\models\SiteStore');

/** @phpstan-ignore-next-line */
if (false) {
    class SiteStore extends \CraftCms\Commerce\Store\Models\SiteStore {}
}
