<?php

/**
 * Factory for record driver data formatting view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Record_Data_Formatter
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter Wiki
 */

namespace Catalog\View\Helper\Root;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

use function count;

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

        $spec->setLine('Physical Description', 'getPhysical');

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
            'Other Uniform Title',
            'getOtherUniformTitle',
            'data-uniform-title.phtml'
        );

        $spec->setTemplateLine(
            'Genre',
            'getGenres',
            'data-genre.phtml'
        );
        $spec->setTemplateLine(
            'Ownership and Custodial History',
            'getNotes',
            'data-notes.phtml'
        );
        $spec->setLine('Cartographic Data', 'getCartographicData');
        $spec->setLine('Produced', 'getProduced');
        $spec->setLine('Platform', 'getPlatform');
        $spec->setLine('Added Title', 'getAddedTitle');
        $spec->setLine('Distributed', 'getDistributed');
        $spec->setLine('Manufactured', 'getManufactured');
        $spec->setLine('Copyright Date', 'getCopyrightDate');
        $spec->setTemplateLine('Language of the Original', 'getLanguageOriginal', 'data-notes.phtml');
        $spec->setTemplateLine('Language and/or Writing System', 'getLanguageNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Local Note', 'getLocalNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Binding Information', 'getBindingNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Dissertation Note', 'getDissertationNotes', 'data-notes.phtml');

        $spec->reorderKeys(['Uniform Title', 'Published in', 'New Title', 'Previous Title',
                'Authors', 'Language', 'Language of the Original', 'Language and/or Writing System',
                'Published', 'Produced', 'Distributed', 'Manufactured', 'Copyright Date',
                'Edition', 'Series', 'Subjects', 'Genre', 'child_records', 'Online Access',
                'Ownership and Custodial History', 'Local Note', 'Dissertation Note',
                'Binding Information', 'Physical Description', 'Tags', 'Variant Title',
                'Other Uniform Title', 'Added Title']);

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
        $spec->setLine('Item Description', null);
        $spec->setLine('Production Credits', null);
        $spec->setLine('Related Items', null);
        $spec->setLine('Format', null);
        $spec->setLine('Access', null);
        $spec->setLine('Bibliography', null);
        $spec->setLine('Audience', null);
        $spec->setLine('Finding Aid', null);
        $spec->setLine('Publication Frequency', null);
        $spec->setLine('Published', null);

        $spec->setTemplateLine('Summary', 'getSummaryNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Review', 'getReviewNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Abstract', 'getAbstractNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Content Advice', 'getContentAdviceNotes', 'data-notes.phtml');
        $spec->setLine('Note', 'getGeneralNotes');
        $spec->setLine('Call Number', 'getCallNumbers');
        $spec->setLine('Credits', 'getProductionCredits');
        $spec->setLine('Related Materials', 'getRelationshipNotes');
        $spec->setLine('System Details', 'getSystemDetails');
        $spec->setLine('Bibliography Note', 'getBibliographyNotes');
        $spec->setTemplateLine('Scale Note', 'getScaleNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Cite As', 'getCiteAsNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Indexed By', 'getIndexedByNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Indexed in its Entirety By', 'getIndexedByEntiretyNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Indexed Selectively By', 'getIndexedBySelectivelyNotes', 'data-notes.phtml');
        $spec->setTemplateLine('References', 'getIndexedReferenceNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Participant or Performer', 'getParticipantNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Cast', 'getCastNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Type of File', 'getFileNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Event Details', 'getEventDetailsNotes', 'data-notes.phtml');
        $spec->setTemplateLine(
            'Type of Report and Period Covered',
            'getTypeOfReportAndPeriodNotes',
            'data-notes.phtml'
        );
        $spec->setTemplateLine('Data Quality', 'getDataQualityNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Audience', 'getTargetAudienceNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Reading Grade Level', 'getGradeLevelNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Interest Age Level', 'getInterestAgeLevelNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Interest Grade Level', 'getInterestGradeLevelNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Special Audience Characteristics', 'getSpecialAudienceNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Motivation/Interest Level', 'getInterestLevelNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Geographic Coverage', 'getGeographicCoverageNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Supplement Note', 'getSupplementNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Reading Program', 'getReadingProgramNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Accessibility Note', 'getA11yNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Accessibility Technical Details', 'getA11yTechnicalDetailsNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Accessibility Features', 'getA11yFeaturesNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Accessibility Deficiencies', 'getA11yDeficienciesNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Reproduction Note', 'getReproductionNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Original Version', 'getOriginalVersionNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Funding Information', 'getFundingInformationNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Source of Acquisition', 'getSourceOfAcquisitionNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Copyright Information', 'getCopyrightInformationNotes', 'data-notes.phtml');
        $spec->setTemplateLine(
            'Location of Other Archival Materials',
            'getLocationOfArchivalMaterialsNotes',
            'data-notes.phtml'
        );
        $spec->setTemplateLine(
            'Location of Related Materials',
            'getLocationOfRelatedMaterialsNotes',
            'data-notes.phtml'
        );
        $spec->setTemplateLine('Biographical Sketch', 'getBiographicalSketchNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Administrative History', 'getAdministrativeHistoryNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Former Title Note', 'getFormerTitleNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Issuing Body Note', 'getIssuingBodyNotes', 'data-notes.phtml');
        $spec->setTemplateLine(
            'Entity and Attribute Information',
            'getEntityAttributeInformationNotes',
            'data-notes.phtml'
        );
        $spec->setTemplateLine('Cumulative Indexes', 'getCumulativeIndexesNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Finding Aids', 'getFindingAidNotes', 'data-notes.phtml');
        $spec->setTemplateLine(
            'Information About Documentation',
            'getDocumentationInformationNotes',
            'data-notes.phtml'
        );
        $spec->setTemplateLine('Copy and Version Identification', 'getCopyAndVersionNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Case File Characteristics', 'getCaseFileCharacteristicNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Methodology Note', 'getMethodologyNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Publications', 'getPublicationNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Action Note', 'getActionNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Exhibitions', 'getExhibitionNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Source of Description', 'getSourceOfDescriptionNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Latest Issue Consulted', 'getLatestIssueConsultedNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Additional Physical Form', 'getAdditionalPhysicalFormNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Holder of Originals', 'getHolderOfOriginalNotes', 'data-notes.phtml');
        $spec->setTemplateLine('Holder of Duplicates', 'getHolderOfDuplicateNotes', 'data-notes.phtml');
        $spec->setLine('Current Publication Frequency', 'getPublicationFrequency');
        $spec->setLine('Former Publication Frequency', 'getFormerPublicationFrequency');
        $spec->setLine('Publication Dates and/or Sequencing', 'getDateSpan');

        $spec->reorderKeys(['Summary', 'Review', 'Abstract', 'Content Advice', 'Note', 'Call Number',
                'Credits', 'Related Materials', 'System Details', 'Scale Note', 'Cite As',
                'Publication Dates and/or Sequencing', 'Current Publication Frequency',
                'Former Publication Frequency', 'Playing Time',
                'Audience', 'Reading Grade Level', 'Interest Age Level', 'Interest Grade Level',
                'Special Audience Characteristics', 'Motivation/Interest Level', 'Awards', 'Bibliography Note',
                'ISBN', 'ISSN', 'DOI',
                'Finding Aids', 'Author Notes', 'Credits', 'Related Materials', 'System Details',
                'Scale Note', 'Indexed By', 'Indexed in its Entirety By', 'Indexed Selectively By',
                'References', 'Participant or Performer', 'Cast', 'Type of File', 'Event Details',
                'Type of Report and Period Covered', 'Data Quality', 'Supplement Note', 'Reading Program',
                'Accessibility Note', 'Accessibility Technical Details', 'Accessibility Features',
                'Accessibility Deficiencies', 'Reproduction Note', 'Original Version', 'Funding Information',
                'Source of Acquisition', 'Copyright Information', 'Additional Physical Form',
                'Location of Other Archival Materials', 'Location of Related Materials',
                'Biographical Sketch', 'Administrative History', 'Former Title Note', 'Issuing Body Note',
                'Entity and Attribute Information', 'Cumulative Indexes', 'Information About Documentation',
                'Copy and Version Identification', 'Case File Characteristics', 'Methodology Note', 'Publications',
                'Action Note', 'Exhibitions', 'Source of Description', 'Latest Issue Consulted',
                'Holder of Originals', 'Holder of Duplicates']);

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

    /**
     * Get the callback function for processing authors.
     *
     * @return callable
     */
    protected function getAuthorFunction()
    {
        return function ($data, $options) {
            // Lookup array of singular/plural labels (note that Other is always
            // plural right now due to lack of translation strings).
            $labels = [
                'primary' => ['Main Author', 'Main Authors'],
                'corporate' => ['Corporate Author', 'Corporate Authors'],
                'secondary' => ['Other Authors', 'Other Authors'],
            ];
            // Lookup array of schema labels.
            $schemaLabels = [
                'primary' => 'author',
                'corporate' => 'creator',
                'secondary' => 'contributor',
            ];

            // Sort the data:
            $final = [];
            foreach ($data as $type => $values) {
                $final[] = [
                    'label' => $labels[$type][count($values) == 1 ? 0 : 1],
                    'values' => [$type => $values],
                    'options' => [
                        'pos' => $options['pos'] + $this->authorOrder[$type],
                        'renderType' => 'RecordDriverTemplate',
                        'template' => 'data-authors.phtml',
                        'context' => [
                            'type' => $type,
                            'schemaLabel' => $schemaLabels[$type],
                            'requiredDataFields' => [
                                ['name' => 'role', 'prefix' => 'CreatorRoles::'],
                                ['name' => 'link', 'prefix' => ''], // MSUL - PC-936 add 'link' to the author output
                            ],
                        ],
                    ],
                ];
            }
            return $final;
        };
    }
}
