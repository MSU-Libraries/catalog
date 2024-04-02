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
            'Variant Title',
            'getVariantTitles',
            'data-variant-title.phtml'
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
        $spec->setTemplateLine('Translated From', 'getTranslatedFrom', 'data-notes.phtml');
        $spec->setTemplateLine('Language and/or Writing System', 'getLanguageNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Local Note', 'getLocalNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Dissertation Note', 'getDissertationNotes', 'data-notes.phtml');

        $spec->reorderKeys(['Uniform Title', 'Published in', 'New Title', 'Previous Title',
                'Authors', 'Language', 'Translated From', 'Language and/or Writing System',
                'Published', 'Edition', 'Series',
                'Subjects', 'Genre', 'Physical Description',
                'child_records', 'Online Access',
                'Notes', 'Local Note', 'Dissertation Note', 'Tags', 'Variant Title']);

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

        $spec->setLine('Physical Description', null);
        $spec->setLine('Call Number', 'getFullCallnumber');
        $spec->setTemplateLine('Numbering Peculiarities', 'getNumberingPeculiaritiesNotes', 'data-notes.phtml');
        $spec->setLine('Production Credits', null);
        $spec->setLine('Credits', 'getProductionCredits');
        $spec->setLine('Related Items', null);
        $spec->setLine('Related Materials', 'getRelationshipNotes');
        $spec->setLine('Format', null);
        $spec->setLine('System Details', 'getSystemDetails');
        $spec->setLine('Access', null);
        $spec->setTemplateLine('Scale Note', 'getScaleNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Cite As', 'getCiteAsNotes', 'data-notes.phtml');

        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in collection-info metadata.
     *
     * @return array
     */
    public function getDefaultCollectionInfoSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultCollectionInfoSpecs());

        $spec->setLine('Production Credits', null);
        $spec->setLine('Credits', 'getProductionCredits');

        $spec->setLine('Related Items', null);
        $spec->setTemplateLine(
            'Related Materials',
            'getAllRecordLinks',
            'data-allRecordLinks.phtml'
        );

        $spec->setLine('Format', null);
        $spec->setLine(
            'System Details',
            'getFormats',
            'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );

        return $spec->getArray();
    }

    /**
     * Get default specifications for displaying data in collection-record metadata.
     *
     * @return array
     */
    public function getDefaultCollectionRecordSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultCollectionInfoSpecs());

        $spec->setLine('Related Items', null);
        $spec->setLine('Related Materials', 'getRelationshipNotes');

        $spec->setLine('Format', null);
        $spec->setLine(
            'System Details',
            'getFormats',
            'RecordHelper',
            ['helperMethod' => 'getFormatList']
        );

        return $spec->getArray();
    }
}
