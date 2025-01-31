<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\TaxIdValidatorInterface;
use yii\base\Event;

/**
 * Class TaxIdValidatorsEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.8.0
 */
class TaxIdValidatorsEvent extends Event
{
    /**
     * @var TaxIdValidatorInterface[] Holds the registered tax ID validators.
     */
    public array $validators = [];
}
