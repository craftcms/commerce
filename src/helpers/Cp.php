<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\helpers\Cp as CraftCp;

/**
 * Class Commerce Cp
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class Cp
{
    /**
     * Renders an inventory locations select field’s HTML.
     *
     * @param array $config
     * @return string
     * @since 5.0.0
     */
    public static function inventoryLocationFieldHtml(array $config): string
    {
        $config['id'] ??= 'inventorylocationselect' . mt_rand();
        return CraftCp::fieldHtml('template:commerce/_includes/forms/inventoryLocationSelect.twig', $config);
    }
}
