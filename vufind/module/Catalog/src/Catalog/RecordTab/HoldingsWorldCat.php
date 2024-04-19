<?php

namespace Catalog\RecordTab;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

class HoldingsWorldCat extends \VuFind\RecordTab\HoldingsWorldCat implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->translate('holdings_access');
    }
}
