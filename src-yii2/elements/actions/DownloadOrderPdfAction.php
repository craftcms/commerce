<?php

namespace craft\commerce\elements\actions;

/** @deprecated use {@see \CraftCms\Commerce\Order\Actions\DownloadOrderPdfAction} */
class_alias(\CraftCms\Commerce\Order\Actions\DownloadOrderPdfAction::class, 'craft\commerce\elements\actions\DownloadOrderPdfAction');

/** @phpstan-ignore-next-line */
if (false) {
    class DownloadOrderPdfAction extends \CraftCms\Commerce\Order\Actions\DownloadOrderPdfAction {}
}
