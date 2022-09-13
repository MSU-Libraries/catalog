<?php

namespace Catalog\RecordDriver;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    public function getGenre()
    {
        return $this->fields['genre_facet'] ?? [];
    }
}

