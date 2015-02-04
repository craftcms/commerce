<?php
namespace Stripey\Product\Behavior\Statemachine;

/**
 * An event raised when a state machine transitions from one state to another
 *
 * @author  Charles Pick
 * @package packages.stateMachine
 */
class AStateTransition extends CEvent
{
	/**
	 * The state the machine is transitioning from
	 *
	 * @var AState
	 */
	public $from;
	/**
	 * The state the machine is transitioning to
	 *
	 * @var AState
	 */
	public $to;

	/**
	 * Whether the event is valid and the transition should proceed
	 *
	 * @var boolean
	 */
	public $isValid = true;

}
