<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Dependency\Plugin;

interface StateMachineProcessTriggerPluginInterface
{
    /**
     * Specification:
     * - Event name offered as a per-process trigger (for example Entity.spy_customer.update).
     *
     * @api
     *
     * @return string
     */
    public function getEventName(): string;

    /**
     * Specification:
     * - Short, user-friendly label for the event, shown as the primary text in the Back Office per-process
     *   trigger picker. For example "Customer created".
     *
     * @api
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Specification:
     * - Subject type this trigger applies to.
     *
     * @api
     *
     * @return string
     */
    public function getSubjectType(): string;

    /**
     * Specification:
     * - User-friendly description of when the event fires, shown in the dropdown per-process trigger picker.
     *
     * @api
     *
     * @return string
     */
    public function getDescription(): string;
}
