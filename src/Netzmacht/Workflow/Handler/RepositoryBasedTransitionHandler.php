<?php

/**
 * @package    dev
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2014 netzmacht creative David Molineus
 * @license    LGPL 3.0
 * @filesource
 *
 */

namespace Netzmacht\Workflow\Handler;

use Netzmacht\Workflow\Data\EntityRepository;
use Netzmacht\Workflow\Data\ErrorCollection;
use Netzmacht\Workflow\Flow\Context;
use Netzmacht\Workflow\Flow\Exception\WorkflowException;
use Netzmacht\Workflow\Flow\Item;
use Netzmacht\Workflow\Flow\State;
use Netzmacht\Workflow\Flow\Workflow;
use Netzmacht\Workflow\Form\Form;
use Netzmacht\Workflow\Data\StateRepository;
use Netzmacht\Workflow\Transaction\TransactionHandler;

/**
 * Class RepositoryBasedTransitionHandler handles the transition to another step in the workflow.
 *
 * It uses an collection repository approach to store entities.
 *
 * @package Netzmacht\Workflow
 */
class RepositoryBasedTransitionHandler implements TransitionHandler
{
    /**
     * The given entity.
     *
     * @var Item
     */
    private $item;

    /**
     * The current workflow.
     *
     * @var Workflow
     */
    private $workflow;

    /**
     * The transition name which will be handled.
     *
     * @var string
     */
    private $transitionName;

    /**
     * The form object for user input.
     *
     * @var Form
     */
    private $form;

    /**
     * Validation state.
     *
     * @var bool
     */
    private $validated;

    /**
     * The entity repository.
     *
     * @var EntityRepository
     */
    private $entityRepository;

    /**
     * The state repository.
     *
     * @var StateRepository
     */
    private $stateRepository;

    /**
     * The transaction handler.
     *
     * @var TransactionHandler
     */
    private $transactionHandler;

    /**
     * The transition context.
     *
     * @var Context
     */
    private $context;

    /**
     * Error collection of errors occurred during transition handling.
     *
     * @var ErrorCollection
     */
    private $errorCollection;

    /**
     * Transition handler listener.
     *
     * @var Listener
     */
    private $listener;

    /**
     * Construct.
     *
     * @param Item               $item               The item.
     * @param Workflow           $workflow           The current workflow.
     * @param string             $transitionName     The transition to be handled.
     * @param EntityRepository   $entityRepository   EntityRepository which stores changes.
     * @param StateRepository    $stateRepository    StateRepository which stores new states.
     * @param TransactionHandler $transactionHandler TransactionHandler take care of transactions.
     * @param Listener           $listener           Transition handler dispatcher.
     *
     * @throws WorkflowException If invalid transition name is given.
     */
    public function __construct(
        Item $item,
        Workflow $workflow,
        $transitionName,
        EntityRepository $entityRepository,
        StateRepository $stateRepository,
        TransactionHandler $transactionHandler,
        Listener $listener
    ) {
        $this->item               = $item;
        $this->workflow           = $workflow;
        $this->transitionName     = $transitionName;
        $this->entityRepository   = $entityRepository;
        $this->stateRepository    = $stateRepository;
        $this->transactionHandler = $transactionHandler;
        $this->context            = new Context();
        $this->errorCollection    = new ErrorCollection();
        $this->listener           = $listener;

        $this->guardAllowedTransition($transitionName);
    }


    /**
     * {@inheritdoc}
     */
    public function getWorkflow()
    {
        return $this->workflow;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem()
    {
        return $this->item;
    }

    /**
     * {@inheritdoc}
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransition()
    {
        if ($this->isWorkflowStarted()) {
            return $this->workflow->getTransition($this->transitionName);
        }

        return $this->workflow->getStartTransition();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentStep()
    {
        if ($this->isWorkflowStarted()) {
            $stepName = $this->item->getCurrentStepName();

            return $this->workflow->getStep($stepName);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function isWorkflowStarted()
    {
        return $this->item->isWorkflowStarted();
    }

    /**
     * {@inheritdoc}
     */
    public function isInputRequired()
    {
        return $this->getTransition()->isInputRequired($this->item);
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorCollection()
    {
        return $this->errorCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Form $form)
    {
        $this->buildForm($form);

        if (!$this->validated) {
            if ($this->isInputRequired($this->item)) {
                $this->validated = $this->getForm()->validate($this->context);

                if (!$this->validated) {
                    $this->errorCollection->addError(
                        'transition.validate.form.failed',
                        array(),
                        $form->getErrorCollection()
                    );
                }
            } else {
                $this->validated = true;
            }
        }

        return $this->listener->onValidate(
            $form,
            $this->validated,
            $this->workflow,
            $this->item,
            $this->context,
            $this->getTransition()->getName()
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception If something went wrong during action execution.
     */
    public function transit()
    {
        $this->guardValidated();

        $this->transactionHandler->begin();

        try {
            $this->listener->onPreTransit(
                $this->workflow,
                $this->item,
                $this->context,
                $this->getTransition()->getName()
            );

            $state = $this->executeTransition();

            $this->listener->onPostTransit($this->workflow, $this->item, $this->context, $state);

            $this->stateRepository->add($state);
            $this->entityRepository->add($this->item->getEntity());
        } catch (\Exception $e) {
            $this->transactionHandler->rollback();

            throw $e;
        }

        $this->transactionHandler->commit();

        return $state;
    }

    /**
     * Execute the transition.
     *
     * @return State
     */
    private function executeTransition()
    {
        $transition = $this->getTransition();

        if ($this->isWorkflowStarted()) {
            return $transition->transit($this->item, $this->context, $this->errorCollection);
        }

        return $transition->start($this->item, $this->context, $this->errorCollection);
    }

    /**
     * Build transition form.
     *
     * @param Form $form The form being built.
     *
     * @return void
     */
    private function buildForm(Form $form)
    {
        $this->form = $form;
        $this->getTransition()->buildForm($this->form, $this->item);

        $this->listener->onBuildForm(
            $form,
            $this->workflow,
            $this->item,
            $this->context,
            $this->getTransition()->getName()
        );
    }

    /**
     * Guard that transition was validated before.
     *
     * @throws WorkflowException If transition.
     *
     * @return void
     */
    private function guardValidated()
    {
        if ($this->validated === null) {
            throw new WorkflowException('Transition was not validated so far.');
        } elseif (!$this->validated) {
            throw new WorkflowException('Transition is in a invalid state and can\'t be processed.');
        }
    }

    /**
     * Guard that requested transition is allowed.
     *
     * @param string $transitionName Transition to be processed.
     *
     * @throws WorkflowException If Transition is not allowed.
     *
     * @return void
     */
    private function guardAllowedTransition($transitionName)
    {
        if (!$this->isWorkflowStarted()) {
            if (!$transitionName || $transitionName === $this->getWorkflow()->getStartTransition()->getName()) {
                return;
            }

            throw new WorkflowException(
                sprintf(
                    'Not allowed to process transition "%s". Workflow "%s" not started for item "%s"',
                    $transitionName,
                    $this->workflow->getName(),
                    $this->item->getEntityId()
                )
            );
        }

        $step = $this->getCurrentStep();

        if (!$step->isTransitionAllowed($transitionName)) {
            throw new WorkflowException(
                sprintf(
                    'Not allowed to process transition "%s". Transition is not allowed in step "%s"',
                    $transitionName,
                    $step->getName()
                )
            );
        }
    }
}
