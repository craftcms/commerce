<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Closure;
use craft\helpers\Json;
use yii\base\BaseObject;

/**
 * Admin Table helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.4
 */
class AdminTable extends BaseObject
{
    // Properties
    // =========================================================================
    
    /**
     * @var array|Closure The actions for the table
     */
    private $actions = [];
    
    /**
     * @var bool|Closure Allow multiple selections
     */
    private $allowMultipleSelections = true;
    
    /**
     * @var bool|Closure Allow multiple deletions
     */
    private $allowMultipleDeletions = true;
    
    /**
     * @var callable|null Before delete callback
     */
    private $beforeDelete = null;
    
    /**
     * @var array|Closure The buttons for the table
     */
    private $buttons = [];
    
    /**
     * @var bool|Closure Show checkboxes
     */
    private $checkboxes = false;
    
    /**
     * @var callable|null Checkbox status callback
     */
    private $checkboxStatus = null;
    
    /**
     * @var array|Closure The columns for the table
     */
    private $columns = [];
    
    /**
     * @var string|Closure|null The container selector
     */
    private $container = null;
    
    /**
     * @var string|Closure|null The delete action URL
     */
    private $deleteAction = null;
    
    /**
     * @var callable|null Delete callback
     */
    private $deleteCallback = null;
    
    /**
     * @var string|Closure Delete confirmation message
     */
    private $deleteConfirmationMessage = 'Are you sure you want to delete "{name}"?';
    
    /**
     * @var string|Closure Delete fail message
     */
    private $deleteFailMessage = 'Couldn\'t delete "{name}".';
    
    /**
     * @var string|Closure Delete success message
     */
    private $deleteSuccessMessage = '"{name}" deleted.';
    
    /**
     * @var string|Closure Empty message
     */
    private $emptyMessage = 'No data available.';
    
    /**
     * @var array|Closure Footer actions
     */
    private $footerActions = [];
    
    /**
     * @var bool|Closure Full page
     */
    private $fullPage = false;
    
    /**
     * @var bool|Closure Full pane
     */
    private $fullPane = true;
    
    /**
     * @var array|Closure Item labels
     */
    private $itemLabels = ['singular' => 'Item', 'plural' => 'Items'];
    
    /**
     * @var int|Closure|null Minimum items
     */
    private $minItems = null;
    
    /**
     * @var string|Closure|null Move to page action URL
     */
    private $moveToPageAction = null;
    
    /**
     * @var string|Closure No search results message
     */
    private $noSearchResults = 'No results';
    
    /**
     * @var bool|Closure Padded
     */
    private $padded = false;
    
    /**
     * @var string|Closure|null Paginated reorder action URL
     */
    private $paginatedReorderAction = null;
    
    /**
     * @var int|Closure|null Results per page
     */
    private $perPage = null;
    
    /**
     * @var string|Closure|null Reorder action URL
     */
    private $reorderAction = null;
    
    /**
     * @var string|Closure Reorder fail message
     */
    private $reorderFailMessage = 'Couldn\'t reorder items';
    
    /**
     * @var string|Closure Reorder success message
     */
    private $reorderSuccessMessage = 'Items reordered';
    
    /**
     * @var bool|Closure Show search
     */
    private $search = false;
    
    /**
     * @var string|Closure Search clear button text
     */
    private $searchClear = 'Clear';
    
    /**
     * @var array|Closure Search params
     */
    private $searchParams = [];
    
    /**
     * @var string|Closure Search placeholder
     */
    private $searchPlaceholder = 'Search';
    
    /**
     * @var array|Closure|null Table data
     */
    private $tableData = null;
    
    /**
     * @var string|Closure|null Table data endpoint
     */
    private $tableDataEndpoint = null;

    /**
     * @var array Event callbacks
     */
    private array $eventCallbacks = [];
    
    /**
     * @var array|null The state variables passed to closures
     */
    private ?array $state = null;
    
    // Public Methods
    // =========================================================================
    
