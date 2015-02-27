<?php
namespace Craft;

require_once(__DIR__ . '/Market_BaseElementType.php');

class Market_PlanElementType extends Market_BaseElementType
{
	/**
	 * Returns the element type name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Plans');
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
	 * Returns this element type's sources.
	 *
	 * @param string|null $context
	 *
	 * @return array|false
	 */
	public function getSources($context = NULL)
	{
		$sources = array(
			'*' => array(
				'label' => Craft::t('All Plans'),
			)
		);

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
		return array(
			'startDate' => Craft::t('Start Date'),
			'endDate'   => Craft::t('End Date'),
		);
	}

	/**
	 * Returns the table view HTML for a given attribute.
	 *
	 * @param BaseElementModel $element
	 * @param string           $attribute
	 *
	 * @return string
	 */
	public function getTableAttributeHtml(BaseElementModel $element, $attribute)
	{
		return parent::getTableAttributeHtml($element, $attribute);
	}

	/**
	 * Defines any custom element criteria attributes for this element type.
	 *
	 * @return array
	 */
	public function defineCriteriaAttributes()
	{
		return array(
			'calendar'   => AttributeType::Mixed,
			'calendarId' => AttributeType::Mixed,
			'startDate'  => AttributeType::Mixed,
			'endDate'    => AttributeType::Mixed,
			'order'      => array(AttributeType::String, 'default' => 'events.startDate asc'),
		);
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
			->addSelect('events.calendarId, events.startDate, events.endDate')
			->join('events events', 'events.id = elements.id');

		if ($criteria->calendarId) {
			$query->andWhere(DbHelper::parseParam('events.calendarId', $criteria->calendarId, $query->params));
		}

		if ($criteria->calendar) {
			$query->join('events_calendars events_calendars', 'events_calendars.id = events.calendarId');
			$query->andWhere(DbHelper::parseParam('events_calendars.handle', $criteria->calendar, $query->params));
		}

		if ($criteria->startDate) {
			$query->andWhere(DbHelper::parseDateParam('entries.startDate', $criteria->startDate, $query->params));
		}

		if ($criteria->endDate) {
			$query->andWhere(DbHelper::parseDateParam('entries.endDate', $criteria->endDate, $query->params));
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
		return Events_EventModel::populateModel($row);
	}

	/**
	 * Returns the HTML for an editor HUD for the given element.
	 *
	 * @param BaseElementModel $element
	 *
	 * @return string
	 */
	public function getEditorHtml(BaseElementModel $element)
	{
		// Start/End Dates
		$html = craft()->templates->render('events/_edit', array(
			'element' => $element,
		));

		// Everything else
		$html .= parent::getEditorHtml($element);

		return $html;
	}
} 