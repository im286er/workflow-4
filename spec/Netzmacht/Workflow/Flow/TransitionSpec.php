<?php

namespace spec\Netzmacht\Workflow\Flow;

use Netzmacht\Workflow\Security\Permission;
use Netzmacht\Workflow\Flow\Exception\ActionFailedException;
use Netzmacht\Workflow\Flow\Item;
use Netzmacht\Workflow\Data\EntityId;
use Netzmacht\Workflow\Data\ErrorCollection;
use Netzmacht\Workflow\Flow\Action;
use Netzmacht\Workflow\Flow\Condition\Transition\Condition;
use Netzmacht\Workflow\Flow\Context;
use Netzmacht\Workflow\Flow\State;
use Netzmacht\Workflow\Flow\Step;
use Netzmacht\Workflow\Flow\Transition;
use Netzmacht\Workflow\Flow\Workflow;
use Netzmacht\Workflow\Form\Form;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

/**
 * Class TransitionSpec
 * @package spec\Netzmacht\Workflow\Flow
 * @mixin Transition
 */
class TransitionSpec extends ObjectBehavior
{
    const NAME = 'transition_name';

    const ERROR_COLLECTION_CLASS = 'Netzmacht\Workflow\Data\ErrorCollection';

    protected static $entity = array('id' => 5);

    function let()
    {
        $this->beConstructedWith(static::NAME);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Netzmacht\Workflow\Flow\Transition');
    }

    function it_behaves_like_base()
    {
        $this->shouldImplement('Netzmacht\Workflow\Base');
    }

    function it_knows_workflow(Workflow $workflow)
    {
        $this->setWorkflow($workflow)->shouldReturn($this);
        $this->getWorkflow()->shouldReturn($workflow);
    }

    function it_has_actions(Action $action)
    {
        $this->addAction($action)->shouldReturn($this);
        $this->getActions()->shouldReturn(array($action));
    }

    function it_has_post_actions(Action $action)
    {
        $this->addPostAction($action)->shouldReturn($this);
        $this->getPostActions()->shouldReturn(array($action));
    }

    function it_has_a_target_step(Step $step)
    {
        $this->setStepTo($step)->shouldReturn($this);
        $this->getStepTo()->shouldReturn($step);
    }

    function it_builds_the_form(Form $form, Item $item, Action $action)
    {
        $this->addAction($action);
        $this->buildForm($form, $item)->shouldReturn($this);

        $action->buildForm($form, $item)->shouldBeCalled();
    }

    function it_knows_if_input_data_is_not_required(Action $action, Item $item)
    {
        $this->isInputRequired($item)->shouldReturn(false);

        $action->isInputRequired($item)->willReturn(false);
        $this->addAction($action);

        $this->isInputRequired($item)->shouldReturn(false);
    }

    function it_knows_if_input_data_is_required(Action $action, Item $item)
    {
        $this->isInputRequired($item)->shouldReturn(false);

        $action->isInputRequired($item)->willReturn(true);
        $this->addAction($action);

        $this->isInputRequired($item)->shouldReturn(true);
    }

    function it_checks_a_precondition(Condition $condition, Item $item, Context $context, ErrorCollection $errorCollection)
    {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);

