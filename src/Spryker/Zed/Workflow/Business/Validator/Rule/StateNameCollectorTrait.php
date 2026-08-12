<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Business\Validator\Rule;

use SimpleXMLElement;

trait StateNameCollectorTrait
{
    /**
     * @return array<string> The distinct authored state names across all processes.
     */
    protected function collectStateNames(SimpleXMLElement $rootElement): array
    {
        $stateNames = [];
        foreach ($rootElement->children() as $xmlProcess) {
            if (!isset($xmlProcess->states)) {
                continue;
            }

            foreach ($xmlProcess->states->children() as $xmlState) {
                $stateName = (string)$xmlState->attributes()['name'];
                if (!in_array($stateName, $stateNames, true)) {
                    $stateNames[] = $stateName;
                }
            }
        }

        return $stateNames;
    }
}
