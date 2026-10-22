<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator;

use ArrayObject;
use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use Generated\Shared\Transfer\StateMachineProcessTransfer;
use SimpleXMLElement;
use Spryker\Zed\Workflow\Business\Validator\Rule\DefinitionValidationContext;
use Spryker\Zed\Workflow\Persistence\WorkflowRepositoryInterface;
use Throwable;

class DefinitionValidator implements DefinitionValidatorInterface
{
    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_INVALID_XML = 'invalid_xml';

    /**
     * @var string
     */
    protected const VALIDATION_ERROR_TYPE_EMPTY_DEFINITION = 'empty_definition';

    protected const string VALIDATION_ERROR_TYPE_PROCESS_NOT_FOUND = 'process_not_found';

    protected const string MESSAGE_PROCESS_NOT_FOUND = 'The process with the ID "%d" does not exist.';

    /**
     * @param array<\Spryker\Zed\Workflow\Business\Validator\Rule\DefinitionValidationRuleInterface> $definitionValidationRules
     */
    public function __construct(
        protected WorkflowRepositoryInterface $workflowRepository,
        protected array $definitionValidationRules
    ) {
    }

    public function validate(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer
    ): StateMachineDefinitionValidationResponseTransfer {
        $stateMachineDefinitionValidationResponseTransfer = (new StateMachineDefinitionValidationResponseTransfer())->setIsValid(false);
        $stateMachineProcessTransfer = $this->findStateMachineProcess($stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail());

        if ($stateMachineProcessTransfer === null) {
            return $stateMachineDefinitionValidationResponseTransfer->addError(
                (new StateMachineDefinitionValidationErrorTransfer())
                    ->setType(static::VALIDATION_ERROR_TYPE_PROCESS_NOT_FOUND)
                    ->setMessage(sprintf(static::MESSAGE_PROCESS_NOT_FOUND, $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcess())),
            );
        }

        $rootElement = $this->validateXmlStructure($stateMachineProcessDefinitionTransfer, $stateMachineDefinitionValidationResponseTransfer);

        if ($rootElement === null) {
            return $stateMachineDefinitionValidationResponseTransfer;
        }

        $definitionValidationContext = $this->createContext($stateMachineProcessDefinitionTransfer, $stateMachineProcessTransfer, $rootElement);

        foreach ($this->definitionValidationRules as $definitionValidationRule) {
            $definitionValidationRule->validate($definitionValidationContext, $stateMachineDefinitionValidationResponseTransfer);
        }

        $stateMachineDefinitionValidationResponseTransfer->setIsValid($stateMachineDefinitionValidationResponseTransfer->getErrors()->count() === 0);

        return $stateMachineDefinitionValidationResponseTransfer;
    }

    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer> $stateMachineProcessDefinitionTransfers
     */
    public function validateCollection(ArrayObject $stateMachineProcessDefinitionTransfers): StateMachineDefinitionValidationResponseTransfer
    {
        $stateMachineDefinitionValidationResponseTransfer = new StateMachineDefinitionValidationResponseTransfer();

        foreach ($stateMachineProcessDefinitionTransfers as $stateMachineProcessDefinitionTransfer) {
            foreach ($this->validate($stateMachineProcessDefinitionTransfer)->getErrors() as $stateMachineDefinitionValidationErrorTransfer) {
                $stateMachineDefinitionValidationResponseTransfer->addError($stateMachineDefinitionValidationErrorTransfer);
            }
        }

        return $stateMachineDefinitionValidationResponseTransfer->setIsValid($stateMachineDefinitionValidationResponseTransfer->getErrors()->count() === 0);
    }

    protected function validateXmlStructure(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        StateMachineDefinitionValidationResponseTransfer $stateMachineDefinitionValidationResponseTransfer
    ): ?SimpleXMLElement {
        $definition = $stateMachineProcessDefinitionTransfer->getDefinition();

        if ($definition === null || trim($definition) === '') {
            $stateMachineDefinitionValidationResponseTransfer->addError(
                (new StateMachineDefinitionValidationErrorTransfer())
                    ->setType(static::VALIDATION_ERROR_TYPE_EMPTY_DEFINITION)
                    ->setMessage('The definition must not be empty.'),
            );

            return null;
        }

        try {
            return new SimpleXMLElement($definition);
        } catch (Throwable) {
            $stateMachineDefinitionValidationResponseTransfer->addError(
                (new StateMachineDefinitionValidationErrorTransfer())
                    ->setType(static::VALIDATION_ERROR_TYPE_INVALID_XML)
                    ->setMessage('The definition is not valid XML.'),
            );
        }

         return null;
    }

    protected function createContext(
        StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        StateMachineProcessTransfer $stateMachineProcessTransfer,
        SimpleXMLElement $rootElement
    ): DefinitionValidationContext {
        return new DefinitionValidationContext(
            $stateMachineProcessDefinitionTransfer,
            $rootElement,
            $stateMachineProcessTransfer->getSubjectType(),
            $stateMachineProcessTransfer->getProcessName(),
        );
    }

    protected function findStateMachineProcess(StateMachineProcessTransfer $stateMachineProcessTransfer): ?StateMachineProcessTransfer
    {
        $idStateMachineProcess = $stateMachineProcessTransfer->getIdStateMachineProcess();

        if ($idStateMachineProcess === null) {
            return $stateMachineProcessTransfer;
        }

        return $this->workflowRepository->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())->addIdStateMachineProcess($idStateMachineProcess),
            ),
        )->getStateMachineProcesses()->getIterator()->current();
    }
}
