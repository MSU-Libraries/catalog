<?php

namespace Catalog\RecordTab;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

class Description extends \VuFind\RecordTab\Description implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->translate('Further Description');
    }
}