<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\Gateway;
use yii\base\Event;
use yii\web\Response;

/**
 * Class WebhookEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.9
 */
class WebhookEvent extends Event
{
    /**
     * @var Gateway
     */
    public Gateway $gateway;

    /**
     * @var Response
     */
    public Response $response;
}
