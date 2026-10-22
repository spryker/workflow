<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Form;

use Spryker\Zed\Kernel\Communication\Form\AbstractType;

/**
 * This form is intentionally empty: it exists only to add CSRF protection to the manual event trigger
 * of a workflow instance.
 *
 * @method \Spryker\Zed\Workflow\WorkflowConfig getConfig()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class InstanceEventTriggerForm extends AbstractType
{
}
