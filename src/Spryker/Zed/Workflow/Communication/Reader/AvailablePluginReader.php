<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

use Generated\Shared\Transfer\StateMachineTransitionHandlerConditionsTransfer;
use Generated\Shared\Transfer\StateMachineTransitionHandlerCriteriaTransfer;
use Spryker\Zed\Workflow\Business\WorkflowFacadeInterface;

class AvailablePluginReader implements AvailablePluginReaderInterface
{
    public const string AVAILABLE_PLUGINS_COMMANDS = 'commands';

    public const string AVAILABLE_PLUGINS_CONDITIONS = 'conditions';

    public function __construct(protected WorkflowFacadeInterface $workflowFacade)
    {
    }

    /**
     * @return array<string, array<string>>
     */
    public function getAvailablePlugins(string $subjectType): array
    {
        $stateMachineTransitionHandlerCollectionTransfer = $this->workflowFacade->getStateMachineTransitionHandlerCollection(
            (new StateMachineTransitionHandlerCriteriaTransfer())->setStateMachineTransitionHandlerConditions(
                (new StateMachineTransitionHandlerConditionsTransfer())->setSubjectType($subjectType),
            ),
        );

        return [
            static::AVAILABLE_PLUGINS_COMMANDS => $stateMachineTransitionHandlerCollectionTransfer->getCommandNames(),
            static::AVAILABLE_PLUGINS_CONDITIONS => $stateMachineTransitionHandlerCollectionTransfer->getConditionNames(),
        ];
    }

    /**
     * @param array<string> $subjectTypes
     *
     * @return array<string, array<string, array<string>>>
     */
    public function getAvailablePluginsIndexedBySubjectType(array $subjectTypes): array
    {
        $availablePluginsIndexedBySubjectType = [];

        foreach ($subjectTypes as $subjectType) {
            $availablePluginsIndexedBySubjectType[$subjectType] = $this->getAvailablePlugins($subjectType);
        }

        return $availablePluginsIndexedBySubjectType;
    }
}
