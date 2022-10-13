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

    public function getLocations()
    {
        $locs = [];
        $marc = $this->getMarcReader();
        $marcArr952 = $marc->getFields('952', ['b','c','d']);
        foreach ($marcArr952 as $marc952) {
            $subfields = $marc952['subfields'];
            $sfvals = [];
            foreach ($subfields as $subfield) {
                $sfvals[$subfield['code']] = $subfield['data'];
            }
            if ($sfvals['b'] == "Michigan State University") {
                $locs[] = empty($sfvals['d']) ? $sfvals['c'] : $sfvals['d'];
            }
        }
        return $locs;
    }

    public function getLocation() {
        return $this->getLocations()[0] ?? '';
    }

}