    /**
     * Set actions for the table
     *
     * @param array|Closure $actions
     * @return self
     */
    public function actions($actions): self
    {
        $this->actions = $actions;
        return $this;
    }
    
    /**
     * Get actions for the table
     *
     * @return array
     */
    public function getActions(): array
    {
        return $this->evaluateClosureValue($this->actions);
    }
    
    /**
     * Set whether to allow multiple selections
     *
     * @param bool|Closure $allow
     * @return self
     */
    public function allowMultipleSelections($allow): self
    {
        $this->allowMultipleSelections = $allow;
        return $this;
    }
    
    /**
     * Get whether to allow multiple selections
     *
     * @return bool
     */
    public function getAllowMultipleSelections(): bool
    {
        return $this->evaluateClosureValue($this->allowMultipleSelections);
    }
    
    /**
     * Set whether to allow multiple deletions
     *
     * @param bool|Closure $allow
     * @return self
     */
    public function allowMultipleDeletions($allow): self
    {
        $this->allowMultipleDeletions = $allow;
        return $this;
    }
    
    /**
     * Get whether to allow multiple deletions
     *
     * @return bool
     */
    public function getAllowMultipleDeletions(): bool
    {
        return $this->evaluateClosureValue($this->allowMultipleDeletions);
    }
    
    /**
     * Set the before delete callback
     *
     * @param callable $callback
     * @return self
     */
    public function beforeDelete(callable $callback): self
    {
        $this->beforeDelete = $callback;
        return $this;
    }
    
    /**
     * Get the before delete callback
     *
     * @return callable|null
     */
    public function getBeforeDelete(): ?callable
    {
        return $this->beforeDelete;
    }
    
    /**
     * Set buttons for the table
     *
     * @param array|Closure $buttons
     * @return self
     */
    public function buttons($buttons): self
    {
        $this->buttons = $buttons;
        return $this;
    }
    
    /**
     * Get buttons for the table
     *
     * @return array
     */
    public function getButtons(): array
    {
        return $this->evaluateClosureValue($this->buttons);
    }
    
    /**
     * Set whether to show checkboxes
     *
     * @param bool|Closure $show
     * @return self
     */
    public function checkboxes($show): self
    {
        $this->checkboxes = $show;
        return $this;
    }
    
    /**
     * Get whether to show checkboxes
     *
     * @return bool
     */
    public function getCheckboxes(): bool
    {
        return $this->evaluateClosureValue($this->checkboxes);
    }
    
    /**
     * Set checkbox status callback
     *
     * @param callable $callback
     * @return self
     */
    public function checkboxStatus(callable $callback): self
    {
        $this->checkboxStatus = $callback;
        return $this;
    }
    
    /**
     * Get checkbox status callback
     *
     * @return callable|null
     */
    public function getCheckboxStatus(): ?callable
    {
        return $this->checkboxStatus;
    }
    
    /**
     * Set columns for the table
     *
     * @param array|Closure $columns
     * @return self
     */
    public function columns($columns): self
    {
        $this->columns = $columns;
        return $this;
    }
    
    /**
     * Get columns for the table
     *
     * @return array
     */
    public function getColumns(): array
    {
        return $this->evaluateClosureValue($this->columns);
    }
    
    /**
     * Set the container selector
     *
     * @param string|Closure $selector
     * @return self
     */
    public function container($selector): self
    {
        $this->container = $selector;
        return $this;
    }
    
    /**
     * Get the container selector
     *
     * @return string|null
     */
    public function getContainer(): ?string
    {
        return $this->evaluateClosureValue($this->container);
    }
    
    /**
     * Set the delete action URL
     *
     * @param string|Closure $url
     * @return self
     */
    public function deleteAction($url): self
    {
        $this->deleteAction = $url;
        return $this;
    }
    
    /**
     * Get the delete action URL
     *
     * @return string|null
     */
    public function getDeleteAction(): ?string
    {
        return $this->evaluateClosureValue($this->deleteAction);
    }
    
