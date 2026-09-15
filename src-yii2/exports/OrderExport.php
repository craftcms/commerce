<?php

namespace craft\commerce\exports;

/** @deprecated use {@see \CraftCms\Commerce\Order\Exporters\OrderExport} */
class_alias(\CraftCms\Commerce\Order\Exporters\OrderExport::class, 'craft\commerce\exports\OrderExport');

/** @phpstan-ignore-next-line */
if (false) {
    class OrderExport extends \CraftCms\Commerce\Order\Exporters\OrderExport {}
}
