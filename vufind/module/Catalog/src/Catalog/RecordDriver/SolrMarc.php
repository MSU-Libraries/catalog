<?php

/**
 * Retrieves data from Solr for a given record
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Record_Drivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace Catalog\RecordDriver;

use function array_key_exists;
use function count;
use function in_array;

/**
 * Extends the record driver with additional data from Solr
 *
 * @category VuFind
 * @package  Record_Drivers
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    /**
     * Takes a Marc field (ex: 950) and a list of sub fields (ex: ['a','b'])
     * and returns the values inside those fields in an array
     * (ex: ['val 1', 'val 2'])
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     *
     * @return array the values within the subfields under the field
     */
    public function getMarcField(string $field, ?array $subfield = null)
    {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $subfield);
        foreach ($marc_fields as $marc_data) {
            $subfields = $marc_data['subfields'];
            foreach ($subfields as $subfield) {
                $vals[] = $subfield['data'];
            }
        }
        return $vals;
    }

    /**
     * Takes a Marc field (ex: 950) and a list of sub fields (ex: ['a','b'])
     * and returns the unique values inside those fields in an array
     * (ex: ['val 1', 'val 2'])
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     *
     * @return array The unique values within the subfields under the field
     */
    public function getMarcFieldUnique(string $field, ?array $subfield = null)
    {
        return array_unique($this->getMarcField($field, $subfield));
    }

    /**
     * Takes a Marc field that notes are stored in (ex: 950) and a list of
     * sub fields (ex: ['a','b']) optionally
     * and concatonates the subfields together and returns the fields back
     * as an array
     * (ex: ['subA subB subC', 'field2SubA field2SubB'])
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     *
     * @return array The values within the subfields under the field
     */
    public function getNotesMarcFields(string $field, ?array $subfield = null)
    {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $subfield);
        foreach ($marc_fields as $marc_data) {
            $exclude = false;
            $val = '';
            $subfields = $marc_data['subfields'];
            foreach ($subfields as $subfield) {
                // exclude field from display if value of subfield 5 is not MiEM
                if ($subfield['code'] == '5' && $subfield['data'] != 'MiEM' && $subfield['data'] != 'MiEMMF') {
                    $exclude = true;
                    break;
                }
                // exclude subfield 5 from display
                if ($subfield['code'] == '5') {
                    continue;
                }
                $val .= $subfield['data'] . ' ';
            }
            if (!$exclude) {
                $vals[] = trim($val);
            }
        }
        return $vals;
    }

    /**
     * Takes a Marc field that notes are stored in (ex: 950) and a list of
     * sub fields (ex: ['a','b']) optionally as well as what indicator
     * number and value to filter for
     * and concatonates the subfields together and returns the fields back
     * as an array
     * (ex: ['subA subB subC', 'field2SubA field2SubB'])
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     * @param string $indNum   The Marc indicator to filter for
     * @param string $indValue The indicator value to check for
     *
     * @return array The values within the subfields under the field
     */
    public function getMarcFieldWithInd(
        string $field,
        ?array $subfield = null,
        string $indNum = '',
        string $indValue = ''
    ) {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $subfield);
        foreach ($marc_fields as $marc_data) {
            $field_vals = [];
            if (trim(($marc_data['i' . $indNum] ?? '')) == $indValue) {
                $subfields = $marc_data['subfields'];
                foreach ($subfields as $subfield) {
                    $field_vals[] = $subfield['data'];
                }
            }
            if (!empty($field_vals)) {
                $vals[] = implode(' ', $field_vals);
            }
        }
        return array_unique($vals);
    }

    /**
     * Takes a Solr field and returns the contents of the field (either
     * a string or array)
     *
     * @param string $field Name of the Solr field to get
     *
     * @return string|array Contents of the solr field
     */
    public function getSolrField(string $field)
    {
        $val = '';
        if (array_key_exists($field, $this->fields) && !empty($this->fields[$field])) {
            $val = $this->fields[$field];
        }
        return $val;
    }

    /**
     * Get the note fields
     *
     * @return array Note fields from Solr
     */
    public function getNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('561', ['a', 'u', '3'], '1', ''),
            $this->getMarcFieldWithInd('561', ['a', 'u', '3'], '1', '1')
        );
    }

    /**
     * Get the binding note fields
     *
     * @return array Note fields from Solr
     */
    public function getBindingNotes()
    {
        return $this->getNotesMarcFields('563');
    }

    /**
     * Get the computer file or data note
     *
     * @return array Note fields from Solr
     */
    public function getFileNotes()
    {
        return $this->getNotesMarcFields('516');
    }

    /**
     * Get the date/time and place of an event note
     *
     * @return array Note fields from Solr
     */
    public function getEventDetailsNotes()
    {
        return $this->getNotesMarcFields('518');
    }

    /**
     * Get the type of report and period covered note
     *
     * @return array Note fields from Solr
     */
    public function getTypeOfReportAndPeriodNotes()
    {
        return $this->getNotesMarcFields('513');
    }

    /**
     * Get the data quality note
     *
     * @return array Note fields from Solr
     */
    public function getDataQualityNotes()
    {
        return $this->getNotesMarcFields('514');
    }

    /**
     * Get the summary note
     *
     * @return array Note fields from Solr
     */
    public function getSummaryNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('520', null, '1', ''),
            $this->getMarcFieldWithInd('520', null, '1', '0'),
            $this->getMarcFieldWithInd('520', null, '1', '2'),
            $this->getMarcFieldWithInd('520', null, '1', '8'),
        );
    }

    /**
     * Get the review by notes
     *
     * @return array Note fields from Solr
     */
    public function getReviewNotes()
    {
        return $this->getMarcFieldWithInd('520', null, '1', '1');
    }

    /**
     * Get the abstract notes
     *
     * @return array Note fields from Solr
     */
    public function getAbstractNotes()
    {
        return $this->getMarcFieldWithInd('520', null, '1', '3');
    }

    /**
     * Get the content advice notes
     *
     * @return array Note fields from Solr
     */
    public function getContentAdviceNotes()
    {
        return $this->getMarcFieldWithInd('520', null, '1', '4');
    }

    /**
     * Get the audience note
     *
     * @return array Note fields from Solr
     */
    public function getTargetAudienceNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('521', null, '1', ''),
            $this->getMarcFieldWithInd('521', null, '1', '8'),
        );
    }

    /**
     * Get the grade level note
     *
     * @return array Note fields from Solr
     */
    public function getGradeLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, '1', '0');
    }

    /**
     * Get the interest age level note
     *
     * @return array Note fields from Solr
     */
    public function getInterestAgeLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, '1', '1');
    }

    /**
     * Get the interest grade level note
     *
     * @return array Note fields from Solr
     */
    public function getInterestGradeLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, '1', '2');
    }

    /**
     * Get the special audience characteristics note
     *
     * @return array Note fields from Solr
     */
    public function getSpecialAudienceNotes()
    {
        return $this->getMarcFieldWithInd('521', null, '1', '3');
    }

    /**
     * Get the motivation interest level note
     *
     * @return array Note fields from Solr
     */
    public function getInterestLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, '1', '4');
    }

    /**
     * Get the Contents notes
     *
     * @return array Note fields from Solr
     */
    public function getContentsNotes()
    {
        $toc = [];
        if (
            $fields = array_merge(
                $this->getMarcFieldWithInd('505', null, '1', '0'),
                $this->getMarcFieldWithInd('505', null, '1', '8')
            )
        ) {
            foreach ($fields as $field) {
                // explode on the -- separators (filtering out empty chunks). Due to
                // inconsistent application of subfield codes, this is the most
                // reliable way to split up a table of contents.
                $toc = array_merge($toc, preg_split('/[.\s]--/', $field));
                //$toc[] = array_filter(array_map('trim', preg_split('/[.\s]--/', $field)));
            }
        }
        return $toc;
    }

    /**
     * Get the incomplete contents notes
     *
     * @return array Note fields from Solr
     */
    public function getIncompleteContentsNotes()
    {
        $toc = [];
        if (
            $fields = $this->getMarcFieldWithInd('505', null, '1', '1')
        ) {
            foreach ($fields as $field) {
                // explode on the -- separators (filtering out empty chunks). Due to
                // inconsistent application of subfield codes, this is the most
                // reliable way to split up a table of contents.
                $toc = array_merge($toc, preg_split('/[.\s]--/', $field));
            }
        }
        return $toc;
    }

    /**
     * Get the partial contents notes
     *
     * @return array Note fields from Solr
     */
    public function getPartialContentsNotes()
    {
        $toc = [];
        if (
            $fields = $this->getMarcFieldWithInd('505', null, '1', '2')
        ) {
            foreach ($fields as $field) {
                // explode on the -- separators (filtering out empty chunks). Due to
                // inconsistent application of subfield codes, this is the most
                // reliable way to split up a table of contents.
                $toc = array_merge($toc, preg_split('/[.\s]--/', $field));
            }
        }
        return $toc;
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedByNotes()
    {
        return $this->getMarcFieldWithInd('510', null, '1', '0');
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedByEntiretyNotes()
    {
        return $this->getMarcFieldWithInd('510', null, '1', '1');
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedBySelectivelyNotes()
    {
        return $this->getMarcFieldWithInd('510', null, '1', '2');
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedReferenceNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('510', null, '1', '3'),
            $this->getMarcFieldWithInd('510', null, '1', '4')
        );
    }

    /**
     * Get the participant or performer notes
     *
     * @return array Note fields from Solr
     */
    public function getParticipantNotes()
    {
        return $this->getMarcFieldWithInd('511', null, '1', '0');
    }

    /**
     * Get the cast notes
     *
     * @return array Note fields from Solr
     */
    public function getCastNotes()
    {
        return $this->getMarcFieldWithInd('511', null, '1', '1');
    }

    /**
     * Get the geographic coverage notes
     *
     * @return array Note fields from Solr
     */
    public function getGeographicCoverageNotes()
    {
        return $this->getNotesMarcFields('522');
    }

    /**
     * Get the supplement notes
     *
     * @return array Note fields from Solr
     */
    public function getSupplementNotes()
    {
        return $this->getNotesMarcFields('525');
    }

    /**
     * Get the reading program notes
     *
     * @return array Note fields from Solr
     */
    public function getReadingProgramNotes()
    {
        return $this->getNotesMarcFields('526');
    }

    /**
     * Get the a11y notes
     *
     * @return array Note fields from Solr
     */
    public function getA11yNotes()
    {
        return $this->getMarcFieldWithInd('532', null, '1', '8');
    }

    /**
     * Get the a11y technical details notes
     *
     * @return array Note fields from Solr
     */
    public function getA11yTechnicalDetailsNotes()
    {
        return $this->getMarcFieldWithInd('532', null, '1', '0');
    }

    /**
     * Get the a11y features notes
     *
     * @return array Note fields from Solr
     */
    public function getA11yFeaturesNotes()
    {
        return $this->getMarcFieldWithInd('532', null, '1', '1');
    }

    /**
     * Get the a11y deficiencies notes
     *
     * @return array Note fields from Solr
     */
    public function getA11yDeficienciesNotes()
    {
        return $this->getMarcFieldWithInd('532', null, '1', '2');
    }

    /**
     * Get the reproduction notes
     *
     * @return array Note fields from Solr
     */
    public function getReproductionNotes()
    {
        return $this->getNotesMarcFields('533');
    }

    /**
     * Get the original version notes
     *
     * @return array Note fields from Solr
     */
    public function getOriginalVersionNotes()
    {
        return $this->getNotesMarcFields('534');
    }

    /**
     * Get the funding information notes
     *
     * @return array Note fields from Solr
     */
    public function getFundingInformationNotes()
    {
        return $this->getNotesMarcFields('536');
    }

    /**
     * Get the terms of use notes
     *
     * @return array Note fields from Solr
     */
    public function getTermsOfUseNotes()
    {
        return $this->getNotesMarcFields('540');
    }

    /**
     * Get the source of acquisition notes
     *
     * @return array Note fields from Solr
     */
    public function getSourceOfAcquisitionNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('541', null, '1', ''),
            $this->getMarcFieldWithInd('541', null, '1', '1')
        );
    }

    /**
     * Get the copyright information notes
     *
     * @return array Note fields from Solr
     */
    public function getCopyrightInformationNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('542', null, '1', ''),
            $this->getMarcFieldWithInd('542', null, '1', '1')
        );
    }

    /**
     * Get the location of other archival materials notes
     *
     * @return array Note fields from Solr
     */
    public function getLocationOfArchivalMaterialsNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('544', null, '1', ''),
            $this->getMarcFieldWithInd('544', null, '1', '0')
        );
    }

    /**
     * Get the location of related materials notes
     *
     * @return array Note fields from Solr
     */
    public function getLocationOfRelatedMaterialsNotes()
    {
        return $this->getMarcFieldWithInd('544', null, '1', '1');
    }

    /**
     * Get the bibliographical sketch notes
     *
     * @return array Note fields from Solr
     */
    public function getBiographicalSketchNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('545', null, '1', ''),
            $this->getMarcFieldWithInd('545', null, '1', '0')
        );
    }

    /**
     * Get the administrative history notes
     *
     * @return array Note fields from Solr
     */
    public function getAdministrativeHistoryNotes()
    {
        return $this->getMarcFieldWithInd('545', null, '1', '1');
    }

    /**
     * Get the formal title notes
     *
     * @return array Note fields from Solr
     */
    public function getFormerTitleNotes()
    {
        return $this->getNotesMarcFields('547');
    }

    /**
     * Get the issuing body notes
     *
     * @return array Note fields from Solr
     */
    public function getIssuingBodyNotes()
    {
        return $this->getNotesMarcFields('550');
    }

    /**
     * Get th entity and attribute information notes
     *
     * @return array Note fields from Solr
     */
    public function getEntityAttributeInformationNotes()
    {
        return $this->getNotesMarcFields('552');
    }

    /**
     * Get the numbering peculiarity notes
     *
     * @return array Note fields from Solr
     */
    public function getNumberingPeculiaritiesNotes()
    {
        return $this->getNotesMarcFields('515');
    }

    /**
     * Get the holder of originals notes
     *
     * @return array Note fields from Solr
     */
    public function getHoldersOfOriginalNotes()
    {
        return $this->getMarcFieldWithInd('535', null, '1', '1');
    }

    /**
     * Get the holder of duplicates notes
     *
     * @return array Note fields from Solr
     */
    public function getHoldersOfDuplicateNotes()
    {
        return $this->getMarcFieldWithInd('535', null, '1', '2');
    }

    /**
     * Get the language note fields
     *
     * @return array Note fields from Solr
     */
    public function getLanguageNotes()
    {
        return $this->getNotesMarcFields('546');
    }

    /**
     * Get the cumulative indexes notes
     *
     * @return array Note fields from Solr
     */
    public function getCumulativeIndexesNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('555', null, '1', ''),
            $this->getMarcFieldWithInd('555', null, '1', '8')
        );
    }

    /**
     * Get the finding aid notes
     *
     * @return array Note fields from Solr
     */
    public function getFindingAidNotes()
    {
        return $this->getMarcFieldWithInd('555', null, '1', '0');
    }

    /**
     * Get the information about documentation note fields
     *
     * @return array Note fields from Solr
     */
    public function getDocumentationInformationNotes()
    {
        return $this->getNotesMarcFields('556');
    }

    /**
     * Get the copy and version identification note fields
     *
     * @return array Note fields from Solr
     */
    public function getCopyAndVersionNotes()
    {
        return $this->getNotesMarcFields('562');
    }

    /**
     * Get the case file characteristics fields
     *
     * @return array Note fields from Solr
     */
    public function getCaseFileCharacteristicNotes()
    {
        return $this->getNotesMarcFields('565');
    }

    /**
     * Get the methodology notes fields
     *
     * @return array Note fields from Solr
     */
    public function getMethodologyNotes()
    {
        return $this->getNotesMarcFields('567');
    }

    /**
     * Get the publications notes fields
     *
     * @return array Note fields from Solr
     */
    public function getPublicationNotes()
    {
        return $this->getNotesMarcFields('581');
    }

    /**
     * Get the action notes fields
     *
     * @return array Note fields from Solr
     */
    public function getActionNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('583', null, '1', ''),
            $this->getMarcFieldWithInd('583', null, '1', '1')
        );
    }

    /**
     * Get the accumulation and frequency of use note
     *
     * @return array Note fields from Solr
     */
    public function getAccumulationAndFrequencyOfUseNotes()
    {
        return $this->getNotesMarcFields('584');
    }

    /**
     * Get the exhibition notes fields
     *
     * @return array Note fields from Solr
     */
    public function getExhibitionNotes()
    {
        return $this->getNotesMarcFields('585');
    }

    /**
     * Get the source of description notes fields
     *
     * @return array Note fields from Solr
     */
    public function getSourceOfDescriptionNotes()
    {
        return array_merge(
            $this->getMarcFieldWithInd('588', null, '1', ''),
            $this->getMarcFieldWithInd('588', null, '1', '0')
        );
    }

    /**
     * Get the latest iossue consulted notes fields
     *
     * @return array Note fields from Solr
     */
    public function getLatestIssueConsultedNotes()
    {
        return $this->getMarcFieldWithInd('588', null, '1', '1');
    }

    /**
     * Get the scale note fields
     *
     * @return array Note fields from Solr
     */
    public function getScaleNotes()
    {
        return $this->getNotesMarcFields('507');
    }

    /**
     * Get the cite as note fields
     *
     * @return array Note fields from Solr
     */
    public function getCiteAsNotes()
    {
        return $this->getNotesMarcFields('524');
    }

    /**
     * Get the additional physical form note fields
     *
     * @return array Note fields from Solr
     */
    public function getAdditionalPhysicalFormNotes()
    {
        return $this->getNotesMarcFields('530');
    }

    /**
     * Get the dissertation note fields
     *
     * @return array Note fields from Solr
     */
    public function getDissertationNotes()
    {
        return $this->getNotesMarcFields('502');
    }

    /**
     * Get the 590 local notes field
     *
     * @return array Content from Solr
     */
    public function getLocalNotes()
    {
        $notes = [];
        $marc = $this->getMarcReader();
        $marcArr856 = $marc->getFields('856', ['u','y']);
        $bookplates = [];

        // Get bookplate data from 856u & y, where 'u' contains "bookplate"
        foreach ($marcArr856 as $marc856) {
            $sfvals = [];
            foreach ($marc856['subfields'] as $subfield) {
                $sfvals[$subfield['code']] = $subfield['data'];
            }
            if (str_contains($sfvals['u'], 'bookplate')) {
                $bookplates[] = ['note' => $sfvals['y'], 'url' => $sfvals['u']];
            }
        }

        // Process local notes from 590a
        $marcArr590 = $marc->getFields('590', ['a']);
        foreach ($marcArr590 as $marc590) {
            $subfields = $marc590['subfields'];
            $sfvals = [];
            foreach ($marc590['subfields'] as $subfield) {
                $sfvals[$subfield['code']] = $subfield['data'];
            }
            // Check if the local note exists in the bookplate notes,
            // if so, use the bookplate values instead
            $bookplateMatch = false;
            foreach ($bookplates as $bookplate) {
                if (strcasecmp($bookplate['note'] ?? '', $sfvals['a']) === 0) {
                    $notes[] = ['note' => $sfvals['a'], 'url' => $bookplate['url']];
                    $bookplateMatch = true;
                    break;
                }
            }
            if (!$bookplateMatch) {
                $notes[] = ['note' => $sfvals['a']];
            }
        }
        return $notes;
    }

    /**
     * Get the publisher field
     *
     * @return array Content from Solr
     */
    public function getPublisher()
    {
        return $this->getSolrField('publisher');
    }

    /**
     * Get the physical description field
     *
     * @return array Content from Solr
     */
    public function getPhysical()
    {
        return $this->getSolrField('physical');
    }

    /**
     * Get the genres
     *
     * @return array Content from Solr
     */
    public function getGenres()
    {
        return $this->getSolrField('genre_facet');
    }

    /**
     * Get the uniform title
     *
     * @return array Content from Solr
     */
    public function getUniformTitle()
    {
        return array_merge(
            $this->getUniformTitleFromMarc('130', range('a', 'z')),
            $this->getUniformTitleFromMarc('240', range('a', 'z')),
        );
    }

    /**
     * Get the uniform title
     *
     * @param $field mixed name of the field to search in
     * @param $codes mixed list of subfield codes to capture
     *
     * @return array Content from Solr
     */
    public function getUniformTitleFromMarc($field, $codes): array
    {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $codes);
        foreach ($marc_fields as $marc_data) {
            $subfields = $marc_data['subfields'];
            $tmpVal = ['name' => [], 'value' => []];
            foreach ($subfields as $subfield) {
                if ($subfield['code'] == 'a' || $subfield['code'] == 'p') {
                    $tmpVal['name'][] = $subfield['data'];
                } else {
                    $tmpVal['value'][] = $subfield['data'];
                }
            }
            $tmpVal['name'] = implode(' ', $tmpVal['name']);
            $tmpVal['value'] = implode(' ', $tmpVal['value']);
            $vals[] = $tmpVal;
        }
        return $vals;
    }

    /**
     * Get the Sierra bib number
     *
     * @return null|string Content from Solr
     */
    public function getSierraBN()
    {
        $bibnum = null;
        $marc = $this->getMarcReader();
        $marcArr907 = $marc->getFields('907', ['y']);
        foreach ($marcArr907 as $marc907) {
            $subfields = $marc907['subfields'];
            foreach ($subfields as $subfield) {
                if ($subfield['code'] == 'y' && !empty($subfield['data'])) {
                    $bibnum = ltrim($subfield['data'], '.');
                    break 2;
                }
            }
        }
        return $bibnum;
    }

    /**
     * Get the locations
     *
     * @return array Content from Solr
     */
    public function getLocations()
    {
        $locs = [];
        $marc = $this->getMarcReader();
        $marcArr952 = $marc->getFields('952', ['b','c','d']);
        foreach ($marcArr952 as $marc952) {
            $subfields = $marc952['subfields'];
            $sfvals = [];
            foreach ($subfields as $subfield) {
                $sfvals[$subfield['code']] = $subfield['data'];
            }
            if ($sfvals['b'] == 'Michigan State University') {
                $locs[] = empty($sfvals['d']) ? $sfvals['c'] : $sfvals['d'];
            }
        }
        return $locs;
    }

    /**
     * Get the first location
     *
     * @return string Content from Solr
     */
    public function getLocation()
    {
        return $this->getLocations()[0] ?? '';
    }

    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        return $this->getSolrField('title_full');
    }

    /**
     * Get the Folio unique identifier
     *
     * @return array Content from Solr
     */
    public function getUUID()
    {
        return $this->getSolrField('uuid_str');
    }

    /**
     * Get the full call number
     *
     * @return array Content from Solr
     */
    public function getFullCallNumber()
    {
        // return $this->getSolrField('099', ['f', 'a']);
        return array_unique(
            $this->getMarcField('952', ['f', 'e'])
        );
    }

    /**
     * Get the barcode
     *
     * @return array Content from Solr
     */
    public function getBarcode()
    {
        return $this->getMarcField('952', ['m']);
    }

    /**
     * Get the Cartographic Data
     *
     * @return array Content from Solr
     */
    public function getCartographicData()
    {
        return $this->getMarcField('255', ['a', 'b', 'c', 'd']);
    }

    /**
     * Get the translated from languaes
     *
     * @return array Content from Solr
     */
    public function getLanguageOriginal()
    {
        return $this->getSolrField('language_original_str_mv');
    }

    /**
     * Get the eJournal links with date coverage from the z subfield if available
     *
     * @return array Content from Solr
     */
    public function geteJournalLinks()
    {
        $data = [];
        $idx = 0;
        $marc = $this->getMarcReader();

        $marc856s = $marc->getFields('856', ['u', 'y', 'z', '3']);
        $marc773s = $marc->getFields('773', ['t']);

        foreach ($marc856s as $marc856) {
            $subfields = $marc856['subfields'];
            $rec = [];
            $suffix = '';

            foreach ($subfields as $subfield) {
                $sfvals[$subfield['code']] = $subfield['data'];
                if ($subfield['code'] == 'u') {
                    $rec['url'] = $subfield['data'];
                } elseif (in_array($subfield['code'], ['y','z'])) {
                    $rec['desc'] = $subfield['data'];
                } elseif ($subfield['code'] == '3') {
                    $suffix = ' (' . $subfield['data'] . ')';
                }
            }

            // Fall back to 773 field if we can't find description in the '856z' field
            if ((in_array('z', $subfields) || empty($rec['desc'])) && count($marc773s) >= $idx) {
                $rec['desc'] = $marc773s[$idx]['subfields'][0]['data'];
            }

            // Append the 856|3 if present
            if (!empty($suffix)) {
                $rec['desc'] .= $suffix;
            }

            $data[] = $rec;
            $idx += 1;
        }
        return $data;
    }

    /**
     * Get the video game platform
     *
     * @return array Content from Solr
     */
    public function getPlatform()
    {
        return $this->getMarcFieldUnique('753', ['a']);
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return array_merge($this->getMarcField('500', ['a', '3']), $this->getMarcField('501', ['a']));
    }

    /**
     * Get the variant titles
     *
     * @return array Content from Solr
     */
    public function getVariantTitles()
    {
        $titles = [];
        $marc = $this->getMarcReader();
        $marcArr246 = $marc->getFields('246', ['a', 'b', 'i']);

        foreach ($marcArr246 as $marc246) {
            $type = '';
            $title = '';

            // Make sure there is an 'a' subfield in this record to get the title
            if (in_array('a', array_column($marc246['subfields'], 'code'))) {
                $a = $b = '';
                foreach ($marc246['subfields'] as $subfield) {
                    switch ($subfield['code']) {
                        case 'a':
                            $a = $subfield['data'];
                            break;
                        case 'b':
                            $b = ' ' . $subfield['data'];
                            break;
                    }
                }
                $title = $a . $b;
            } else {
                continue; // don't proces if we don't even have a title
            }

            if (!empty($marc246['i2'] ?? '')) {
                // If the 2nd indicator is present, use that as 'type'
                $type = $marc246['i2'];
                switch ($marc246['i2']) {
                    case 0:
                        $type = 'Portion of title';
                        break;
                    case 1:
                        $type = 'Parallel title';
                        break;
                    case 2:
                        $type = 'Distinctive title';
                        break;
                    case 3:
                        $type = 'Other title';
                        break;
                    case 4:
                        $type = 'Cover title';
                        break;
                    case 5:
                        $type = 'Added title page title';
                        break;
                    case 6:
                        $type = 'Caption title';
                        break;
                    case 7:
                        $type = 'Running title';
                        break;
                    case 8:
                        $type = 'Spine title';
                        break;
                }
            } elseif (in_array('i', array_column($marc246['subfields'], 'code'))) {
                // otherwise check if subfield 'i' is present, and use that for 'type'
                foreach ($marc246['subfields'] as $subfield) {
                    if ($subfield['code'] == 'i') {
                        $type = $subfield['data'];
                        break;
                    }
                }
            }
            // Get the title
            $titles[] = [
                'type' => $type,
                'title' => $title,
            ];
        }
        return $titles;
    }

    /**
     * Modification of the original function in MarcAdvancedTrait.php to display subjects in same order ad marc records
     * Get all subject headings associated with this record. Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @param bool $extended Whether to return a keyed array with the following
     * keys:
     * - heading: the actual subject heading chunks
     * - type: heading type
     * - source: source vocabulary
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        // This is all the collected data:
        $retval = [];

        /** START MSU */
        /** This modification replaces the two foreach from the trait **/
        $allFields = $this->getMarcReader()->getAllFields();
        $subjectFieldsKeys = array_keys($this->subjectFields);
        // Go through all the fields and handle them if they are part of what we want
        foreach ($allFields as $result) {
            if (isset($result['tag']) && in_array($result['tag'], $subjectFieldsKeys)) {
                $fieldType = $this->subjectFields[$result['tag']];
                /** END MSU **/

                // Start an array for holding the chunks of the current heading:
                $current = [];

                // Get all the chunks and collect them together:
                foreach ($result['subfields'] as $subfield) {
                    // Numeric subfields are for control purposes and should not
                    // be displayed:
                    if (!is_numeric($subfield['code'])) {
                        $current[] = $subfield['data'];
                    }
                }
                // If we found at least one chunk, add a heading to our result:
                if (!empty($current)) {
                    if ($extended) {
                        $sourceIndicator = $result['i2'];
                        $source = '';
                        if (isset($this->subjectSources[$sourceIndicator])) {
                            $source = $this->subjectSources[$sourceIndicator] ?? '';
                        } else {
                            $source = $this->getSubfield($result, '2');
                        }
                        $retval[] = [
                            'heading' => $current,
                            'type' => $fieldType,
                            'source' => $source,
                            'id' => $this->getSubfield($result, '0'),
                        ];
                    } else {
                        $retval[] = $current;
                    }
                }
            }
        }

        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize',
            array_unique(array_map('serialize', $retval))
        );
    }
}
