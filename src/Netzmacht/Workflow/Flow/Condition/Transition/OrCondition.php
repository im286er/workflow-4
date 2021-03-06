<?php

/**
 * @package    workflow
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2014 netzmacht creative David Molineus
 * @license    LGPL 3.0
 * @filesource
 *
 */

namespace Netzmacht\Workflow\Flow\Condition\Transition;

use Netzmacht\Workflow\Data\ErrorCollection;
use Netzmacht\Workflow\Flow\Context;
use Netzmacht\Workflow\Flow\Transition;
use Netzmacht\Workflow\Flow\Item;

/**
 * Class OrCondition matches if any of the child condition matches.
 *
 * @package Netzmacht\Workflow\Flow\Condition\Transition
 */
class OrCondition extends ConditionCollection
{
    /**
     * {@inheritdoc}
     */
    public function match(Transition $transition, Item $item, Context $context, ErrorCollection $errorCollection)
    {
        if (!$this->conditions) {
            return true;
        }

        $errors = new ErrorCollection();

        foreach ($this->conditions as $condition) {
            if ($condition->match($transition, $item, $context, $errors)) {
                return true;
            }
        }

        $errorCollection->addError('transition.condition.or.failed', array(), $errors);

        return false;
    }
}
