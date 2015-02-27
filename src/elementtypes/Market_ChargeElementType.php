<?php
namespace Craft;

class Market_ChargeElementType extends Market_BaseElementType
{
	/**
	 * Returns the element type name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Charges');
	}

	/**
	 * Returns whether this element type has content.
	 *
	 * @return bool
	 */
	public function hasContent()
	{
		return true;
	}

	/**
	 * Returns whether this element type has titles.
	 *
	 * @return bool
	 */
	public function hasTitles()
	{
		return false;
	}

	/**
	 * Returns whether this element type can have statuses.
	 *
	 * @return bool
	 */
	public function hasStatuses()
	{
		return false;
	}

	/**
	 * Returns this element type's sources.
	 *
	 * @param string|null $context
	 *
	 * @return array|false
	 */
	public function getSources($context = NULL)
	{
		$sources = [
			'*' => [
				'label' => Craft::t('All Charges'),
			]
		];

		return $sources;
	}

	/**
	 * Returns the attributes that can be shown/sorted by in table views.
	 *
	 * @param string|null $source
	 *
	 * @return array
	 */
	public function defineTableAttributes($source = NULL)
	{
		return [
			'id'       => Craft::t('Craft Id'),
			'stripeId' => Craft::t('Stripe Charge Id'),
			'amount'   => Craft::t('Amount'),
		];
	}

	/**
	 * Returns attributes available to search
	 *
	 * @return array
	 */
	public function defineSearchableAttributes()
	{
		return ['stripeId'];
	}


	public function getTableAttributeHtml(BaseElementModel $element, $attribute)
	{
		switch ($attribute) {
			case 'amount': {
				return "$ " . $element->amount;
			}
			default: {
				return parent::getTableAttributeHtml($element, $attribute);
			}
		}
	}

	/**
	 * Sortable by
	 *
	 * @return array
	 */
	public function defineSortableAttributes()
	{
		return [
			'stripeId' => Craft::t('Stripe ID'),
			'amount'   => Craft::t('Amount')
		];
	}

	/**
	 * Defines any custom element criteria attributes for this element type.
	 *
	 * @return array
	 */
	public function defineCriteriaAttributes()
	{
		return [
			'stripeId' => AttributeType::Mixed,
		];
	}

	/**
	 * Modifies an element query targeting elements of this type.
	 *
	 * @param DbCommand            $query
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return mixed
	 */
	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect("charges.id,charges.stripeId,charges.amount")
			->join('market_charges charges', 'charges.id = elements.id');

		if ($criteria->stripeId) {
			$query->andWhere(DbHelper::parseParam('charges.stripeId', $criteria->stripeId, $query->params));
		}
	}

	/**
	 * Populates an element model based on a query result.
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function populateElementModel($row)
	{
		return Market_ChargeModel::populateModel($row);
	}

} 