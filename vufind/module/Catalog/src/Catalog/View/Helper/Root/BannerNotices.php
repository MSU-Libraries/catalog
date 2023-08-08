<?php

/**
 * View helper to display banner notices loaded from a Yaml config
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
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Catalog\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use Laminas\Validator\File\NotExists;
use Laminas\Diactoros\ServerRequestFilter\IPRange;

/**
 * View helper to display banner notices loaded from a Yaml config
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BannerNotices extends AbstractHelper implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Banner notices configuration
     *
     * @var array
     */
    protected $noticesConfig;

    /**
     * User IP address reader
     *
     * @var UserIpReader
     */
    protected $userIpReader;

    /**
     * Request object
     *
     * @var \Laminas\Http\PhpEnvironment\Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param array                                 $noticesConfig Banner notices configuration
     * @param UserIpReader                          $userIpReader IP reader for client
     * @param \Laminas\Http\PhpEnvironment\Request  $request
     */
    public function __construct(array $noticesConfig, $userIpReader, $request)
    {
        $this->noticesConfig = $noticesConfig;
        $this->userIpReader = $userIpReader;
        $this->request = $request;
    }

    /**
     * Print banners based on conditions defined in banner-notices.yaml
     *
     * @return string                   The formatted HTML for output
     */
    public function __invoke()
    {
        $makeTag = $this->getView()->plugin('makeTag');
        $html = "";

        foreach ($this->noticesConfig['notices'] ?? [] as $notice) {
            if (empty($notice['message'])) {
                $this->logWarning(
                    "BannerNotices config has notice with ".
                    "empty or missing 'message'");
            }
            elseif ($this->evaluateConditions($notice)) {
                $html .= $this->renderNotice($notice);
            }
        }
        return $html;
    }

    /**
     * Given a notice configuration, render and return HTML as appropriate
     *
     * @param array     $notice A single banner notice configuration
     * @return string   The rendered banner notice div, or empty string
     */
    protected function renderNotice(array $notice)
    {
        $makeTag = $this->getView()->plugin('makeTag');
        $escapeContent = ($notice['escapeContent'] ?? true);
        $style = "";
        foreach ($notice['style'] ?? [] as $key => $val) {
            $style .= "{$key}:{$val};";
        }
        return $makeTag("div", $notice["message"],
            ["class" => "banner-notice"
                . ($notice["classes"] ?? ""),
             "style" => $style],
            ["escapeContent" => $escapeContent]
        );
    }

    /**
     * Evaluate all conditions appropriate to a single notice configuration
     *
     * @param array     $notice A single banner notice configuration
     * @return boolean
     */
    protected function evaluateConditions(array $notice)
    {
        $success = true;
        foreach ($notice['conditions'] ?? [] as $idx => $condition) {
            $baseVal = "";
            $compVals = array_merge(
                array_key_exists('value', $condition) ? [$condition['value']] : [],
                $condition['values'] ?? []
            );
            switch($condition['type'] ?? "") {
                case 'string':
                    $baseVal = $condition['string'] ?? "";
                    break;
                case 'datetime':
                    $baseVal = (new \DateTime())->format("c");
                    $compVals = array_map(
                        function ($val) { return (new \DateTime($val))->format("c"); },
                        $compVals
                    );
                    break;
                case 'date':
                    $baseVal = (new \DateTime())->setTime(0, 0)->format("Y-m-d");
                    $compVals = array_map(
                        function ($val) { return (new \DateTime($val))->format("Y-m-d"); },
                        $compVals
                    );
                    break;
                case 'time':
                    $baseVal = (new \DateTime())->format("H:i:s");
                    $compVals = array_map(
                        function ($val) { return (new \DateTime($val))->format("H:i:s"); },
                        $compVals
                    );
                    break;
                case 'env':
                    $baseVal = getenv($condition['env'] ?? "");
                    break;
                case 'urlpath':
                    $baseVal = $this->request->getUri()->getPath();
                    break;
                case 'remoteip':
                    $baseVal = $this->userIpReader->getUserIp();
                    break;
                default;
                    $this->logWarning(
                        "BannerNotices config has invalid type for ".
                        "condition index of {$idx} with message starting '" .
                        mb_substr($condition['message'] ?? "", 0, 10) . "'");
            }

            $success &= $this->handleComparison(
                $condition['comp'] ?? "",
                $baseVal,
                $compVals);
        }
        return $success;
    }

    /**
     * Evaluate a single set of comparison conditions
     *
     * @param string    $comp     The type of comparison to evaluate
     * @param string    $checkVal The value to valiate
     * @param array     $matchAny Values for the comparison which will result in success
     * @return boolean
     */
    protected function handleComparison(
        string $comp,
        string $checkVal,
        array $matchAny
    ) {
        $matched = false;
        foreach ($matchAny as $match) {
            switch ($comp) {
                case 'equals':
                    $matched |= $checkVal == $match;
                    break;
                case 'notequals':
                    $matched |= $checkVal != $match;
                    break;
                case 'startswith':
                    $matched |= str_starts_with($checkVal, $match);
                    break;
                case 'endswith':
                    $matched |= str_ends_with($checkVal, $match);
                    break;
                case 'lessthan':
                    $matched |= $checkVal < $match;
                    break;
                case 'greaterthan':
                    $matched |= $checkVal > $match;
                    break;
                case 'cidr':
                    $matched |= IPRange::matches($checkVal, $match);
                    break;
                case 'notcidr':
                    $matched |= !IPRange::matches($checkVal, $match);
                    break;
                case 'filetype':
                    $matched |= @filetype($checkVal) == $match;
                    break;
                default:
                    $this->logWarning(
                        "BannerNotices config has invalid comparison type '{$comp}'");
            }
            if ($matched) { break; }
        }
        return $matched;
    }
}
