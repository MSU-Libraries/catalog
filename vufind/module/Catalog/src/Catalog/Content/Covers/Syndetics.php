<?php

/**
 * Syndetics cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Content
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\Content\Covers;

use DOMDocument;

/**
 * Syndetics cover content loader extension improving the Syndetics implementation.
 *
 * @category VuFind
 * @package  Content
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Syndetics extends \VuFind\Content\Covers\Syndetics implements \VuFind\Http\CachingDownloaderAwareInterface
{
    use \VuFind\Http\CachingDownloaderAwareTrait;

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        if (!isset($ids['isbn']) && !isset($ids['issn']) && !isset($ids['oclc']) && !isset($ids['upc'])) {
            return false;
        }
        $baseUrl = $this->getBaseUrl($key, $ids);
        $xml = $this->getMetadataXML($baseUrl);
        $filename = $this->getImageFilename($xml, $size);
        if ($filename == false) {
            return false;
        }
        return $this->getImageUrl($baseUrl, $filename);
    }

    /**
     * Return the base Syndetics URL for both the metadata and image URLs.
     *
     * @param string $key API key
     * @param array  $ids Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string Base URL
     */
    protected function getBaseUrl($key, $ids)
    {
        $url = $this->useSSL
            ? 'https://secure.syndetics.com' : 'http://syndetics.com';
        $url .= "/index.aspx?client={$key}";
        if (isset($ids['isbn']) && $ids['isbn']->isValid()) {
            $isbn = $ids['isbn']->get13();
            $url .= "&isbn={$isbn}";
        }
        if (isset($ids['issn'])) {
            $url .= "&issn={$ids['issn']}";
        }
        if (isset($ids['oclc'])) {
            $url .= "&oclc={$ids['oclc']}";
        }
        if (isset($ids['upc'])) {
            $url .= "&upc={$ids['upc']}";
        }
        return $url;
    }

    /**
     * Get the Syndetics metadata as XML.
     *
     * @param $baseUrl string  Base URL for the Syndetics query
     *
     * @return DOMDocument The metadata as a DOM XML document.
     */
    protected function getMetadataXML($baseUrl)
    {
        $url = $baseUrl . '/index.xml';
        if (!isset($this->cachingDownloader)) {
            throw new \Exception('CachingDownloader initialization failed.');
        }
        return $this->cachingDownloader->downloadXML($url);
    }

    /**
     * Find the image url in the XML returned from API.
     *
     * @param DOMDocument $xml  Parsed XML document
     * @param string      $size Size of image to load (small/medium/large)
     *
     * @return string|bool Full url of the image, or false if none matches
     */
    protected function getImageFilename($xml, $size)
    {
        switch ($size) {
            case 'small':
                $elementName = 'SC';
                break;
            case 'medium':
                $elementName = 'MC';
                break;
            case 'large':
                $elementName = 'LC';
                break;
            default:
                return false;
        }
        $nodes = $xmldoc->getElementsByTagName($elementName);
        if ($nodes->length == 0) {
            return false;
        }
        return $nodes->item(0)->nodeValue;
    }

    /**
     * Return the full image url.
     *
     * @param $baseUrl  string  Base URL for the Syndetics query
     * @param $filename string  Image filename
     *
     * @return string Full url of the image
     */
    protected function getImageUrl($baseUrl, $filename)
    {
        return $baseUrl . "/{$filename}";
    }
}
