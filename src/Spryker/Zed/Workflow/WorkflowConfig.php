<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow;

use Generated\Shared\Transfer\DataImporterDataSourceConfigurationTransfer;
use Spryker\Zed\Kernel\AbstractBundleConfig;

class WorkflowConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     *  - Import type identifier for the workflow data import.
     *
     * @api
     *
     * @var string
     */
    public const IMPORT_TYPE_WORKFLOW = 'workflow';

    /**
     * Specification:
     *  - Value stored in `spy_state_machine_process.type` that marks a process as DB-authored.
     *  - Every read in this module scopes its queries to this type so the module never touches file-based
     *    processes. Changing it re-labels the whole family of DB-authored processes: existing rows keep the
     *    old value and become invisible to the module until migrated, so treat it as a persisted contract.
     *
     * @api
     *
     * @var string
     */
    public const PROCESS_TYPE_DATABASE = 'database';

    /**
     * Specification:
     *  - Value stored in `spy_state_machine_process_definition.status` for the single active version of a
     *    process. Exactly one version per process may hold this status (exclusive activation).
     *  - Version resolution, the scheduled condition/timeout discovery and new-instance creation all select
     *    the active version by this value.
     *
     * @api
     *
     * @var string
     */
    public const PROCESS_DEFINITION_STATUS_ACTIVE = 'active';

    /**
     * Specification:
     *  - Value stored in `spy_state_machine_process_definition.status` for every non-active version (draft
     *    or superseded). Definitions are always persisted with this status; exclusive activation is the only
     *    path that promotes one to active, and deactivation writes this value back.
     *  - Changing it invalidates every persisted "inactive" marker until the data is migrated, so it is a
     *    persisted contract, not a free label.
     *
     * @api
     *
     * @var string
     */
    public const PROCESS_DEFINITION_STATUS_INACTIVE = 'inactive';

    /**
     * Specification:
     *  - Returns the data importer data source configuration for the workflow data import.
     *  - Used by `DataImportFactoryTrait::getCsvDataImporterFromConfig()` to create the CSV reader.
     *
     * @api
     */
    public function getWorkflowDataImporterDataSourceConfiguration(): DataImporterDataSourceConfigurationTransfer
    {
        return (new DataImporterDataSourceConfigurationTransfer())
            ->setImportType(static::IMPORT_TYPE_WORKFLOW)
            ->setModuleName('Workflow')
            ->setFileName('workflow.csv');
    }
}
