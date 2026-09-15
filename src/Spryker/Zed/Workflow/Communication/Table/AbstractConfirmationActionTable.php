<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Workflow\Communication\Table;

use Spryker\Zed\Gui\Communication\Table\AbstractTable;

/*
 * @uses \Spryker\Zed\Workflow\Presentation\_partial\action-confirmation-modal.twig
 */
abstract class AbstractConfirmationActionTable extends AbstractTable
{
    protected const string ATTRIBUTE_CONFIRM = 'data-confirm';

    protected const string ATTRIBUTE_CONFIRM_TITLE = 'data-confirm-title';

    protected const string ATTRIBUTE_CONFIRM_MESSAGE = 'data-confirm-message';

    protected const string ATTRIBUTE_CONFIRM_BUTTON = 'data-confirm-button';

    protected const string ATTRIBUTE_CONFIRM_ENABLED_VALUE = '1';

    /**
     * @return array<string, string> Already translated; keyed by HTML attribute name.
     */
    protected function buildConfirmationAttributes(string $title, string $message, string $button): array
    {
        return [
            static::ATTRIBUTE_CONFIRM => static::ATTRIBUTE_CONFIRM_ENABLED_VALUE,
            static::ATTRIBUTE_CONFIRM_TITLE => $this->translateForAttribute($title),
            static::ATTRIBUTE_CONFIRM_MESSAGE => $this->translateForAttribute($message),
            static::ATTRIBUTE_CONFIRM_BUTTON => $this->translateForAttribute($button),
        ];
    }

    protected function translateForAttribute(string $key): string
    {
        $translator = $this->getTranslator();
        $message = $translator !== null ? $translator->trans($key) : $key;

        return htmlspecialchars($message, \ENT_QUOTES, 'UTF-8');
    }
}