    /**
     * Set the delete callback
     *
     * @param callable $callback
     * @return self
     */
    public function deleteCallback(callable $callback): self
    {
        $this->deleteCallback = $callback;
        return $this;
    }
    
    /**
     * Get the delete callback
     *
     * @return callable|null
     */
    public function getDeleteCallback(): ?callable
    {
        return $this->deleteCallback;
    }
    
    /**
     * Set the delete confirmation message
     *
     * @param string|Closure $message
     * @return self
     */
    public function deleteConfirmationMessage($message): self
    {
        $this->deleteConfirmationMessage = $message;
        return $this;
    }
    
    /**
     * Get the delete confirmation message
     *
     * @return string
     */
    public function getDeleteConfirmationMessage(): string
    {
        return $this->evaluateClosureValue($this->deleteConfirmationMessage);
    }
    
    /**
     * Set the delete fail message
     *
     * @param string|Closure $message
     * @return self
     */
    public function deleteFailMessage($message): self
    {
        $this->deleteFailMessage = $message;
        return $this;
    }
    
    /**
     * Get the delete fail message
     *
     * @return string
     */
    public function getDeleteFailMessage(): string
    {
        return $this->evaluateClosureValue($this->deleteFailMessage);
    }
    
    /**
     * Set the delete success message
     *
     * @param string|Closure $message
     * @return self
     */
    public function deleteSuccessMessage($message): self
    {
        $this->deleteSuccessMessage = $message;
        return $this;
    }
    
    /**
     * Get the delete success message
     *
     * @return string
     */
    public function getDeleteSuccessMessage(): string
    {
        return $this->evaluateClosureValue($this->deleteSuccessMessage);
    }
    
    /**
     * Set the empty message
     *
     * @param string|Closure $message
     * @return self
     */
    public function emptyMessage($message): self
    {
        $this->emptyMessage = $message;
        return $this;
    }
    
    /**
     * Get the empty message
     *
     * @return string
     */
    public function getEmptyMessage(): string
    {
        return $this->evaluateClosureValue($this->emptyMessage);
    }
    
    /**
     * Set footer actions
     *
     * @param array|Closure $actions
     * @return self
     */
    public function footerActions($actions): self
    {
        $this->footerActions = $actions;
        return $this;
    }
    
    /**
     * Get footer actions
     *
     * @return array
     */
    public function getFooterActions(): array
    {
        return $this->evaluateClosureValue($this->footerActions);
    }
    
    /**
     * Set whether this is a full page table
     *
     * @param bool|Closure $fullPage
     * @return self
     */
    public function fullPage($fullPage): self
    {
        $this->fullPage = $fullPage;
        return $this;
    }
    
    /**
     * Get whether this is a full page table
     *
     * @return bool
     */
    public function getFullPage(): bool
    {
        return $this->evaluateClosureValue($this->fullPage);
    }
    
    /**
     * Set whether this is a full pane table
     *
     * @param bool|Closure $fullPane
     * @return self
     */
    public function fullPane($fullPane): self
    {
        $this->fullPane = $fullPane;
        return $this;
    }
    
    /**
     * Get whether this is a full pane table
     *
     * @return bool
     */
    public function getFullPane(): bool
    {
        return $this->evaluateClosureValue($this->fullPane);
    }
    
    /**
     * Set item labels for pagination
     *
     * @param string|Closure $singular
     * @param string|Closure $plural
     * @return self
     */
    public function itemLabels($singular, $plural): self
    {
        $this->itemLabels = [
            'singular' => $singular,
            'plural' => $plural,
        ];
        return $this;
    }
    
    /**
     * Get item labels for pagination
     *
     * @return array
     */
    public function getItemLabels(): array
    {
        $itemLabels = $this->evaluateClosureValue($this->itemLabels);
        
        if (isset($itemLabels['singular']) && $itemLabels['singular'] instanceof Closure) {
            $itemLabels['singular'] = $this->evaluateClosureValue($itemLabels['singular']);
        }
        
        if (isset($itemLabels['plural']) && $itemLabels['plural'] instanceof Closure) {
            $itemLabels['plural'] = $this->evaluateClosureValue($itemLabels['plural']);
        }
        
        return $itemLabels;
    }
    
