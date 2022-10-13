<?php

namespace Catalog\RecordDriver;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    public function getPublisher()
    {
        return is_array($this->fields['publisher']) ? reset($this->fields['publisher']) : $this->fields['publisher'] ?? [];
    }

    public function getPhysical()
    {
        return is_array($this->fields['physical']) ? reset($this->fields['physical']) : $this->fields['physical'] ?? [];
    }

    public function getGenre()
    {
        return is_array($this->fields['genre_facet']) ? reset($this->fields['genre_facet']) : $this->fields['genre_facet'] ?? [];
    }

    public function getLocation()
    {
        $loc = null;
        $marc952 = $this->getFieldArray('952', ['b','d'], false);
        if ($marc952[0] == "Michigan State University") {
            $loc = $marc952[1];
        }
        return $loc;
    }

}

