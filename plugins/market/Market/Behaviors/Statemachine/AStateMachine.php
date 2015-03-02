<?php
namespace Market\Behaviors\Statemachine;

/**
 * Implements a simple state machine.
 * State machines can have various states, each state can provide methods and
 * properties unique to that state The state machine also manages the
 * transitions between states. Since AStateMachine extends CBehavior, it can
 * also be attached to other models e.g.
 * <pre>
 *    $stateMachine = new AStateMachine;
 *  $stateMachine->setStates(array(
 *        new ExampleEnabledState("enabled",$stateMachine),
 *      new ExampleDisabledState("disabled",$stateMachine),
 * ));
 * $stateMachine->defaultStateName = "enabled";
 * $model = new User;
 * $model->attachBehavior("status", $stateMachine);
 * echo $model->status->is("enabled") ? "true" : "false"; // "true"
 * $model->transition("disabled");
 * echo $model->status->getState(); // "disabled"
 * $model->status->enable(); // assuming enable is a method on
 * ExampleDisabledState echo $model->status->getState(); // "enabled"
 * </pre>
 *
 *
 * @author  Charles Pick
 * @package packages.stateMachine
 */
use Craft\BaseBehavior;

class AStateMachine extends BaseBehavior implements \IApplicationComponent
{
	/**
	 * Holds the name of the default state.
	 * Defaults to "default"
	 *
	 * @var string
	 */
	public $defaultStateName = "default";

	/**
	 * Whether to track state transition history or not.
	 * The state history will be stored in a stack and will not be persisted
	 * between requests. This is set to false by default.
	 *
	 * @var boolean
	 */
	public $enableTransitionHistory = false;

	/**
	 * The maximum size of the state history
	 * This is useful when performing lots of transitions.
	 * Defaults to null, meaning there is no maximum history size
	 *
	 * @var integer|null
	 */
	public $maximumTransitionHistorySize;

	/**
	 * Defines whather to use AState.transitsTo attribute to check transition
	 * validity. If it was set to TRUE than you should specify which states can
	 * be reached from current. For example:
	 * <pre>
	 *      $machine = new AStateMachine();
	 *      $machine->setStates(array(
	 *          array(
	 *              'name'=>'not_saved',
	 *              'transitsTo'=>'published'
	 *          ),
	 *          array(
	 *              'name'=>'published',
	 *              'transitsTo'=>'registration, canceled',
	 *          ),
	 *          array(
	 *              'name'=>'registration',
	 *              'transitsTo'=>'published, processing, canceled'
	 *          ),
	 *          array(
	 *              'name'=>'processing',
	 *              'transitsTo'=>'finished, canceled'
	 *          ),
	 *          array('name'=>'finished'),
	 *          array('name'=>'canceled')
	 *      ));
	 *      $machine->checkTransitionMap = true;
	 * </pre>
	 *
	 * @var boolean
	 */
	public $checkTransitionMap = false;

	/**
	 * Holds the transition history
	 *
	 * @var \CList
	 */
	protected $_transitionHistory;

	/**
	 * The name of the current state
	 *
	 * @var string
	 */
	protected $_stateName;
	/**
	 * The supported states
	 *
	 * @var AState[]
	 */
	protected $_states = [];
	/**
	 * Whether the state machine is initialized or not
	 *
	 * @var boolean
	 */
	protected $_isInitialized = false;

	/**
	 * The unique id for this state machine.
	 * This is used when the machine is attached as a behavior
	 *
	 * @var string
	 */
	protected $_uniqueID;

	/**
	 * Constructor.
	 * The default implementation calls the init() method
	 */
	public function __construct()
	{
		$this->init();
	}

	/**
	 * Initializes the state machine.
	 * The default implementation merely sets the $this->_isInitialized
	 * property to true Child classes that override this method should call the
	 * parent implementation This method is required by IApplicationComponent
	 */
	public function init()
	{
		$this->_isInitialized = true;
	}

	/**
	 * Determines whether the state machine has been initialized or not
	 *
	 * @return boolean
	 */
	public function getIsInitialized()
	{
		return $this->_isInitialized;
	}

	/**
	 * Attaches the state machine to a component
	 *
	 * @param \CComponent $owner the component to attach to
	 */
	public function attach($owner)
	{
		parent::attach($owner);
		if ($this->_uniqueID === NULL) {
			$this->_uniqueID = uniqid();
		}
		if (($state = $this->getState()) !== NULL) {
			$owner->attachBehavior($this->_uniqueID . "_" . $state->name, $state);
		}
	}

	/**
	 * Gets the current state
	 *
	 * @return AState|null the current state, or null if there is no state set
	 */
	public function getState()
	{
		$stateName = $this->getStateName();
		if (!isset($this->_states[$stateName])) {
			return NULL;
		}

		return $this->_states[$stateName];
	}

	/**
	 * Gets the name of the current state
	 *
	 * @return string
	 */
	public function getStateName()
	{
		if ($this->_stateName === NULL) {
			return $this->defaultStateName;
		}

		return $this->_stateName;
	}