    /**
     * Set minimum items
     *
     * @param int|Closure $min
     * @return self
     */
    public function minItems($min): self
    {
        $this->minItems = $min;
        return $this;
    }
    
    /**
     * Get minimum items
     *
     * @return int|null
     */
    public function getMinItems(): ?int
    {
        return $this->evaluateClosureValue($this->minItems);
    }
    
    /**
     * Set move to page action URL
     *
     * @param string|Closure $url
     * @return self
     */
    public function moveToPageAction($url): self
    {
        $this->moveToPageAction = $url;
        return $this;
    }
    
    /**
     * Get move to page action URL
     *
     * @return string|null
     */
    public function getMoveToPageAction(): ?string
    {
        return $this->evaluateClosureValue($this->moveToPageAction);
    }
    
    /**
     * Set no search results message
     *
     * @param string|Closure $message
     * @return self
     */
    public function noSearchResults($message): self
    {
        $this->noSearchResults = $message;
        return $this;
    }
    
    /**
     * Get no search results message
     *
     * @return string
     */
    public function getNoSearchResults(): string
    {
        return $this->evaluateClosureValue($this->noSearchResults);
    }
    
    /**
     * Set whether to add padding around the table
     *
     * @param bool|Closure $padded
     * @return self
     */
    public function padded($padded): self
    {
        $this->padded = $padded;
        return $this;
    }
    
    /**
     * Get whether to add padding around the table
     *
     * @return bool
     */
    public function getPadded(): bool
    {
        return $this->evaluateClosureValue($this->padded);
    }
    
    /**
     * Set paginated reorder action URL
     *
     * @param string|Closure $url
     * @return self
     */
    public function paginatedReorderAction($url): self
    {
        $this->paginatedReorderAction = $url;
        return $this;
    }
    
    /**
     * Get paginated reorder action URL
     *
     * @return string|null
     */
    public function getPaginatedReorderAction(): ?string
    {
        return $this->evaluateClosureValue($this->paginatedReorderAction);
    }
    
    /**
     * Set results per page
     *
     * @param int|Closure $perPage
     * @return self
     */
    public function perPage($perPage): self
    {
        $this->perPage = $perPage;
        return $this;
    }
    
    /**
     * Get results per page
     *
     * @return int|null
     */
    public function getPerPage(): ?int
    {
        return $this->evaluateClosureValue($this->perPage);
    }
    
    /**
     * Set reorder action URL
     *
     * @param string|Closure $url
     * @return self
     */
    public function reorderAction($url): self
    {
        $this->reorderAction = $url;
        return $this;
    }
    
    /**
     * Get reorder action URL
     *
     * @return string|null
     */
    public function getReorderAction(): ?string
    {
        return $this->evaluateClosureValue($this->reorderAction);
    }
    
    /**
     * Set reorder fail message
     *
     * @param string|Closure $message
     * @return self
     */
    public function reorderFailMessage($message): self
    {
        $this->reorderFailMessage = $message;
        return $this;
    }
    
    /**
     * Get reorder fail message
     *
     * @return string
     */
    public function getReorderFailMessage(): string
    {
        return $this->evaluateClosureValue($this->reorderFailMessage);
    }
    
    /**
     * Set reorder success message
     *
     * @param string|Closure $message
     * @return self
     */
    public function reorderSuccessMessage($message): self
    {
        $this->reorderSuccessMessage = $message;
        return $this;
    }
    
    /**
     * Get reorder success message
     *
     * @return string
     */
    public function getReorderSuccessMessage(): string
    {
        return $this->evaluateClosureValue($this->reorderSuccessMessage);
    }
    
    /**
     * Set whether to show search
     *
     * @param bool|Closure $show
     * @return self
     */
    public function search($show): self
    {
        $this->search = $show;
        return $this;
    }
    
