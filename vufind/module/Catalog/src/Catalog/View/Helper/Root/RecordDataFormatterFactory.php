<?php

/**
 * Handles Formatting of the record page display
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Record_Data_Formatter
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter Wiki
 */

namespace Catalog\View\Helper\Root;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

/**
 * Extends the default display of the record page display
 *
 * @category VuFind
 * @package  Record_Data_Formatter
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter Wiki
 */
class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory
{
    /**
     * Override the default display at the top of the record page display
     * to add new fields and alter the display order.
     *
     * @return array The display specifications
     */
    public function getDefaultCoreSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultCoreSpecs());

        $spec->setTemplateLine(
            'Uniform Title',
            'getUniformTitle',
            'data-uniform-title.phtml'
        );

        $spec->setTemplateLine(
            'Genre',
            'getGenres',
            'data-genre.phtml'
        );
        $spec->setTemplateLine(
            'Notes',
            'getNotes',
            'data-notes.phtml'
        );
        $spec->setLine('Physical Description', 'getPhysicalDescriptions');
        $spec->setLine('Cartographic Data', 'getCartographicData');
        $spec->setLine('Platform', 'getPlatform');

        // Reorder the fields to get Genre next to Subjects
        $spec->reorderKeys(['Uniform Title', 'Published in', 'New Title', 'Previous Title', 'Authors',
                'Format', 'Language', 'Published', 'Edition', 'Series',
                'Subjects', 'Genre', 'Physical Description',
                'child_records', 'Online Access', 'Related Items', 'Notes', 'Tags']);

        return $spec->getArray();
    }

    /**
     * Update the description field display for the record used to
     * populate the Description tab on record page
     *
     * @return array The display specification
     */
    public function getDefaultDescriptionSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultDescriptionSpecs());

        // Remove Physical Description
        $spec->setLine('Physical Description', null);
        $spec->setLine('Call Number', 'getFullCallnumber');
        $spec->setLine('Language Notes', 'getLanguageNotes');

        return $spec->getArray();
    }
}
