<?php
/**
 * View helper to print an array formatted for HTML display.
 *
 * PHP version 7
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
namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper to print an array formatted for HTML display.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Nathan Collins <colli372@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PrintArrayHtml extends AbstractHelper
{
    /**
     * Print an array formatted for HTML display.
     * Function uses recursion to achieve desired results, so entry can be
     * either an array or a value to display.
     *
     * @param array|string $entry  An array or string to output
     * @param int $indentLevel How many spaces to indent output
     * @param bool $indentFirst Whether the first item in an array should be indented
     *
     * @return string
     */
    public function __invoke($entry, $indentLevel=0, $indentFirst=true) {
        $html = "";
        if (is_array($entry)) {
            $first = true;
            foreach ($entry as $key => $value) {
                $nextIndentLevel = $indentLevel;
                if ($indentFirst || !$first) {
                    $html .= str_repeat("&ensp;", $indentLevel);
                }

                if (is_int($key)) {
                    $html .= "&ndash;&ensp;";
                    $nextIndentLevel += 2;
                }
                else {
                    $html .= "<strong>".$this->view->escapeHtml($key)."</strong> ";
                }

                if (is_array($value)) {
                    if (is_int($key)) {
                        # If our key is int, don't indent (continue from hyphen)
                        $html .= $this->__invoke($value, $nextIndentLevel, false);
                    }
                    else {
                        # Only indent when key in value being passed is not an int
                        $html .= "<br/>" .
                                 $this->__invoke($value, $nextIndentLevel + 2);
                    }
                }
                else {
                    $html .= $this->__invoke($value);
                }
                $first = false;
            }
        }
        else {
            $html = $this->view->escapeHtml($entry) . "<br />";
        }
        return $html;
    }

}