    /**
     * Get whether to show search
     *
     * @return bool
     */
    public function getSearch(): bool
    {
        return $this->evaluateClosureValue($this->search);
    }
    
    /**
     * Set search clear button text
     *
     * @param string|Closure $text
     * @return self
     */
    public function searchClear($text): self
    {
        $this->searchClear = $text;
        return $this;
    }
    
    /**
     * Get search clear button text
     *
     * @return string
     */
    public function getSearchClear(): string
    {
        return $this->evaluateClosureValue($this->searchClear);
    }
    
    /**
     * Set search parameters
     *
     * @param array|Closure $params
     * @return self
     */
    public function searchParams($params): self
    {
        $this->searchParams = $params;
        return $this;
    }
    
    /**
     * Get search parameters
     *
     * @return array
     */
    public function getSearchParams(): array
    {
        return $this->evaluateClosureValue($this->searchParams);
    }
    
    /**
     * Set search placeholder
     *
     * @param string|Closure $placeholder
     * @return self
     */
    public function searchPlaceholder($placeholder): self
    {
        $this->searchPlaceholder = $placeholder;
        return $this;
    }
    
    /**
     * Get search placeholder
     *
     * @return string
     */
    public function getSearchPlaceholder(): string
    {
        return $this->evaluateClosureValue($this->searchPlaceholder);
    }
    
    /**
     * Set table data (for data mode)
     *
     * @param array|Closure $data
     * @return self
     */
    public function tableData($data): self
    {
        $this->tableData = $data;
        return $this;
    }
    
    /**
     * Get table data (for data mode)
     *
     * @return array|null
     */
    public function getTableData(): ?array
    {
        return $this->evaluateClosureValue($this->tableData);
    }
    
    /**
     * Set table data endpoint (for API mode)
     *
     * @param string|Closure $endpoint
     * @return self
     */
    public function tableDataEndpoint($endpoint): self
    {
        $this->tableDataEndpoint = $endpoint;
        return $this;
    }
    
    /**
     * Get table data endpoint (for API mode)
     *
     * @return string|null
     */
    public function getTableDataEndpoint(): ?string
    {
        return $this->evaluateClosureValue($this->tableDataEndpoint);
    }
    
    /**
     * Set event callback
     *
     * @param string $event Event name (e.g., 'onSelect', 'onData', etc.)
     * @param callable $callback
     * @return self
     */
    public function on(string $event, callable $callback): self
    {
        $this->eventCallbacks[$event] = $callback;
        return $this;
    }
    
    /**
     * Get event callbacks
     *
     * @return array
     */
    public function getEventCallbacks(): array
    {
        return $this->eventCallbacks;
    }
    
    /**
     * Set state parameters that will be passed to closures
     *
     * @param array $state
     * @return self
     */
    public function withState(array $state): self
    {
        $this->state = $state;
        return $this;
    }
    
    /**
     * Get options JSON for JS initialization
     *
     * @return string
     */
    public function getOptionsJson(): string
    {
        $options = $this->getOptions();
        return Json::encode($options);
    }
    
