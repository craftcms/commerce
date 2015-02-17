<?php

namespace Market\Behaviors\Statemachine;

/**
 * Represents a state for a state machine
 *
 * @author  Charles Pick
 * @package packages.stateMachine
 */
class AState extends \CBehavior
{
	/**
	 * The state machine this state belongs to
	 *
	 * @var AStateMachine
	 */
	protected $_machine;

	/**
	 * The name of this state
	 *
	 * @var string
	 */
	protected $_name;

	/**
	 * Names of the states which can replace current state
	 *
	 * @var array
	 */
	protected $_transitsTo;

	/**
	 * Constructor
	 *
	 * @param string        $name  The name of the state
	 * @param AStateMachine $owner the state machine this state belongs to
	 */
	public function __construct($name, AStateMachine $owner)
	{
		$this->setName($name);
		$this->setMachine($owner);
	}

	/**
	 * Invoked before the state is transitioned to
	 *
	 * @return boolean true if the event is valid and the transition should be allowed to continue
	 */
	public function beforeEnter()
	{
		$transition       = new AStateTransition($this);
		$transition->to   = $this;
		$transition->from = $this->_machine->getState();
		$this->onBeforeEnter($transition);

		return $transition->isValid;
	}

	/**
	 * This event is raised before the state is transitioned to
	 *
	 * @param AStateTransition $transition the state transition
	 */
	public function onBeforeEnter($transition)
	{
		$this->raiseEvent("onBeforeEnter", $transition);
	}

	/**
	 * Invoked after the state is transitioned to
	 *
	 * @param AState $from The state we're transitioning from
	 */
	public function afterEnter(AState $from)
	{
		$transition       = new AStateTransition($this);
		$transition->to   = $this;
		$transition->from = $from;
		$this->onAfterEnter($transition);
	}

	/**
	 * This event is raised after the state is transitioned to
	 *
	 * @param AStateTransition $transition the state transition
	 */
	public function onAfterEnter($transition)
	{
		$this->raiseEvent("onAfterEnter", $transition);
	}

	/**
	 * Invoked before the state is transitioned from
	 *
	 * @param  AState $toState The state we're transitioning to
	 *
	 * @return boolean true if the event is valid and the transition should be allowed to continue
	 */
	public function beforeExit(AState $toState)
	{
		$transition       = new AStateTransition($this);
		$transition->to   = $toState;
		$transition->from = $this;

		if ($this->_machine->checkTransitionMap && !in_array($toState->name, $this->transitsTo)) {
			$transition->isValid = false;
		}

		$this->onBeforeExit($transition);

		return $transition->isValid;
	}

	/**
	 * This event is raised before the state is transitioned from
	 *
	 * @param AStateTransition $transition the state transition
	 */
	public function onBeforeExit($transition)
	{
		$this->raiseEvent("onBeforeExit", $transition);
	}

	/**
	 * Invoked after the state is transitioned from
	 */
	public function afterExit()
	{
		$transition       = new AStateTransition($this);
		$transition->from = $this;
		$transition->to   = $this->_machine->getState();
		$this->onAfterExit($transition);
	}

	/**
	 * This event is raised after the state is transitioned from
	 *
	 * @param AStateTransition $transition the state transition
	 */
	public function onAfterExit($transition)
	{
		$this->raiseEvent("onAfterExit", $transition);
	}

	/**
	 * Gets the name for this state
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->_name;
	}

	/**
	 * Sets the name for this state
	 *
	 * @param string $name
	 */
	public function setName($name)
	{
		return $this->_name = $name;
	}

	/**
	 * Gets the state machine the state belongs to
	 *
	 * @return AStateMachine
	 */
	public function getMachine()
	{
		return $this->_machine;
	}

	/**
	 * Sets the state machine that this state belongs to
	 *
	 * @param  AStateMachine $owner the state machine this state belongs to
	 *
	 * @return AStateMachine the state machine
	 */
	public function setMachine($owner)
	{
		return $this->_machine = $owner;
	}

	public function getTransitsTo()
	{
		return ($this->_transitsTo) ? $this->_transitsTo : array();
	}

	/**
	 *
	 * @param mixed $states
	 */
	public function setTransitsTo($states)
	{
		$transitsTo = $states;

		if (!is_array($states)) {
			if (is_string($states)) {
				if (strstr($states, ',') !== false) {
					$transitsTo = explode(',', preg_replace('/\s+/', '', $states));
				} else
					$transitsTo = array(trim($states));
			} else {
				throw new AStateException('Invalide transitsTo format: ' . print_r($states, true));
			}
		}

		$this->_transitsTo = $transitsTo;
	}
}
