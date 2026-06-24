<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\GatewayInterface;
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
     * @var GatewayInterface
     */
    public GatewayInterface $gateway;

    /**
     * @var Response
     */
    public Response $response;
}
