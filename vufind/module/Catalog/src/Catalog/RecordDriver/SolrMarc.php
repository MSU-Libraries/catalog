<?php

namespace Catalog\RecordDriver;

class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    /**
    * Takes a Marc field (ex: 950) and a list of sub fields (ex: ['a','b'])
    * and returns the values inside those fields in an array
    * (ex: ['val 1', 'val 2'])
    * 
    * args:
    *    string field: Marc field to search within
    *    array subfield: sub-fields to return
    * return:
    *   array: the values within the subfields under the field
    */
    public function getMarcField(string $field, array $subfield)
    {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $subfield);
        foreach ($marc_fields as $marc_data) {
            $subfields = $marc_data['subfields'];
	        foreach ($subfields as $subfield) {
                $vals[] = $subfield['data'];
            }
        }
        return $vals;
    }

    /**
    * Takes a Solr field and returns the contents of the field (either
    * a string or array)
    * 
    * args:
    *    string field: Name of the Solr field to get
    * return:
    *    string|array: Contents of the solr field
    */
    public function getSolrField(string $field)
    {
        $val = "";
	    if (array_key_exists($field, $this->fields) && !empty($this->fields[$field])) {
	        $val = $this->fields[$field];
        }
        return $val;
    }

    public function getNotes() 
    {
	    return array_merge($this->getMarcField('590', ['a','b','c','d','e']), 
		    $this->getMarcField('561', ['a','b','c','d','e']));
    }

    public function getPublisher()
    {
        return $this->getSolrField('publisher');
    }

    public function getPhysical()
    {
        return $this->getSolrField('physical');
    }

    public function getGenres()
    {
        return $this->getSolrField('genre_facet');
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

