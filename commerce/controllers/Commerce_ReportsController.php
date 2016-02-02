<?php
namespace Craft;

/**
 * Class Commerce_ReportsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ReportsController extends BaseElementsController
{
    // Properties
    // =========================================================================

    /**
     * @var IElementType
     */
    private $_elementType;

    /**
     * @var string
     */
    private $_context;

    /**
     * @var string
     */
    private $_sourceKey;

    /**
     * @var array|null
     */
    private $_source;

    /**
     * @var array
     */
    private $_viewState;

    /**
     * @var ElementCriteriaModel
     */
    private $_criteria;

    /**
     * @var array|null
     */
    private $_actions;


    // Public Methods
    // =========================================================================

    /**
     * Initializes the application component.
     *
     * @return null
     */
    public function init()
    {
        parent::init();

        $this->_elementType = $this->getElementType();
        $this->_context     = $this->getContext();
        $this->_sourceKey   = craft()->request->getParam('source');
        $this->_source      = $this->_getSource();
        $this->_viewState   = $this->_getViewState();
        $this->_criteria    = $this->_getCriteria();

        if ($this->_context == 'index')
        {
            $this->_actions = $this->_getAvailableActions();
        }
    }

    public function actionGetOrders()
    {
        $total = 0;

        $dateRange = craft()->request->getParam('dateRange');
        $startDate = craft()->request->getParam('startDate');
        $endDate = craft()->request->getParam('endDate');

        if($dateRange)
        {
            switch ($dateRange)
            {
                case 'd7':
                $startDate = '-7 days';
                break;

                case 'd30':
                $startDate = '-30 days';
                break;

                case 'lastweek':
                $startDate = '-2 weeks';
                $endDate = '-1 week';
                break;

                case 'lastmonth':
                $startDate = '-2 month';
                $endDate = '-1 month';
                break;
            }
        }

        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        $endDate->modify('+1 day');


        // auto scale

        $numberOfDays = floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / (60*60*24));

        if ($numberOfDays > 360)
        {
            $scale = 'year';
        }
        elseif($numberOfDays > 60)
        {
            $scale = 'month';
        }
        else
        {
            $scale = 'day';
        }

        // currency
        $currency = craft()->commerce_settings->getOption('defaultCurrency');


        // report columns

        $columns = [];

        $columns[] = [
            'type' => 'date',
            'label' => 'Date',
        ];

        $columns[] = [
            'type' => 'currency',
            'label' => 'Revenue',
        ];


        // report rows

        $rows = [];

        $cursorCurrent = new DateTime($startDate);

        while($cursorCurrent->getTimestamp() < $endDate->getTimestamp())
        {
            $cursorStart = new DateTime($cursorCurrent);
            $cursorCurrent->modify('+1 '.$scale);
            $cursorEnd = $cursorCurrent;

            $orders = $this->_getOrders($cursorStart, $cursorEnd);

            $totalPaid = 0;

            foreach($orders as $order)
            {
                $totalPaid += $order->totalPaid;
            }

            $rows[] = [
                [
                    'value' => strftime("%e-%b-%y", $cursorStart->getTimestamp()), // date
                ],
                [
                    'value' => $totalPaid, // revenue
                ]
            ];

            $total += $totalPaid;
        }

        $reportDataTable = [
            'columns' => $columns,
            'rows' => $rows
        ];

        $currency = craft()->commerce_settings->getSettings()->defaultCurrency;

        $this->returnJson(array(
            'reportDataTable' => $reportDataTable,
            'scale' => $scale,
            'currencyFormat' => $this->_getCurrencyFormat($currency),
            'total' => $total,
            'totalHtml' => craft()->numberFormatter->formatCurrency($total, strtoupper($currency)),
        ));
    }

    // Private Methods
    // =========================================================================

    private function _getCurrencyFormat($currency)
    {
        $currencySymbol = craft()->locale->getCurrencySymbol($currency);
        $currencyFormat = craft()->locale->getCurrencyFormat();

        if(strpos($currencyFormat, ";") > 0)
        {
            $currencyFormatArray = explode(";", $currencyFormat);
            $currencyFormat = $currencyFormatArray[0];
        }

        $pattern = '/[#0,.]/';
        $replacement = '';
        $currencyFormat = preg_replace($pattern, $replacement, $currencyFormat);

        if(strpos($currency, "¤") === 0)
        {
            // symbol at beginning
            $currencyD3Format = [str_replace('¤', $currencySymbol, $currencyFormat), ''];
        }
        else
        {
            // symbol at the end
            $currencyD3Format = ['', str_replace('¤', $currencySymbol, $currencyFormat)];
        }
    }

    private function _getOrders($start, $end)
    {
        $this->_criteria->dateOrdered = ['and', '>= '.$start, '< '.$end];
        $this->_criteria->order = 'dateOrdered desc';

        return $this->_criteria->find();
    }

    /**
     * Returns the selected source info.
     *
     * @throws HttpException
     * @return array|null
     */
    private function _getSource()
    {
        if ($this->_sourceKey)
        {
            $source = $this->_elementType->getSource($this->_sourceKey, $this->_context);

            if (!$source)
            {
                // That wasn't a valid source, or the user doesn't have access to it in this context
                throw new HttpException(404);
            }

            return $source;
        }
    }

    /**
     * Returns the current view state.
     *
     * @return array
     */
    private function _getViewState()
    {
        $viewState = craft()->request->getParam('viewState', array());

        if (empty($viewState['mode']))
        {
            $viewState['mode'] = 'table';
        }

        return $viewState;
    }

    /**
     * Returns the element criteria based on the current params.
     *
     * @return ElementCriteriaModel
     */
    private function _getCriteria()
    {
        $criteria = craft()->elements->getCriteria($this->_elementType->getClassHandle());

        // Does the source specify any criteria attributes?
        if (!empty($this->_source['criteria']))
        {
            $criteria->setAttributes($this->_source['criteria']);
        }

        // Override with the request's params
        $criteria->setAttributes(craft()->request->getPost('criteria'));

        // Exclude descendants of the collapsed element IDs
        if (!$criteria->id)
        {
            $collapsedElementIds = craft()->request->getParam('collapsedElementIds');

            if ($collapsedElementIds)
            {
                // Get the actual elements
                $collapsedElementCriteria = $criteria->copy();
                $collapsedElementCriteria->id = $collapsedElementIds;
                $collapsedElementCriteria->offset = 0;
                $collapsedElementCriteria->limit = null;
                $collapsedElementCriteria->order = 'lft asc';
                $collapsedElementCriteria->positionedAfter = null;
                $collapsedElementCriteria->positionedBefore = null;
                $collapsedElements = $collapsedElementCriteria->find();

                if ($collapsedElements)
                {
                    $descendantIds = array();

                    $descendantCriteria = $criteria->copy();
                    $descendantCriteria->offset = 0;
                    $descendantCriteria->limit = null;
                    $descendantCriteria->order = null;
                    $descendantCriteria->positionedAfter = null;
                    $descendantCriteria->positionedBefore = null;

                    foreach ($collapsedElements as $element)
                    {
                        // Make sure we haven't already excluded this one, because its ancestor is collapsed as well
                        if (in_array($element->id, $descendantIds))
                        {
                            continue;
                        }

                        $descendantCriteria->descendantOf = $element;
                        $descendantIds = array_merge($descendantIds, $descendantCriteria->ids());
                    }

                    if ($descendantIds)
                    {
                        $idsParam = array('and');

                        foreach ($descendantIds as $id)
                        {
                            $idsParam[] = 'not '.$id;
                        }

                        $criteria->id = $idsParam;
                    }
                }
            }
        }

        return $criteria;
    }

    /**
     * Returns the element HTML to be returned to the client.
     *
     * @param bool $includeContainer Whether the element container should be included in the response data
     * @param bool $includeActions   Whether info about the available actions should be included in the response data
     *
     * @return string
     */
    private function _getElementResponseData($includeContainer, $includeActions)
    {
        $responseData = array();

        // Get the action head/foot HTML before any more is added to it from the element HTML
        if ($includeActions)
        {
            $responseData['actions'] = $this->_getActionData();
            $responseData['actionsHeadHtml'] = craft()->templates->getHeadHtml();
            $responseData['actionsFootHtml'] = craft()->templates->getFootHtml();
        }

        $disabledElementIds = craft()->request->getParam('disabledElementIds', array());
        $showCheckboxes = !empty($this->_actions);

        $responseData['html'] = $this->_elementType->getIndexHtml(
            $this->_criteria,
            $disabledElementIds,
            $this->_viewState,
            $this->_sourceKey,
            $this->_context,
            $includeContainer,
            $showCheckboxes
        );

        $responseData['headHtml'] = craft()->templates->getHeadHtml();
        $responseData['footHtml'] = craft()->templates->getFootHtml();

        return $responseData;
    }

    /**
     * Returns the available actions for the current source.
     *
     * @return array|null
     */
    private function _getAvailableActions()
    {
        if (craft()->request->isMobileBrowser())
        {
            return;
        }

        $actions = $this->_elementType->getAvailableActions($this->_sourceKey);

        if ($actions)
        {
            foreach ($actions as $i => $action)
            {
                if (is_string($action))
                {
                    $actions[$i] = $action = craft()->elements->getAction($action);
                }

                if (!($action instanceof IElementAction))
                {
                    unset($actions[$i]);
                }
            }

            return array_values($actions);
        }
    }

    /**
     * Returns the data for the available actions.
     *
     * @return array
     */
    private function _getActionData()
    {
        if ($this->_actions)
        {
            $actionData = array();

            foreach ($this->_actions as $action)
            {
                $actionData[] = array(
                    'handle'      => $action->getClassHandle(),
                    'name'        => $action->getName(),
                    'destructive' => $action->isDestructive(),
                    'trigger'     => $action->getTriggerHtml(),
                    'confirm'     => $action->getConfirmationMessage(),
                );
            }

            return $actionData;
        }
    }

}
