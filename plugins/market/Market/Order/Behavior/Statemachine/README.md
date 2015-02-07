<h1>Yii State Machine</h1>
An object oriented state machine for Yii. It can be used independently or as a behavior to augment your existing components / models.


<h2>Why is this useful?</h2>

As developers, we're often in the situation where we need to keep track of an object's state.
For example, your user system might require users to activate their accounts by clicking a link on
an email, and you might also offer users the ability to deactivate their accounts rather than merely deleting them.
To do this, you would typically have an enum field called something like "status" with the fields:
<ul>
    <li>pending</li>
    <li>active</li>
    <li>inactive</li>
</ul>

and that's great. But what if you need to *do something* when a user activates their account?
You need to store the user's current status after find, then check if it has changed, then, finally do your magic,
that could get complicated if you have other status fields.

And also, what if you have business logic that only applies when the user is in a certain state?
After all, an active user should not be able to activate their account a second time, just as a deactivated user should
not be able to deactivate their account a second time. Again, when a user can have a lot of different states,
this can become code spaghetti pretty quickly.

This is where the state machine comes in.
The state machine keeps track of the user's state, and manages the transitions from one state to another.
A state can encapsulate logic that only applies when the state machine is in that state, and also provides
events that are raised when the state is transitioned from and to.
When the state machine is in a certain state, the methods and properties declared on that state become available to the state machine.
This means that in the user scenario, we could implement:
<ul>
    <li>an activate() method on the *pending* state</li>
    <li>a deactivate() method on the *active* state</li>
    <li>a reactivate() method on the *inactive* state</li>
</ul>
We can also do stuff, e.g. sending a welcome email when the user transitions to the *active* state by handling the
afterEnter() event on the *active* state. If we want to send a different email when the account is reactivated, we can
handle that by inspecting the $from method parameter which refers to the previous state, if the previous state is *inactive*
send a welcome back email instead.

This keeps our model code clean, separates the business logic into easily testable chunks
and ensures that a user cannot accidentally be activated or deactivated more than once because the relevant methods
are simply not available when the machine isn't in the right state.

So when a user clicks their activation email, we can *transition* to the *active* state and send them an welcome email,
safe in the knowledge that they won't get multiple welcome emails if they happen to click the activation link more than once.

<h2>Example Code</h2>
First, declare our states

<pre lang="php">
/**
 * A state that applies when the user's account is pending activation
 */
class UserPendingState extends AState {
    /**
     * Activates the user's account
     */
    public function activate() {
        $machine = $this->getMachine();
        $user = $machine->getOwner();
        $user->status = "active";
        $user->save();
        $machine->transition("active");
    }
}


/**
 * A state that applies when the user's account is active
 */
class UserActiveState extends AState {
    /**
     * Deactivates the user's account
     */
    public function deactivate() {
        $machine = $this->getMachine();
        $user = $machine->getOwner();
        $user->status = "inactive";
        $user->save();
        $machine->transition("inactive");
    }
    /**
     * Raised when the state is transitioned to
     * @param AState $from the previous state
     */
    protected function afterEnter(AState $from) {
        parent::afterEnter($from);
        if ($from->name == "pending") {
            // send welcome email
        }
        else {
            // send welcome back email
        }
    }
}

/**
 * A state that applies when the user's account is deactivated
 */
class UserInactiveState extends AState {
    /**
     * Reactivates the user's account
     */
    public function reactivate() {
        $machine = $this->getMachine();
        $user = $machine->getOwner();
        $user->status = "active";
        $user->save();
        $machine->transition("active");
    }
    /**
     * Invoked before the state is transitioned to
     */
    protected function beforeEnter() {
        if ($this->getMachine()->getState()->name == "pending") {
            // invalid state transition, user cannot go pending -> deactivated
            return false;
        }
        return parent::beforeEnter();
    }
    /**
     * Raised when the state is transitioned to
     * @param AState $from the previous state
     */
    protected function afterEnter(AState $from) {
        parent::afterEnter($from);
        Yii::log($this->getMachine()->getOwner()->name." deactivated their account :(");
    }
}

</pre>

<h2>Adding the state machine to our user model</h2>

<pre lang="php">
/**
 * Your user model
 * @property string $status either pending, active or inactive
 */
