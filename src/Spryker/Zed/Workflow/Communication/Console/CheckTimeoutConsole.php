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
 * Scheduled timeout check for DB-authored state machines. Discovery (which state machines to scan) lives
 * behind the facade.
 *
 * @method \Spryker\Zed\Workflow\Business\WorkflowFacadeInterface getFacade()
 * @method \Spryker\Zed\Workflow\Communication\WorkflowCommunicationFactory getFactory()
 */
class CheckTimeoutConsole extends Console
{
    /**
     * @var string
     */
    protected const COMMAND_NAME = 'workflow:check-timeout';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME)
            ->setDescription('Executes event-less timeout transitions for active state machines.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $affectedItemCount = $this->getFacade()->checkDynamicTimeouts();

        $output->writeln(sprintf('Affected items: %d', $affectedItemCount));

        return static::CODE_SUCCESS;
    }
}
