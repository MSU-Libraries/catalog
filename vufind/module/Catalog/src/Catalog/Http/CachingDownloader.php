<?php

/**
 * Caching downloader extension.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Http
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Catalog\Http;

use DOMDocument;

/**
 * Caching downloader extension to download and cache XML metadata.
 *
 * @category VuFind
 * @package  Http
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CachingDownloader extends \VuFind\Http\CachingDownloader
{
    /**
     * Download a resource using the cache in the background,
     * including decoding for XML.
     *
     * @param string $url    URL
     * @param array  $params Request parameters (e.g. additional headers)
     *
     * @return DOMDocument|bool Document on success, false on failure
     */
    public function downloadXML($url, $params = [])
    {
        $decodeXML = function (\Laminas\Http\Response $response, $url) {
            $dom = new DOMDocument();
            return $dom->loadXML($response->getBody()) ? $dom : false;
        };
        return $this->download($url, $params, $decodeXML);
    }
}
