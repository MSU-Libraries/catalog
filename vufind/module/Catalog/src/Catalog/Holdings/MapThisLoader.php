<?php

/**
 * Prepares data for the Map This button
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Map_This
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace Catalog\Holdings;

/**
 * Class to hold data for the Map This button
 *
 * @category VuFind
 * @package  Map_This
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class MapThisLoader extends AbstractItemLoader
{
    /**
     * Get the call number for the record as it would be printed on the
     * book spine label
     *
     * @param string $item_id Item to filter the result for
     *
     * @return string The description string
     */
    public function getCallNumberLabel($item_id = null)
    {
        $item_id = $this->getItemId($item_id);
        $item = $this->getItem($item_id);

        $callnum = '';
        if ($item['callnumber'] ?? false) {
            $callnum .= ($item['callnumber_prefix'] ? $item['callnumber_prefix'] . ' ' : '') .
                        $item['callnumber'];
        }

        return $callnum;
    }

    /**
     * Get the Arc GIS portal URL from the msul config
     *
     * @return string The Arc GIS portal URL
     */
    public function getPortalUrl()
    {
        $msulConfig = $this->getMsulConfig();
        return $msulConfig['ArcGis']['portal_url'] ?? '';
    }

    /**
     * Get the Arc GIS map ID from the msul config
     *
     * @return string The Arc GIS map ID
     */
    public function getMapId()
    {
        $msulConfig = $this->getMsulConfig();
        return $msulConfig['ArcGis']['map_id'] ?? '';
    }

    /**
     * Get the Arc GIS building ID from the msul config
     *
     * @return string The Arc GIS building ID
     */
    public function getBuildingId()
    {
        $msulConfig = $this->getMsulConfig();
        return $msulConfig['ArcGis']['building_id'] ?? '';
    }

    /**
     * Get the Arc GIS floor ID from the msul config
     * to be used when we can't determine the correct
     * floor for an item.
     *
     * @return string The Arc GIS default floor ID
     */
    public function getDefaultFloorId()
    {
        $msulConfig = $this->getMsulConfig();
        return $msulConfig['ArcGis']['default_floor_id'] ?? '';
    }
}
