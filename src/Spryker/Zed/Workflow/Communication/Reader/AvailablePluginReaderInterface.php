<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Reader;

interface AvailablePluginReaderInterface
{
    /**
     * @return array<string, array<string>>
     */
    public function getAvailablePlugins(string $subjectType): array;

    /**
     * @param array<string> $subjectTypes
     *
     * @return array<string, array<string, array<string>>>
     */
    public function getAvailablePluginsIndexedBySubjectType(array $subjectTypes): array;
}
