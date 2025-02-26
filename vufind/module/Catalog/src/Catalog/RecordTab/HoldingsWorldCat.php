<?php

/**
 * Holdings (WorldCat) tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace Catalog\RecordTab;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Holdings (WorldCat2) tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HoldingsWorldCat2 extends \VuFind\RecordTab\HoldingsWorldCat2 implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->translate('holdings_access');
    }
}
