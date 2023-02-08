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
     * @param array|string $entry  An array or string to output.
     *
     * @return string
     */
    public function __invoke($entry, $recurse=0)
        $html = "";
        if (is_array($entry)) {
            # Prevent excessive recursion
            if ($recurse > 12) {
                return print_r($entry, true);
            }

            foreach ($entry as $key => $value) {
                $html .= str_repeat("&ensp;", $recurse * 2) .
                         "<strong>" . $this->view->escapeHtml($key) .
                         "</strong> =&gt;";
                if (is_array($value)) {
                    $html .= "[<br/>" .
                             $this->__invoke($value, $recurse + 1) .
                             str_repeat("&ensp;", $recurse * 2) .
                             "]<br/>";
                }
                else {
                    $html .= $this->__invoke($value);
                }
            }
        }
        else {
            $html = $this->view->escapeHtml($entry) . "<br />";
        }
        return $html;
    }

}
