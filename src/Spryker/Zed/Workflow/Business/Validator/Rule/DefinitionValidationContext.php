<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use Generated\Shared\Transfer\StateMachineProcessDefinitionTransfer;
use SimpleXMLElement;

/**
 * Read-only inputs shared by the validation rules. Built once by the orchestrator (parsed XML, resolved
 * subject type / process name) so each rule only expresses its own check.
 */
class DefinitionValidationContext
{
    public function __construct(
        protected StateMachineProcessDefinitionTransfer $stateMachineProcessDefinitionTransfer,
        protected SimpleXMLElement $rootElement,
        protected ?string $subjectType,
        protected ?string $processName
    ) {
    }

    public function getStateMachineProcessDefinition(): StateMachineProcessDefinitionTransfer
    {
        return $this->stateMachineProcessDefinitionTransfer;
    }

    public function getRootElement(): SimpleXMLElement
    {
        return $this->rootElement;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getProcessName(): ?string
    {
        return $this->processName;
    }
}
