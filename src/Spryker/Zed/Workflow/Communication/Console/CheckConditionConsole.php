<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scheduled, event-less condition check for DB-authored state machines. Discovery (which state machines
 * and which versions to scan) and the skip-finished filtering live behind the facade.
 *
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class CheckConditionConsole extends Console
{
    /**
     * @var string
     */
    protected const COMMAND_NAME = 'workflow:check-condition';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME)
            ->setDescription('Executes event-less condition transitions for active state machines.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $affectedItemCount = $this->getFacade()->checkDynamicConditions();

        $output->writeln(sprintf('Affected items: %d', $affectedItemCount));

        return static::CODE_SUCCESS;
    }
}
