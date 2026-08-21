<?php

namespace craft\commerce\exports;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exporters\LineItemExport} */
class_alias(\CraftCms\Commerce\Order\Exporters\LineItemExport::class, 'craft\commerce\exports\LineItemExport');

/** @phpstan-ignore-next-line */
if (false) {
    class LineItemExport extends \CraftCms\Commerce\Order\Exporters\LineItemExport {}
}
