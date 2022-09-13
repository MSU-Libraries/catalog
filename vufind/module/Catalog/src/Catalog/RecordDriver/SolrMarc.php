<?php

namespace Catalog\RecordDriver;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    public function getPublisher()
    {
        return $this->fields['publisher'] ?? [];
    }
}


