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
     * Renders an inventory locations select field's HTML.
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

    /**
     * Renders a tax zone select field's HTML.
     *
     * @param array $config
     * @return string
     * @since 5.0.0
     */
    public static function taxZoneFieldHtml(array $config): string
    {
        $config['id'] ??= 'taxzoneselect' . mt_rand();
        return CraftCp::fieldHtml('template:commerce/_includes/forms/taxZoneSelect.twig', $config);
    }

    /**
     * Renders a tax category select field's HTML.
     *
     * @param array $config
     * @return string
     * @since 5.0.0
     */
    public static function taxCategoryFieldHtml(array $config): string
    {
        $config['id'] ??= 'taxcategoryselect' . mt_rand();
        return CraftCp::fieldHtml('template:commerce/_includes/forms/taxCategorySelect.twig', $config);
    }
}
