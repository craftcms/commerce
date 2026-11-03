<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\elements;

use craft\commerce\gql\interfaces\elements\Donation as DonationInterface;
use craft\gql\types\elements\Element as ElementType;

/**
 * Class Donation
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Donation extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            DonationInterface::getType(),
        ];

        parent::__construct($config);
    }
}
