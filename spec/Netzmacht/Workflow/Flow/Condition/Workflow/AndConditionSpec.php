<?php

namespace spec\Netzmacht\Workflow\Flow\Condition\Workflow;

use Netzmacht\Workflow\Flow\Workflow;
use Netzmacht\Workflow\Data\Entity;
use Netzmacht\Workflow\Flow\Condition\Workflow\Condition;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class AndConditionSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType('Netzmacht\Workflow\Flow\Condition\Workflow\AndCondition');
    }

    function it_is_a_condition_collection()
    {
        $this->shouldHaveType('Netzmacht\Workflow\Flow\Condition\Workflow\ConditionCollection');
    }

    function it_matches_if_all_children_matches(
        Condition $conditionA,
        Condition $conditionB,
        Workflow $workflow,
        Entity $entity
    ) {
        $conditionA->match($workflow, $entity)->willReturn(true);
        $conditionB->match($workflow, $entity)->willReturn(true);

        $this->addCondition($conditionA);
        $this->addCondition($conditionB);

        $this->match($workflow, $entity)->shouldReturn(true);
    }

    function it_does_not_match_if_one_child_does_not(
        Condition $conditionA,
        Condition $conditionB,
        Workflow $workflow,
        Entity $entity
    ) {
        $conditionA->match($workflow, $entity)->willReturn(true);
        $conditionB->match($workflow, $entity)->willReturn(false);

        $this->addCondition($conditionA);
        $this->addCondition($conditionB);

        $this->match($workflow, $entity)->shouldReturn(false);
    }

    function it_matches_if_no_children_exists(Workflow $workflow, Entity $entity)
    {
        $this->match($workflow, $entity)->shouldReturn(true);
    }
}
