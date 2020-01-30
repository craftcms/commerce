<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use craft\commerce\elements\Donation;
use craft\elements\db\ElementQuery;
use yii\db\Connection;

/**
 * DonationQuery represents a SELECT SQL statement for donations in a way that is independent of DBMS.
 *
 * @method Donation[]|array all($db = null)
 * @method Donation|array|null one($db = null)
 * @method Donation|array|null nth(int $n, Connection $db = null)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @skip-docs
 */
class DonationQuery extends ElementQuery
{
    /**
     * @var string The sku of the donation purchasable
     */
    public $sku;



    /**
     * Narrows the query results based on the sku.
     *
     * Possible values include:
     *
     * | Value | Fetches {elements}…
     * | - | -
     * | `'DON-123'` | with a matching sku
     *
     * ---
     *
     * ```twig
     * {# Fetch the requested {element} #}
     * {% set sku = craft.app.request.getQueryParam('sku') %}
     * {% set {element-var} = {twig-method}
     *     .sku(sku)
     *     .one() %}
     * ```
     *
     * ```php
     * // Fetch the requested {element}
     * $sku = Craft::$app->request->getQueryParam('sku');
     * ${element-var} = {php-method}
     *     ->sku($sku)
     *     ->one();
     * ```
     *
     * @param string|null $value The property value
     * @return static self reference
     */
    public function sku(string $value = null)
    {
        $this->sku = $value;
        return $this;
    }


    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('commerce_donations');

        $this->query->select([
            'commerce_donations.id',
            'commerce_donations.sku',
            'commerce_donations.availableForPurchase'
        ]);

        if ($this->sku) {
            $this->subQuery->andWhere(['commerce_donations.sku' => $this->sku]);
        }

        return parent::beforePrepare();
    }
}