class User extends CActiveRecord {
    /**
     * Declares the behaviors for the model
     * @return array the behavior configuration
     */
    public function behaviors() {
        return array(
            "activationStatus" => array(
                "class" => "AStateMachine",
                "states" => array(
                    array(
                        "class" => "UserPendingState",
                        "name" => "pending",
                    ),
                    array(
                        "class" => "UserActiveState",
                        "name" => "active",
                    ),
                    array(
                        "class" => "UserInactiveState",
                        "name" => "inactive",
                    ),
                ),
                "defaultStateName" => "pending",
                "stateName" => $this->status,
            )
        );
    }
    ...
}
</pre>

<h2>Using it</h2>

<pre lang="php">
$user = new User;
$user->name = "Test User";
$user->email = "test@example.com";
$user->activate(); // activates the user, transitions to the "active" state
$user->activate(); // throws exception, no such method

$user->deactivate(); // deactivates the user
$user->reactivate(); // reactivates the user

$user->activationStatus->deactivate(); // call the state machine directly
</pre>

<h2>Specifying states map</h2>

Often we need to check what state can become active after current. We can override
beforeExit or beforeEnter methods of AState as described.

<pre>
    /**
     * Invoked before the state is transitioned to
     */
    protected function beforeEnter() {
        if ($this->getMachine()->getState()->name == "pending") {
            // invalid state transition, user cannot go pending -> deactivated
            return false;
        }
        return parent::beforeEnter();
    }
</pre>

This approach is very flexible but it may make you crazy if you should describe a big graph of states. 
In this case you can free your time by setting *AStateMachine.checkTransitionMap* to *TRUE*
and specifying *AState.transitsTo* attribute for all states. This attribute describes which states can 
be reached from current. See example below.

<pre>
    /**
     * Represents deputy election process. 
     */
    class Election extends CActiveRecord {

        public static $statuses = array(
            Election::STATUS_PUBLISHED    => 'Published',
            Election::STATUS_REGISTRATION => 'Registration',
            Election::STATUS_ELECTION => 'Election',
            Election::STATUS_FINISHED => 'Finished',
            Election::STATUS_CANCELED => 'Canceled',
        );

        public function getStatusName() {
            return self::$statuses[$this->status];
        }

        /**
         *  ... other methods ...
         */
        public function behaviors() {
            return array(
                "state" => array(
                    "class" => "AStateMachine",
                    "states" => array(
                        array(
                            'name'=>'not_saved',
                            'transitsTo'=>'Published'
                        ),
                        array(
                            'name'=>'Published',
                            'transitsTo'=>'Registration, Canceled'
                        ),
                        array(
                            'name'=>'Registration',
                            'transitsTo'=>'Published, Election, Canceled'
                        ),
                        array(
                            'name'=>'Election',
                            'transitsTo'=>'Finished, Canceled'
                        ),
                        array(
                            'name'=>'Finished',
                            'class'=>'ElectionFinishedState'
                        ),
                        array('name'=>'Canceled')
                    ),
                    "defaultStateName" => "not_saved",
                    "checkTransitionMap" => true,
                    "stateName" => $this->statusName,
                )
            );
        }
        /**
         *  ... other methods ...
         */
    }

    class ElectionFinishedState extends AState {
    
        public function finish() {
            // ...
        }

        // ...

        public function afterEnter(AState $from) {
            parent::afterEnter($from);
            $this->finish();
        }
    }
</pre>

So lets see which states can be reached.

<pre>
    $election = new Election;
    echo $election->stateName;      // "not_saved"
    echo $election->canTransit('Published');    // true
    
    echo $election->canTransit('Registration'); // false
    echo $election->canTransit('Finished');     // false
    // ... Election and Canceled will return false too

    $election->transition('Published');
    echo $election->canTransit('Published');    // false because we already here
    
    echo $election->canTransit('Registration'); // true
    echo $election->canTransit('Election');     // false
    echo $election->canTransit('Finished');     // false
    echo $election->canTransit('Canceled');     // true

    $election->transition('Registration');
    $election->availableStates;                 // return array('Published', 'Election', 'Canceled')

    $election->transition('Election');
    $election->availableStates;                 // return array('Finished', 'Canceled')

    $election->transition('Finished');
    $election->availableStates;                 // return array()
</pre>

Here we saw *availableStates* attribute ( or *getAvailableStates()* ). This is useful
method when we want provide ability to switch state by user in an UI.