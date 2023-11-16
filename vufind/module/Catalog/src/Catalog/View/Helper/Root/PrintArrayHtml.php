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

namespace Catalog\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

use function is_array;
use function is_int;

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
     * @param array|string $entry       An array or string to output
     * @param int          $indentLevel How many spaces to indent output
     * @param bool         $indentFirst Whether the first item in an array should be indented
     *
     * @return string
     */
    public function __invoke($entry, $indentLevel = 0, $indentFirst = true)
    {
        $html = '';
        if (is_array($entry)) {
            foreach ($entry as $key => $value) {
                if ($indentFirst || $key != array_key_first($entry)) {
                    $html .= str_repeat('&ensp;', $indentLevel);
                }

                // Increase indent if entering new array
                $nextIndentLevel = is_array($value) ? $indentLevel + 2 : 0;
                // Indent first line in values unless we're continuing from hypen
                $nextIndentFirst = !is_int($key) || !is_array($value);

                if (is_int($key)) {
                    // Integer keyed arrays use a hypen list
                    $html .= '&ndash;&ensp;';
                } else {
                    $html .= '<strong>' . $this->view->escapeHtml($key) . '</strong>: '
                             . (is_array($value) ? '<br>' : '');
                }

                $html .= $this->__invoke($value, $nextIndentLevel, $nextIndentFirst);
            }
        } else {
            $html = $this->view->escapeHtml($entry) . '<br>';
        }
        return $html;
    }
}
