<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\gql\types\elements;

use craft\commerce\gql\interfaces\elements\Order as OrderInterface;
use craft\gql\types\elements\Element as ElementType;

/**
 * Class Order
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class Order extends ElementType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            OrderInterface::getType(),
        ];

        parent::__construct($config);
    }
}
