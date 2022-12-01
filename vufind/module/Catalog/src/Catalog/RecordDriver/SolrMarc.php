<?php

namespace Catalog\RecordDriver;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    public function getPublisher()
    {
        return (is_array($this->fields['publisher']) ? reset($this->fields['publisher']) : $this->fields['publisher']) ?? [];
    }

    public function getPhysical()
    {
        return (is_array($this->fields['physical']) ? reset($this->fields['physical']) : $this->fields['physical']) ?? [];
    }

    public function getGenre()
    {
        return !empty($this->fields['genre_facet']) ? $this->fields['genre_facet'] : [];
    }

    public function getSierraBN()
    {
        $bibnum = null;
        $marc = $this->getMarcReader();
        $marcArr907 = $marc->getFields('907', ['y']);
        foreach ($marcArr907 as $marc907) {
            $subfields = $marc907['subfields'];
            foreach ($subfields as $subfield) {
                if ($subfield['code'] == 'y' && !empty($subfield['data'])) {
                    $bibnum = ltrim($subfield['data'], '.');
                    break 2;
                }
            }
        }
        return $bibnum;
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

