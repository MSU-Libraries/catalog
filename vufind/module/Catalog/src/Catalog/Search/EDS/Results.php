<?php

/**
 * EDS Search Result
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  EDS_Result
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Search\EDS;

use VuFindSearch\Command\SearchCommand;

/**
 * This will no longer be needed with Vufind 9.0.3.
 *
 * @category VuFind
 * @package  EDS_Result
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Results extends \VuFind\Search\EDS\Results
{
    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        $query  = $this->getParams()->getQuery();
        $limit  = $this->getParams()->getLimit();
        $offset = $this->getStartRecord() - 1;
        $params = $this->getParams()->getBackendParameters();
        $command = new SearchCommand(
            $this->backendId,
            $query,
            $offset,
            $limit,
            $params
        );
        $collection = $this->getSearchService()->invoke($command)
            ->getResult();
        if (null != $collection) {
            $this->responseFacets = $collection->getFacets();
            $this->resultTotal = $collection->getTotal();

            // Add fake date facets if flagged earlier; this is necessary in order
            // to display the date range facet control in the interface.
            $dateFacets = $this->getParams()->getDateFacetSettings();
            if (!empty($dateFacets)) {
                foreach ($dateFacets as $dateFacet) {
                    $this->responseFacets[$dateFacet] = [""];
                }
            }

            // Construct record drivers for all the items in the response:
            $this->results = $collection->getRecords();
        }
    }
}
