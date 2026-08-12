<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator;

use Generated\Shared\Transfer\StateMachineDefinitionValidationErrorTransfer;
use Generated\Shared\Transfer\StateMachineDefinitionValidationResponseTransfer;
use Generated\Shared\Transfer\StateMachineProcessConditionsTransfer;
use Generated\Shared\Transfer\StateMachineProcessCriteriaTransfer;
use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
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

        $rootElement = $this->validateXmlStructure($stateMachineProcessDefinitionTransfer, $stateMachineDefinitionValidationResponseTransfer);

        if ($rootElement === null) {
            return $stateMachineDefinitionValidationResponseTransfer;
        }

        $definitionValidationContext = $this->createContext($stateMachineProcessDefinitionTransfer, $rootElement);

        foreach ($this->definitionValidationRules as $definitionValidationRule) {
            $definitionValidationRule->validate($definitionValidationContext, $stateMachineDefinitionValidationResponseTransfer);
        }

        $stateMachineDefinitionValidationResponseTransfer->setIsValid($stateMachineDefinitionValidationResponseTransfer->getErrors()->count() === 0);

        return $stateMachineDefinitionValidationResponseTransfer;
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
        SimpleXMLElement $rootElement
    ): DefinitionValidationContext {
        $stateMachineProcessCollectionTransfer = $this->workflowRepository->getStateMachineProcessCollection(
            (new StateMachineProcessCriteriaTransfer())->setStateMachineProcessConditions(
                (new StateMachineProcessConditionsTransfer())->addIdStateMachineProcess(
                    $stateMachineProcessDefinitionTransfer->getStateMachineProcessOrFail()->getIdStateMachineProcessOrFail(),
                ),
            ),
        );

        $stateMachineProcessTransfer = $stateMachineProcessCollectionTransfer->getStateMachineProcesses()->getIterator()->current() ?: null;

        return new DefinitionValidationContext(
            $stateMachineProcessDefinitionTransfer,
            $rootElement,
            $stateMachineProcessTransfer?->getSubjectType(),
            $stateMachineProcessTransfer?->getProcessName(),
        );
    }
}
