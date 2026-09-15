<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Reader;

use Generated\Shared\Transfer\StateMachineTransitionHandlerCollectionTransfer;
use Generated\Shared\Transfer\StateMachineTransitionHandlerCriteriaTransfer;
use Spryker\Zed\Workflow\Business\Registry\PluginRegistryInterface;

class StateMachineTransitionHandlerReader implements StateMachineTransitionHandlerReaderInterface
{
    public function __construct(protected PluginRegistryInterface $pluginRegistry)
    {
    }

    public function getStateMachineTransitionHandlerCollection(
        StateMachineTransitionHandlerCriteriaTransfer $stateMachineTransitionHandlerCriteriaTransfer
    ): StateMachineTransitionHandlerCollectionTransfer {
        $subjectType = (string)$stateMachineTransitionHandlerCriteriaTransfer
            ->getStateMachineTransitionHandlerConditionsOrFail()
            ->getSubjectType();

        return (new StateMachineTransitionHandlerCollectionTransfer())
            ->setCommandNames($this->pluginRegistry->getCommandNamesBySubjectType($subjectType))
            ->setConditionNames($this->pluginRegistry->getConditionNamesBySubjectType($subjectType));
    }
}