	/**
	 * Sets the name of the current state but doesn't trigger the transition
	 * events
	 *
	 * @param string $state the name of the state to change to
	 */
	public function setStateName($state)
	{
		$this->_stateName = $state;
	}

	/**
	 * Detaches the state machine from a component
	 *
	 * @param \CComponent $owner the component to detach from
	 */
	public function detach($owner)
	{
		parent::detach($owner);
		if ($this->_uniqueID !== NULL) {
			$owner->detachBehavior($this->_uniqueID . "_" . $this->getStateName());
		}
	}

	/**
	 * Returns a property value based on its name.
	 *
	 * @param  string $name the property name or event name
	 *
	 * @return mixed      the property value, event handlers attached to the
	 *                    event, or the named behavior (since version 1.0.2)
	 * @throws \CException if the property or event is not defined
	 * @see CComponent::__get()
	 */

	public function __get($name)
	{
		$state = $this->getState();
		if ($state !== NULL && (property_exists($state, $name) || $state->canGetProperty($name))) {
			return $state->{$name};
		}

		return parent::__get($name);
	}

	/**
	 * Sets a property value based on its name.
	 *
	 * @param  string $name  the property name or event name
	 * @param  mixed  $value the property value
	 *
	 * @return mixed      the property value, event handlers attached to the
	 *                    event, or the named behavior (since version 1.0.2)
	 * @throws \CException if the property or event is not defined
	 * @see CComponent::__get()
	 */
	public function __set($name, $value)
	{
		$state = $this->getState();
		if ($state !== NULL && (property_exists($state, $name) || $state->canSetProperty($name))) {
			return $state->{$name} = $value;
		}

		return parent::__set($name, $value);
	}

	/**
	 * Checks if a property value is null.
	 * Do not call this method. This is a PHP magic method that we override
	 * to allow using isset() to detect if a component property is set or not.
	 *
	 * @param  string $name the property name or the event name
	 *
	 * @return boolean
	 * @since 1.0.1
	 */
	public function __isset($name)
	{
		$state = $this->getState();
		if ($state !== NULL && (property_exists($state, $name) || $state->canGetProperty($name))) {
			return true;
		}

		return parent::__isset($name);
	}

	/**
	 * Sets a component property to be null.
	 *
	 * @param  string $name the property name or event name
	 *
	 * @return mixed      the property value, event handlers attached to the
	 *                    event, or the named behavior (since version 1.0.2)
	 * @throws \CException if the property or event is not defined
	 * @see CComponent::__get()
	 */
	public function __unset($name)
	{
		$state = $this->getState();
		if ($state !== NULL && (property_exists($state, $name) || $state->canSetProperty($name))) {
			return $state->{$name} = NULL;
		}

		return parent::__unset($name);
	}

	/**
	 * Calls the named method which is not a class method.
	 * Do not call this method. This is a PHP magic method that we override
	 * to implement the states feature.
	 *
	 * @param  string $name       the method name
	 * @param  array  $parameters method parameters
	 *
	 * @return mixed  the method return value
	 */
	public function __call($name, $parameters)
	{
		$state = $this->getState();
		if (is_object($state) && method_exists($state, $name)) {
			return call_user_func_array([$state, $name], $parameters);
		}

		return parent::__call($name, $parameters);
	}

	/**
	 * Gets an array of possible states for this machine
	 *
	 * @return AState[] the possible states for the machine
	 */
	public function getStates()
	{
		return $this->_states;
	}

	/**
	 * Sets the possible states for this machine
	 *
	 * @param AState[] $states an array of possible states
	 *
	 * @return AState[]
	 */
	public function setStates($states)
	{
		$this->_states = [];
		foreach ($states as $state) {
			$this->addState($state);
		}

		return $this->_states;
	}

	/**
	 * Adds a state to the machine
	 *
	 * @param  AState|array $state The state to add, either an instance of
	 *                             AState or a configuration array for an
	 *                             AState
	 *
	 * @return AState       the added state
	 */
	public function addState($state)
	{
		if (is_array($state)) {
			if (!isset($state['class'])) {
				$state['class'] = 'Market\Behaviors\Statemachine\AState';
			}
			$state = \Yii::createComponent($state, $state['name'], $this);
		}

		return $this->_states[$state->getName()] = $state;
	}

	/**
	 * Removes a state with the given name
	 *
	 * @param  string $stateName the name of the state to remove
	 *
	 * @return AState|null the removed state, or null if there was no state by
	 *                     that name
	 */
	public function removeState($stateName)
	{
		if (!$this->hasState($stateName)) {
			return NULL;
		}
		$state = $this->_states[$stateName];
		unset($this->_states[$stateName]);
		$this->_stateName = $this->defaultStateName;

		return $state;
	}

	/**
	 * Transitions to a
	 *
	 * @param  string $state the name of the state
	 *
	 * @return boolean true if the state exists, otherwise false
	 */
	public function hasState($state)
	{
		return isset($this->_states[$state]);
	}

