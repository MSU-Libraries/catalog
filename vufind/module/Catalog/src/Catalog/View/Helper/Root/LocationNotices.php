<?php

/**
 * View helper to display location-specific notices loaded from a Yaml config
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2023.
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
 * @package  View_Helpers
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper to display banner notices loaded from a Yaml config
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LocationNotices extends AbstractHelper implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Banner notices configuration
     *
     * @var array
     */
    protected $noticesConfig;

    /**
     * Constructor
     *
     * @param array $noticesConfig Banner notices configuration
     */
    public function __construct(array $noticesConfig)
    {
        $this->noticesConfig = $noticesConfig;
    }

    /**
     * Print banners based on conditions defined in banner-notices.yaml
     *
     * @param string $location   Location regular expression
     * @param string $callnumber Call number regular expression
     *
     * @return string The formatted HTML for output
     */
    public function __invoke(string $location, string $callnumber)
    {
        $makeTag = $this->getView()->plugin('makeTag');
        $html = "";

        foreach ($this->noticesConfig['locationNotices'] ?? [] as $notice) {
            if (empty($notice['message'])) {
                $this->logWarning(
                    "LocationNotices config has notice with " .
                    "empty or missing 'message'"
                );
            } elseif ($this->evaluateConditions($notice, $location, $callnumber)) {
                $html .= $this->renderNotice($notice);
            }
        }
        return $html;
    }

    /**
     * Evaluate all conditions appropriate to a single notice configuration
     *
     * @param array  $notice     A single location notice configuration
     * @param string $location   Location regular expression
     * @param string $callnumber Call number regular expression
     *
     * @return boolean
     */
    protected function evaluateConditions(array $notice, string $location, string $callnumber)
    {
        if (empty($notice['location']) && empty($notice['callNumber'])) {
            return false;
        }
        $success = true;
        if (!empty($notice['location'])) {
            if (!preg_match('/^' . $notice['location'] . '$/', $location)) {
                $success = false;
            }
        }
        if (!empty($notice['callNumber'])) {
            if (!preg_match('/^' . $notice['callNumber'] . '$/', $callnumber)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Given a notice configuration, render and return HTML as appropriate
     *
     * @param array $notice A single location notice configuration
     *
     * @return string The rendered location notice div, or empty string
     */
    protected function renderNotice(array $notice)
    {
        $makeTag = $this->getView()->plugin('makeTag');
        $escapeContent = ($notice['escapeContent'] ?? true);
        $style = "";
        foreach ($notice['style'] ?? [] as $key => $val) {
            $style .= "{$key}:{$val};";
        }
        return $makeTag(
            "div",
            $notice["message"],
            [
                "class" => "location-notice" . ($notice["classes"] ?? ""),
                "style" => $style,
            ],
            ["escapeContent" => $escapeContent]
        );
    }
}