        $this->addPreCondition($condition)->shouldReturn($this);
        $this->checkPreCondition($item, $context, $errorCollection)->shouldReturn(true);
    }

    function it_checks_a_precondition_failing(
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(false);
        $errorCollection->addError(Argument::cetera())->shouldBeCalled();

        $this->addPreCondition($condition)->shouldReturn($this);
        $this->checkPreCondition($item, $context, $errorCollection)->shouldReturn(false);
    }

    function it_gets_condition(Condition $condition)
    {
        $this->getCondition()->shouldReturn(null);
        $this->addCondition($condition);
        $this->getCondition()->shouldHaveType('Netzmacht\Workflow\Flow\Condition\Transition\AndCondition');
    }

    function it_gets_pre_condition(Condition $condition)
    {
        $this->getPreCondition()->shouldReturn(null);
        $this->addPreCondition($condition);
        $this->getPreCondition()->shouldHaveType('Netzmacht\Workflow\Flow\Condition\Transition\AndCondition');
    }

    function it_checks_a_condition(Condition $condition, Item $item, Context $context, ErrorCollection $errorCollection)
    {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);

        $this->addCondition($condition)->shouldReturn($this);
        $this->checkCondition($item, $context, $errorCollection)->shouldReturn(true);
    }

    function it_checks_a_condition_failing(
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(false);
        $errorCollection->addError(Argument::cetera())->shouldBeCalled();

        $this->addCondition($condition)->shouldReturn($this);
        $this->checkCondition($item, $context, $errorCollection)->shouldReturn(false);
    }

    function it_is_allowed_by_conditions(
        Condition $preCondition,
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);
        $preCondition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);

        $this->addCondition($condition);
        $this->addPreCondition($condition);

        $this->isAllowed($item, $context, $errorCollection)->shouldReturn(true);
    }

    function it_is_not_allowed_by_failing_pre_condition(
        Condition $preCondition,
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);
        $preCondition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(false);

        $errorCollection->addError(Argument::cetera())->shouldBeCalled();

        $this->addCondition($condition);
        $this->addPreCondition($preCondition);

        $this->isAllowed($item, $context, $errorCollection)->shouldReturn(false);
    }

    function it_is_not_allowed_by_failing_condition(
        Condition $preCondition,
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(false);
        $preCondition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);

        $errorCollection->addError(Argument::cetera())->shouldBeCalled();

        $this->addCondition($condition);
        $this->addPreCondition($preCondition);

        $this->isAllowed($item, $context, $errorCollection)->shouldReturn(false);
    }

    function it_is_available_when_passing_conditions(
        Condition $preCondition,
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);
        $preCondition->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))->willReturn(true);

        $this->addCondition($condition);
        $this->addPreCondition($preCondition);

        $this->isAvailable($item, $context, $errorCollection)->shouldReturn(true);
    }

    function it_is_not_available_when_condition_fails(
        Condition $preCondition,
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition
            ->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))
            ->willReturn(false);

        $errorCollection->addError(Argument::cetera())->shouldBeCalled();

        $preCondition
            ->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))
            ->willReturn(true);

        $this->addCondition($condition);
        $this->addPreCondition($preCondition);

        $this
            ->isAvailable($item, $context, $errorCollection)
            ->shouldReturn(false);
    }

    function it_is_not_available_when_precondition_fails(
        Condition $preCondition,
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $condition
            ->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))
            ->willReturn(true);

        $preCondition
            ->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))
            ->willReturn(false);

        $errorCollection->addError(Argument::cetera())->shouldBeCalled();

        $this->addCondition($condition);
        $this->addPreCondition($preCondition);

        $this
            ->isAvailable($item, $context, $errorCollection)
            ->shouldReturn(false);
    }

    function it_only_recognize_precondition_when_input_is_required(
        Condition $preCondition,
        Condition $condition,
        Item $item,
        Context $context,
        ErrorCollection $errorCollection,
        Action $action
    ) {
        $preCondition
            ->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))
            ->willReturn(true);

        $condition
            ->match($this, $item, $context, Argument::type(self::ERROR_COLLECTION_CLASS))
            ->willReturn(false);

        $action->isInputRequired($item)->willReturn(true);
        $this->addAction($action);


        $this->addCondition($condition);
        $this->addPreCondition($preCondition);

        $this->isAvailable($item, $context, $errorCollection)->shouldReturn(true);
    }

    function it_executes_actions(
        Item $item,
        Context $context,
        ErrorCollection $errorCollection,
        Action $action
    )
    {
        $action->transit($this, $item, $context)->shouldBeCalled();
        $this->addAction($action);

        $this->executeActions($item, $context, $errorCollection)->shouldReturn(true);
    }

    function it_catches_action_failed_exceptions_during_action_execution(
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $this->addAction(new ThrowingAction());

        $errorCollection->addError(Argument::type('string'), Argument::type('array'))->shouldBeCalled();
        $context->getProperties()->willReturn(array());

        $this->executeActions($item, $context, $errorCollection);
    }

    function it_executes_post_actions(
        Item $item,
        Context $context,
        ErrorCollection $errorCollection,
        Action $action
    )
    {
        $action->transit($this, $item, $context)->shouldBeCalled();
        $this->addPostAction($action);

        $this->executePostActions($item, $context, $errorCollection)->shouldReturn(true);
    }

    function it_catches_action_failed_exceptions_during_post_action_execution(
        Item $item,
        Context $context,
        ErrorCollection $errorCollection
    ) {
        $this->addPostAction(new ThrowingAction());

        $errorCollection->addError(Argument::type('string'), Argument::type('array'))->shouldBeCalled();
        $context->getProperties()->willReturn(array());

        $this->executePostActions($item, $context, $errorCollection);
    }

    function it_has_permission(Permission $permission)
    {
        $permission->equals($permission)->willReturn(true);

        $this->setPermission($permission)->shouldReturn($this);
        $this->hasPermission($permission)->shouldReturn(true);
        $this->getPermission()->shouldReturn($permission);
    }

    function it_does_not_require_a_permission(Permission $permission)
    {
        $this->getPermission()->shouldReturn(null);
        $this->hasPermission($permission)->shouldReturn(false);
    }
}

class ThrowingAction implements Action
{
    public function isInputRequired(Item $item)
    {
    }

    public function buildForm(Form $form, Item $item)
    {
    }

    public function transit(Transition $transition, Item $item, Context $context)
    {
        throw new ActionFailedException();
    }
}