	/**
	 * Gets the default state
	 *
	 * @return AState|null the default state, or null if no state is set
	 */
	public function getDefaultState()
	{
		if (is_null($this->defaultStateName) || !$this->hasState($this->defaultStateName)) {
			return NULL;
		}

		return $this->_states[$this->defaultStateName];
	}

	/**
	 * Transitions the state machine to the specified state
	 *
	 * @throws AInvalidStateException if the state doesn't exist
	 *
	 * @param  string $to     The name of the state we're transitioning to
	 * @param mixed   $params additional parameters for the before/after
	 *                        Transition events
	 *
	 * @return boolean true if the transition succeeded or false if it failed
	 */
	public function transition($to, $params = NULL)
	{
		if (!$this->hasState($to)) {
			throw new AInvalidStateException("No such state: " . $to);
		}
		$toState   = $this->_states[$to];
		$fromState = $this->getState();

		if (!$this->canTransit($to, $params)) {
			return false;
		}

		if (($owner = $this->getOwner()) !== NULL) {

			// we need to attach the current state to the owner
			$owner->detachBehavior($this->_uniqueID . "_" . $this->getStateName());
			$this->setStateName($to);
			$owner->attachBehavior($this->_uniqueID . "_" . $to, $toState);
		} else {
			$this->setStateName($to);
		}

		if ($this->enableTransitionHistory) {
			if ($this->maximumTransitionHistorySize !== NULL && ($c = $this->getTransitionHistory()->count() - $this->maximumTransitionHistorySize) >= 0) {
				for ($i = 0; $i <= $c; $i++) {
					$this->getTransitionHistory()->removeAt(0);
				}

			}
			$this->getTransitionHistory()->add($to);
		}
		$this->afterTransition($fromState, $params);

		return true;
	}

	/**
	 * Checks can the state machine transite to the specified state
	 *
	 * @throws AInvalidStateException if the state doesn't exist
	 *
	 * @param string $to     The name of the state we're transitioning to
	 * @param mixed  $params additional parameters for the before/after
	 *                       Transition events
	 *
	 * @return boolean true if the transition succeeded or false if it failed
	 */
	public function canTransit($to, $params = NULL)
	{
		if (!$this->hasState($to)) {
			throw new AInvalidStateException("No such state: " . $to);
		}
		$toState = $this->_states[$to];

		if (!$this->beforeTransition($toState, $params)) {
			return false;
		}

		return true;
	}

	/**
	 * Invoked before a state transition
	 *
	 * @param AState $toState The state we're transitioning to
	 * @param mixed  $params  additional parameters for the event
	 *
	 * @return boolean true if the event is valid and the transition should be
	 *                 allowed to continue
	 */
	public function beforeTransition(AState $toState, $params = NULL)
	{
		if (!$this->getState()->beforeExit($toState) || !$toState->beforeEnter()) {
			return false;
		}
		$transition       = new AStateTransition($this, $params);
		$transition->to   = $toState;
		$transition->from = $this->getState();
		$this->onBeforeTransition($transition);

		return $transition->isValid;
	}

	/**
	 * This event is raised before a state transition
	 *
	 * @param AStateTransition $transition the state transition
	 */
	public function onBeforeTransition($transition)
	{
		$this->raiseEvent("onBeforeTransition", $transition);
	}

	/**
	 * Gets the transition history
	 *
	 * @return \CList the transition history
	 */
	public function getTransitionHistory()
	{
		if ($this->_transitionHistory === NULL) {
			$this->_transitionHistory = new \CList([$this->getStateName()]);
		}

		return $this->_transitionHistory;
	}

	/**
	 * Invoked after a state transition
	 *
	 * @param AState $from   The state we're transitioning from
	 * @param mixed  $params additional parameters for the event
	 */
	public function afterTransition(AState $fromState, $params = NULL)
	{
		$fromState->afterExit();
		$this->getState()->afterEnter($fromState);

		$transition       = new AStateTransition($this, $params);
		$transition->to   = $this->getState();
		$transition->from = $fromState;
		$this->onAfterTransition($transition);
	}

	/**
	 * This event is raised after a state transition
	 *
	 * @param AStateTransition $transition the state transition
	 */
	public function onAfterTransition($transition)
	{
		$this->raiseEvent("onAfterTransition", $transition);
	}

	/**
	 * Determines whether the current state matches the given name
	 *
	 * @param  string $stateName the name of the state to check against
	 *
	 * @return boolean true if the state names match
	 */
	public function is($stateName)
	{
		return $this->getStateName() == $stateName;
	}

	/**
	 * Returns available states that can be reached from current.
	 * It is usefull when you want allow user to chose next state somewhere in
	 * an UI.
	 *
	 * @return array
	 */
	public function getAvailableStates()
	{
		$result = [];

		foreach ($this->states as $state)
			if ($this->canTransit($state->name))
				$result[] = $state->name;

		return $result;
	}
}
