<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\stats;

use craft\commerce\base\Stat;
use yii\db\Expression;

/**
 * Average Order Total Stat
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class AverageOrderTotal extends Stat
{
    /**
     * @inheritdoc
     */
    protected $_handle = 'averageOrderTotal';

    /**
     * @inheritdoc
     */
    public $cache = true;

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $query = $this->_createStatQuery();
        $query->select([new Expression('SUM([[total]]) / COUNT([[id]]) as averageOrderTotal')]);

        return $query->scalar();
    }
}