    /**
     * Get options array for JS initialization
     *
     * @return array
     */
    public function getOptions(): array
    {
        $options = [
            'actions' => $this->getActions(),
            'allowMultipleSelections' => $this->getAllowMultipleSelections(),
            'allowMultipleDeletions' => $this->getAllowMultipleDeletions(),
            'buttons' => $this->getButtons(),
            'checkboxes' => $this->getCheckboxes(),
            'columns' => $this->getColumns(),
            'container' => $this->getContainer(),
            'deleteAction' => $this->getDeleteAction(),
            'deleteConfirmationMessage' => $this->getDeleteConfirmationMessage(),
            'deleteFailMessage' => $this->getDeleteFailMessage(),
            'deleteSuccessMessage' => $this->getDeleteSuccessMessage(),
            'emptyMessage' => $this->getEmptyMessage(),
            'footerActions' => $this->getFooterActions(),
            'fullPage' => $this->getFullPage(),
            'fullPane' => $this->getFullPane(),
            'itemLabels' => $this->getItemLabels(),
            'minItems' => $this->getMinItems(),
            'moveToPageAction' => $this->getMoveToPageAction(),
            'noSearchResults' => $this->getNoSearchResults(),
            'padded' => $this->getPadded(),
            'paginatedReorderAction' => $this->getPaginatedReorderAction(),
            'perPage' => $this->getPerPage(),
            'reorderAction' => $this->getReorderAction(),
            'reorderFailMessage' => $this->getReorderFailMessage(),
            'reorderSuccessMessage' => $this->getReorderSuccessMessage(),
            'search' => $this->getSearch(),
            'searchClear' => $this->getSearchClear(),
            'searchParams' => $this->getSearchParams(),
            'searchPlaceholder' => $this->getSearchPlaceholder(),
            'tableData' => $this->getTableData(),
            'tableDataEndpoint' => $this->getTableDataEndpoint(),
        ];
        
        // Add callback functions
        if ($this->beforeDelete !== null) {
            $options['beforeDelete'] = $this->beforeDelete;
        }
        
        if ($this->checkboxStatus !== null) {
            $options['checkboxStatus'] = $this->checkboxStatus;
        }
        
        if ($this->deleteCallback !== null) {
            $options['deleteCallback'] = $this->deleteCallback;
        }
        
        // Add event callbacks
        foreach ($this->eventCallbacks as $event => $callback) {
            $options[$event] = $callback;
        }
        
        // Filter out null values
        return array_filter($options, function($value) {
            return $value !== null;
        });
    }
    
    /**
     * Get JavaScript initialization code
     *
     * @return string
     */
    public function getJsInit(): string
    {
        $options = $this->getOptionsJson();
        return "new Craft.VueAdminTable($options);";
    }
    
    /**
     * Render the table
     *
     * @param string $containerId The ID of the container element
     * @return string HTML and JS for the admin table
     */
    public function render(string $containerId = 'admin-table'): string
    {
        // Set container if not already set
        if ($this->container === null) {
            $this->container = '#' . $containerId;
        }
        
        $html = '<div id="' . $containerId . '"></div>';
        $js = $this->getJsInit();
        
        return $html . '<script>' . $js . '</script>';
    }
    
    /**
     * Evaluate a value that might be a closure
     *
     * @param mixed $value The value or closure to evaluate
     * @return mixed The evaluated value
     */
    protected function evaluateClosureValue($value)
    {
        if ($value instanceof Closure) {
            return $value($this->state ?: []);
        }
        
        return $value;
    }
    
    /**
     * Create a column definition
     *
     * @param string $name The column name
     * @param string|Closure $title The column title
     * @param array $options Additional column options
     * @return array The column definition
     */
    public static function createColumn(string $name, $title, array $options = []): array
    {
        $column = array_merge([
            'name' => $name,
            'title' => $title,
        ], $options);
        
        return $column;
    }
    
    /**
     * Create a title column definition
     *
     * @param string|Closure $title The column title
     * @param array $options Additional column options
     * @return array The column definition
     */
    public static function createTitleColumn($title, array $options = []): array
    {
        return self::createColumn('__slot:title', $title, $options);
    }
    
    /**
     * Create a handle column definition
     *
     * @param string|Closure $title The column title
     * @param array $options Additional column options
     * @return array The column definition
     */
    public static function createHandleColumn($title, array $options = []): array
    {
        return self::createColumn('__slot:handle', $title, $options);
    }
    
    /**
     * Create a menu column definition
     *
     * @param string|Closure $title The column title
     * @param array $options Additional column options
     * @return array The column definition
     */
    public static function createMenuColumn($title, array $options = []): array
    {
        return self::createColumn('__slot:menu', $title, $options);
    }
    
    /**
     * Create a detail column definition
     *
     * @param string|Closure $title The column title
     * @param array $options Additional column options
     * @return array The column definition
     */
    public static function createDetailColumn($title, array $options = []): array
    {
        return self::createColumn('__slot:detail', $title, $options);
    }
}
