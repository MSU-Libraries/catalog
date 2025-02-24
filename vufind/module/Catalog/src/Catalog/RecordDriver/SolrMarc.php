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
use function is_array;

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
     * Fields that may contain subject headings, and their descriptions
     *
     * @var array
     */
    protected $subjectFields = [
        '600' => 'personal name',
        '610' => 'corporate name',
        '611' => 'meeting name',
        '630' => 'uniform title',
        '648' => 'chronological',
        '650' => 'topic',
        '651' => 'geographic',
        '653' => '',
//        '655' => 'genre/form', // MSU commented
        '656' => 'occupation',
    ];

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
     * @param string $field Marc field to search within
     * @param array  $codes Sub-fields to return or empty for all
     *
     * @return array The values within the subfields under the field
     */
    public function getNotesMarcFields(string $field, ?array $codes = [])
    {
        $vals = [];
        $marc = $this->getMarcReader();
        // Add '6' to codes if not null
        if ($codes !== null && !empty($codes)) {
            $codes[] = '6';
        }
        $marc_fields = $marc->getFields($field, $codes);
        foreach ($marc_fields as $marc_data) {
            $exclude = false;
            $val = '';
            $link = '';
            $subfields = $marc_data['subfields'];
            foreach ($subfields as $subfield) {
                // exclude field from display if value of subfield 5 is not MiEM
                if ($subfield['code'] == '5' && $subfield['data'] != 'MiEM' && $subfield['data'] != 'MiEMMF') {
                    $exclude = true;
                    break;
                }
                // exclude subfield 5 from display
                if ($subfield['code'] === '5') {
                    continue;
                }
                $explodedSubfield = explode('-', $subfield['data']);
                if ($subfield['code'] === '6' && count($explodedSubfield) > 1) {
                    $index = $explodedSubfield[1];
                    $linked = $marc->getLinkedField('880', $field, $index, $codes);
                    if (isset($linked['subfields'])) {
                        $linkval = '';
                        foreach ($linked['subfields'] as $rec) {
                            if ($rec['code'] === '6') {
                                continue;
                            }
                            $linkval = $linkval . ' ' . $rec['data'];
                        }
                        $link = rtrim(trim($linkval), ',.');
                    }
                } elseif ($subfield['code'] !== '6') {
                    $val .= $subfield['data'] . ' ';
                }
            }
            if (!$exclude) {
                $vals[] = [
                    'note' => trim($val),
                    'link' => $link,
                ];
            }
        }
        return $vals;
    }

    /**
     * Similar functionality to getMarcFieldWithInd, but also retrieves any linkage from a $6
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     * @param array  $indData  Array of filter arrays, each in the format indicator number =>
     * array of allowed indicator values. If any one of the filter arrays fully matches the indicator
     * values in the field, data will be returned. If no filter arrays are defined, data will always
     * be returned regardless of indicators.
     * ex: [['1' => ['1', '2']], ['2' => ['']]] would filter fields ind1 = 1 or 2 or ind2 = blank
     * ex: [['1' => ['1'], '2' => ['7']]] would filter fields with ind1 = 1 and ind2 = 7
     * ex: [] would apply no filtering based on indicators
     *
     * @return array An array pair with:
     *                0: array with the values within the subfields under the field
     *                1: array with the values from a $6 linkage if exists, null otherwise
     */
    public function getMarcFieldWithIndAndLinked(
        string $field,
        ?array $subfield = null,
        array $indData = []
    ) {
        $linkVals = null;
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, array_merge($subfield ?? [], ['6']));
        foreach ($marc_fields as $marc_data) {
            $subfields = $marc_data['subfields'];
            $subf6 = array_filter($subfields, function ($subf) {
                return array_key_exists('code', $subf) && $subf['code'] == '6';
            });
            $linked = null;
            if ($subf6) {
                $subf6Parts = explode('-', $subf6[0]['data']);
                if (count($subf6Parts) > 1 && $subf6Parts[0] == '880') {
                    $index = $subf6Parts[1];
                    $linked = $marc->getLinkedField('880', $field, $index, $subfield);
                }
            }

            foreach ($subfields as $subf) {
                if ($subf['code'] === '6') {
                    continue;
                }
                if (isset($linked['subfields'])) {
                    foreach ($linked['subfields'] as $lsf) {
                        if ($lsf['code'] === '6' || !in_array($lsf['code'], $subfield)) {
                            continue;
                        }
                        if ($linkVals === null) {
                            $linkVals = [];
                        }
                        $linkVals[] = $lsf['data'];
                    }
                }
            }
        }
        return [
            $this->getMarcFieldWithInd($field, $subfield, $indData),
            $linkVals,
        ];
    }

    /**
     * Added in PC-1105
     * Takes a Marc field that notes are stored in (ex: 950) and a list of
     * sub fields (ex: ['a','b']) optionally as well as what indicator
     * numbers and values to exclude, then concatenates the subfields
     * together and returns the fields back as an array
     * (ex: ['subA subB subC', 'field2SubA field2SubB'])
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     * @param array  $indData  Array containing the indicator number as the key
     *                         and the value as an array of strings for the
     *                         allowed indicator values
     *                         ex: [['1' => '1', '2', '2' => '']]
     *                         would filter ind1 = 1 or 2 and ind2 = blank
     *
     * @return array The values within the subfields under the field
     */
    public function getMarcFieldWithoutInd(
        string $field,
        ?array $subfield = null,
        array $indData = []
    ) {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, $subfield);
        foreach ($marc_fields as $marc_data) {
            $field_vals = [];
            // Check if that field has either indicator (MARC only has up to 2 indicators)
            foreach (range(1, 2) as $indNum) {
                if (
                    array_key_exists($indNum, $indData) &&
                    !in_array(trim(($marc_data['i' . $indNum] ?? '')), $indData[$indNum])
                ) {
                    $subfields = $marc_data['subfields'];
                    foreach ($subfields as $subfield) {
                        $field_vals[] = $subfield['data'];
                    }
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
     * Get the full titles of the record including section and part information in
     * alternative scripts.
     *
     * @return array
     */
    public function getFullTitlesAltScript(): array
    {
        return $this->getMarcReader()
            ->getLinkedFieldsSubfields('880', '245', ['a', 'b', 'c', 'n', 'p']);
    }

    /**
     * MSU extended
     * Get the text of the part/section portion of the title.
     *
     * @return string
     */
    public function getTitleSection()
    {
        return $this->getFirstFieldValue('245', ['n', 'p', 'c']);
    }

    /**
     * Get the note fields
     *
     * @return array Note fields from Solr
     */
    public function getNotes()
    {
        return $this->getMarcFieldWithInd('561', ['a', 'u', '3'], [[1 => ['', '1']]]);
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
        return $this->getMarcFieldWithInd('520', null, [[1 => ['', '0', '2', '8']]]);
    }

    /**
     * Get the review by notes
     *
     * @return array Note fields from Solr
     */
    public function getReviewNotes()
    {
        return $this->getMarcFieldWithInd('520', null, [[1 => ['1']]]);
    }

    /**
     * Get the abstract notes
     *
     * @return array Note fields from Solr
     */
    public function getAbstractNotes()
    {
        return $this->getMarcFieldWithInd('520', null, [[1 => ['3']]]);
    }

    /**
     * Get the abstract and summary notes
     *
     * @return array Note fields from Solr
     */
    public function getAbstractAndSummaryNotes()
    {
        return $this->getMarcFieldWithInd('520', null, [[1 => ['', '0', '2', '3', '8']]]);
    }

    /**
     * Get the content advice notes
     *
     * @return array Note fields from Solr
     */
    public function getContentAdviceNotes()
    {
        return $this->getMarcFieldWithInd('520', null, [[1 => ['4']]]);
    }

    /**
     * Get the audience note
     *
     * @return array Note fields from Solr
     */
    public function getTargetAudienceNotes()
    {
        return $this->getMarcFieldWithInd('521', null, [[1 => ['', '8']]]);
    }

    /**
     * Get the grade level note
     *
     * @return array Note fields from Solr
     */
    public function getGradeLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, [[1 => ['0']]]);
    }

    /**
     * Get the interest age level note
     *
     * @return array Note fields from Solr
     */
    public function getInterestAgeLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, [[1 => ['1']]]);
    }

    /**
     * Get the interest grade level note
     *
     * @return array Note fields from Solr
     */
    public function getInterestGradeLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, [[1 => ['2']]]);
    }

    /**
     * Get the special audience characteristics note
     *
     * @return array Note fields from Solr
     */
    public function getSpecialAudienceNotes()
    {
        return $this->getMarcFieldWithInd('521', null, [[1 => ['3']]]);
    }

    /**
     * Get the motivation interest level note
     *
     * @return array Note fields from Solr
     */
    public function getInterestLevelNotes()
    {
        return $this->getMarcFieldWithInd('521', null, [[1 => ['4']]]);
    }

    /**
     * Given two arrays of strings, split each stirng on a delimiter `--` and join
     * them with their counterpart split from the other array using ` = `. These
     * joined strings are returned as an array.
     * If the first array is empty, an empty array is returned.
     * If the second array does not have a split counterpart, only the first array's split
     * will be returned for that iteration.
     *
     * @param array $arr1 The primary array
     * @param array $arr2 The secondary array
     *
     * @return array The array with joined strings
     */
    private function splitAndMergeArrayValues($arr1, $arr2)
    {
        // TODO we could have the delimiter and join string passed as customizable arguments
        $toc = [];
        if (!empty($arr1)) {
            array_map(function ($fdata, $ldata) use (&$toc) {
                $fsplits = preg_split('/[.\s]--/', $fdata);
                $lsplits = preg_split('/[.\s]--/', $ldata);
                $max_size = max(count($fsplits), count($lsplits));
                $fsplits = array_pad($fsplits, $max_size, '');
                $lsplits = array_pad($lsplits, $max_size, '');

                array_map(function ($fval, $lval) use (&$toc) {
                    $toc[] = $fval . ($lval ? " = {$lval}" : '');
                }, $fsplits, $lsplits);
            }, $arr1, $arr2);
        }
        return array_filter($toc);
    }

    /**
     * Get the Contents notes
     *
     * @return array Note fields from Solr
     */
    public function getContentsNotes()
    {
        $toc = [];
        // Assumption: only one of indicator "1=0" or "1=8" will exist on any given record
        $marc505_0 = $this->getMarcFieldWithIndAndLinked('505', ['a','g','r','t','u'], [[1 => ['0']]]);
        $fields = $marc505_0[0];
        $linked = $marc505_0[1] ?? array_pad($marc505_0[1] ?? [], count($marc505_0[0]), '');
        if (empty($fields)) {
            $marc505_8 = $this->getMarcFieldWithIndAndLinked('505', ['a','g','r','t','u'], [[1 => ['8']]]);
            $fields = $marc505_8[0];
            $linked = $marc505_8[1] ?? array_pad($marc505_8[1] ?? [], count($marc505_8[0]), '');
        }

        $toc = $this->splitAndMergeArrayValues($fields, $linked);
        return array_unique($toc);
    }

    /**
     * Get the incomplete contents notes
     *
     * @return array Note fields from Solr
     */
    public function getIncompleteContentsNotes()
    {
        $toc = [];
        $marc505 = $this->getMarcFieldWithIndAndLinked('505', ['a','g','r','t','u'], [[1 => ['1']]]);
        $fields = $marc505[0];
        $linked = $marc505[1] ?? array_pad($marc505[1] ?? [], count($marc505[0]), '');

        $toc = $this->splitAndMergeArrayValues($fields, $linked);
        return array_unique($toc);
    }

    /**
     * Get the partial contents notes
     *
     * @return array Note fields from Solr
     */
    public function getPartialContentsNotes()
    {
        $toc = [];
        $marc505 = $this->getMarcFieldWithIndAndLinked('505', ['a','g','r','t','u'], [[1 => ['2']]]);
        $fields = $marc505[0];
        $linked = $marc505[1] ?? array_pad($marc505[1] ?? [], count($marc505[0]), '');

        $toc = $this->splitAndMergeArrayValues($fields, $linked);
        return array_unique($toc);
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedByNotes()
    {
        return $this->getMarcFieldWithInd('510', null, [[1 => ['0']]]);
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedByEntiretyNotes()
    {
        return $this->getMarcFieldWithInd('510', null, [[1 => ['1']]]);
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedBySelectivelyNotes()
    {
        return $this->getMarcFieldWithInd('510', null, [[1 => ['2']]]);
    }

    /**
     * Get the indexed by notes
     *
     * @return array Note fields from Solr
     */
    public function getIndexedReferenceNotes()
    {
        return $this->getMarcFieldWithInd('510', null, [[1 => ['3', '4']]]);
    }

    /**
     * Get the participant or performer notes
     *
     * @return array Note fields from Solr
     */
    public function getParticipantNotes()
    {
        return $this->getMarcFieldWithInd('511', null, [[1 => ['0']]]);
    }

    /**
     * Get the cast notes
     *
     * @return array Note fields from Solr
     */
    public function getCastNotes()
    {
        return $this->getMarcFieldWithInd('511', null, [[1 => ['1']]]);
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
        return $this->getMarcFieldWithInd('532', null, [[1 => ['8']]]);
    }

    /**
     * Get the a11y technical details notes
     *
     * @return array Note fields from Solr
     */
    public function getA11yTechnicalDetailsNotes()
    {
        return $this->getMarcFieldWithInd('532', null, [[1 => ['0']]]);
    }

    /**
     * Get the a11y features notes
     *
     * @return array Note fields from Solr
     */
    public function getA11yFeaturesNotes()
    {
        return $this->getMarcFieldWithInd('532', null, [[1 => ['1']]]);
    }

    /**
     * Get the a11y deficiencies notes
     *
     * @return array Note fields from Solr
     */
    public function getA11yDeficienciesNotes()
    {
        return $this->getMarcFieldWithInd('532', null, [[1 => ['2']]]);
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
        return $this->getNotesMarcFields('540', range('a', 'z'));
    }

    /**
     * Get the source of acquisition notes
     *
     * @return array Note fields from Solr
     */
    public function getSourceOfAcquisitionNotes()
    {
        return $this->getMarcFieldWithInd('541', null, [[1 => ['', '1']]]);
    }

    /**
     * Get the copyright information notes
     *
     * @return array Note fields from Solr
     */
    public function getCopyrightInformationNotes()
    {
        return $this->getMarcFieldWithInd('542', null, [[1 => ['', '1']]]);
    }

    /**
     * Get the location of other archival materials notes
     *
     * @return array Note fields from Solr
     */
    public function getLocationOfArchivalMaterialsNotes()
    {
        return $this->getMarcFieldWithInd('544', null, [[1 => ['', '0']]]);
    }

    /**
     * Get the location of related materials notes
     *
     * @return array Note fields from Solr
     */
    public function getLocationOfRelatedMaterialsNotes()
    {
        return $this->getMarcFieldWithInd('544', null, [[1 => ['1']]]);
    }

    /**
     * Get the bibliographical sketch notes
     *
     * @return array Note fields from Solr
     */
    public function getBiographicalSketchNotes()
    {
        return $this->getMarcFieldWithInd('545', null, [[1 => ['', '0']]]);
    }

    /**
     * Get the administrative history notes
     *
     * @return array Note fields from Solr
     */
    public function getAdministrativeHistoryNotes()
    {
        return $this->getMarcFieldWithInd('545', null, [[1 => ['1']]]);
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
    public function getHolderOfOriginalNotes()
    {
        return $this->getMarcFieldWithInd('535', null, [[1 => ['1']]]);
    }

    /**
     * Get the holder of duplicates notes
     *
     * @return array Note fields from Solr
     */
    public function getHolderOfDuplicateNotes()
    {
        return $this->getMarcFieldWithInd('535', null, [[1 => ['2']]]);
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
        return $this->getMarcFieldWithInd('555', null, [[1 => ['', '8']]]);
    }

    /**
     * Get the finding aid notes
     *
     * @return array Note fields from Solr
     */
    public function getFindingAidNotes()
    {
        return $this->getMarcFieldWithInd('555', null, [[1 => ['0']]]);
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
        return $this->getMarcFieldWithInd('583', null, [[1 => ['', '1']]]);
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
        return $this->getMarcFieldWithInd('588', null, [[1 => ['', '0']]]);
    }

    /**
     * Get the latest iossue consulted notes fields
     *
     * @return array Note fields from Solr
     */
    public function getLatestIssueConsultedNotes()
    {
        return $this->getMarcFieldWithInd('588', null, [[1 => ['1']]]);
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
     * Get the topics
     *
     * @return array Content from Solr
     */
    public function getTopics()
    {
        $topics = [];
        $subjects = $this->getAllSubjectHeadings();
        if (is_array($subjects)) {
            foreach ($subjects as $subj) {
                $headings = array_map(
                    function ($item) {
                        return $item['subject'];
                    },
                    $subj
                );
                $topics[] = implode(' > ', $headings);
            }
        }
        return $topics;
    }

    /**
     * MSU -- customize to pull out 'subject' from getAllSubjectHeadings
     * Get the subject headings as a flat array of strings.
     *
     * @return array Subject headings
     */
    public function getAllSubjectHeadingsFlattened()
    {
        $topics = [];
        $subjects = $this->getAllSubjectHeadings();
        if (is_array($subjects)) {
            foreach ($subjects as $subj) {
                $headings = array_map(
                    function ($item) {
                        return $item['subject'];
                    },
                    $subj
                );
                $topics[] = implode(' -- ', $headings);
            }
        }
        return $topics;
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
     * Get the other uniform title
     *
     * @return array Content from Solr
     */
    public function getOtherUniformTitle()
    {
        return $this->getUniformTitleFromMarc('730', range('a', 'z'));
    }

    /**
     * Get the added title
     *
     * @return array Content from Solr
     */
    public function getAddedTitle()
    {
        // PC-1105
        $subfields = range('a', 'z');
        $subfields[] = '3';
        return $this->getMarcFieldWithInd('711', $subfields, [[2 => ['2']]]);
    }

    /**
     * Get the distributed value
     *
     * @return array Content from Solr
     */
    public function getDistributed()
    {
        // PC-1105
        return $this->getMarcFieldWithInd('264', ['a', 'b', 'c', '3'], [[2 => ['2']]]);
    }

    /**
     * Get the manufactured value
     *
     * @return array Content from Solr
     */
    public function getManufactured()
    {
        // PC-1105
        return $this->getMarcFieldWithInd('264', ['a', 'b', 'c', '3'], [[2 => ['3']]]);
    }

    /**
     * Get the copyright date
     *
     * @return array Content from Solr
     */
    public function getCopyrightDate()
    {
        // PC-1105
        return $this->getMarcFieldWithInd('264', ['a', 'b', 'c', '3'], [[2 => ['4']]]);
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
        // Add '6' to codes if not null
        if (!empty($codes)) {
            $codes[] = '6';
        }
        $marc_fields = $marc->getFields($field, $codes);
        foreach ($marc_fields as $marc_data) {
            $subfields = $marc_data['subfields'];
            $tmpVal = ['name' => [], 'value' => [], 'link' => ''];
            foreach ($subfields as $subfield) {
                // check if it has link data
                $explodedSubfield = explode('-', $subfield['data']);
                if ($subfield['code'] === '6' && count($explodedSubfield) > 1) {
                    $index = $explodedSubfield[1];
                    $linked = $marc->getLinkedField('880', $field, $index, $codes);
                    if (isset($linked['subfields'])) {
                        $val = '';
                        foreach ($linked['subfields'] as $rec) {
                            if ($rec['code'] === '6') {
                                continue;
                            }
                            $val = $val . ' ' . $rec['data'];
                        }
                        $tmpVal['link'] = rtrim(rtrim(trim($val), ','), '.');
                    }
                } elseif ($subfield['code'] === 'a' || $subfield['code'] === 'p') {
                    // get the title name used for the search link
                    $tmpVal['name'][] = $subfield['data'];
                } else {
                    // get the data to display
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
     * Return an array of call numbers from the Solr field "callnumber-full_str_mv".
     *
     * @return array the call numbers
     */
    public function getCallNumbers()
    {
        return array_unique(
            isset($this->fields['callnumber-full_str_mv'])
            ? array_map('trim', $this->fields['callnumber-full_str_mv'])
            : []
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
            $rec = ['desc' => ''];
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
            if (
                (in_array('z', $subfields) || empty($rec['desc']))
                && isset($marc773s[$idx]['subfields'][0]['data'])
            ) {
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
        // PC-1105 update subfields
        $marcArr246 = $marc->getFields('246', ['a', 'b', 'f', 'g', 'h', 'i', 'n', 'p', '6']);

        foreach ($marcArr246 as $marc246) {
            $type = '';
            $title = '';
            $link = '';

            // Make sure there is an 'a' subfield in this record to get the title
            if (in_array('a', array_column($marc246['subfields'], 'code'))) {
                foreach ($marc246['subfields'] as $subfield) {
                    switch ($subfield['code']) {
                        case 'a':
                        case 'b':
                        case 'f':
                        case 'g':
                        case 'h':
                        case 'n':
                        case 'p':
                            $title = $title . (empty($title) ? '' : ' ') . $subfield['data'];
                            break;
                        case 'i':
                            $type = $subfield['data'];
                            break;
                        case '6':
                            // check if it has link data
                            $explodedSubfield = explode('-', $subfield['data']);
                            if (count($explodedSubfield) > 1) {
                                $index = $explodedSubfield[1];
                                $linked = $marc->getLinkedField('880', '246', $index, ['a', 'b']);
                                if (isset($linked['subfields'])) {
                                    $val = '';
                                    foreach ($linked['subfields'] as $rec) {
                                        $val = $val . ' ' . $rec['data'];
                                    }
                                    $link = rtrim(rtrim(trim($val), ','), '.');
                                }
                            }
                            break;
                    }
                }
            } else {
                continue; // don't proces if we don't even have a title
            }

            if (!empty(trim($marc246['i2'] ?? ''))) {
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
            }
            // Get the title
            $titles[] = [
                'type' => $type,
                'title' => $title,
                'link' => $link,
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
     * Additional modification for PC-1018 to add transliterated values to the subjects
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

        /* START MSU */
        /* This modification replaces the two foreach from the trait */
        $allFields = $this->getMarcReader()->getAllFields();
        $subjectFieldsKeys = array_keys($this->subjectFields);
        // Go through all the fields and handle them if they are part of what we want
        // and does NOT have ind2 = 6
        foreach ($allFields as $result) {
            if (isset($result['tag']) && in_array($result['tag'], $subjectFieldsKeys) && $result['i2'] != '6') {
                $fieldType = $this->subjectFields[$result['tag']];

                // Start an array for holding the chunks of the current heading:
                $current = [];

                /* START MSU */
                // Get the transliterated 880 values for each result
                // if it has a subfield 6
                $linked = [];
                foreach ($result['subfields'] as $subfield) {
                    if ($subfield['code'] == '6') {
                        $explodedSubfield = explode('-', $subfield['data']);
                        if (count($explodedSubfield) > 1) {
                            $index = $explodedSubfield[1];
                            $linked = $this->getMarcReader()->getLinkedField(
                                '880',
                                $result['tag'],
                                $index,
                                range('a', 'z')
                            );
                            break;
                        }
                    }
                }

                // Get all the chunks and collect them together:
                // Track the previous subfield code and index so we can get the
                // correct linked one.
                $prevCode = '';
                $codeIndex = 0;
                foreach ($result['subfields'] as $subfield) {
                    if ($prevCode == $subfield['code']) {
                        $codeIndex = $codeIndex + 1;
                    }
                    // Numeric subfields are for control purposes and should not
                    // be displayed:
                    if (!is_numeric($subfield['code'])) {
                        $linkedVal = '';
                        if (array_key_exists('subfields', $linked)) {
                            $linkedPrevCode = '';
                            $linkedCodeIndex = 0;
                            foreach ($linked['subfields'] as $linkedSubfield) {
                                if ($linkedPrevCode == $linkedSubfield['code']) {
                                    $linkedCodeIndex = $linkedCodeIndex + 1;
                                }
                                // Use if we found the matching subfield code
                                // and it is not the same value as the original
                                if (
                                    $linkedSubfield['code'] == $subfield['code'] &&
                                    $linkedCodeIndex == $codeIndex &&
                                    $linkedSubfield['data'] != $subfield['data']
                                ) {
                                    $linkedVal = $linkedSubfield['data'];
                                    break;
                                }
                                $linkedPrevCode = $linkedSubfield['code'];
                            }
                        }
                        $current[] = ['subject' => $subfield['data'], 'linked' => $linkedVal];
                        $prevCode = $subfield['code'];
                    }
                }
                /* MSU END */

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

    /**
     * Get an array of the transliterated values for each author.
     *
     * @return array
     */
    public function getPrimaryAuthorsLinks()
    {
        return $this->getMarcFieldLinked('100', ['a', 'b', 'c']);
    }

    /**
     * Get an array of the transliterated values for each author.
     *
     * @return array
     */
    public function getSecondaryAuthorsLinks()
    {
        return $this->getMarcFieldLinked('700', ['a', 'b', 'c']);
    }

    /**
     * Get an array of all corporate authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getCorporateAuthors()
    {
        // MSUL PC-1105
        return array_merge(
            $this->getFieldArray('110', ['a', 'b']),
            $this->getFieldArray('111', range('a', 'z')),
            $this->getFieldArray('710', ['a', 'b']),
            $this->getMarcFieldWithoutInd('711', ['a', 'b'], ['2' => ['2']])
        );
    }

    /**
     * Get an array of the transliterated values for each author.
     *
     * @return array
     */
    public function getCorporateAuthorsLinks()
    {
        // PC-1105
        $authors = [];
        foreach (['110', '111', '710', '711'] as $field) {
            $authors = array_merge($authors, $this->getMarcFieldLinked($field, range('a', 'z')));
        }
        return $authors;
    }

    /**
     * Get an array of all series names containing the record. Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     */
    public function getSeries()
    {
        $matches = [];

        // MSUL PC-1105 Update subfields to match spreadsheet
        $subfieldsFor800s = range('a', 'u');
        $subfieldsFor800s[] = 'w';
        $subfieldsFor800s[] = 'x';
        $subfieldsFor800s[] = '3';

        // Get data for the primary series fields
        // subfield v is excluded here because getSeriesFromMarc will uses v
        // to get the series number
        $primaryFields = [
            '400' => ['p', 't'],
            '410' => ['p', 't'],
            '411' => ['p', 't'],
            '440' => [range('a', 'w')],
            '800' => $subfieldsFor800s,
            '810' => $subfieldsFor800s,
            '811' => $subfieldsFor800s,
            '830' => ['a', 'd', 'f', 'g', 'k', 'l', 'm', 'n','o', 'p', 'r', 's', 't', 'w', 'x', 'y'],
        ];
        $matches = $this->getSeriesFromMARC($primaryFields);

        // Get 490 field data if no other series data found
        // if ind 1 == 0
        if (empty($matches)) {
            $marc = $this->getMarcReader();
            $marc_fields = $marc->getFields('490', ['a', 'n', 'p', 'v', '6']);
            foreach ($marc_fields as $marc_data) {
                $field_vals = [];
                $field_num = '';
                $field_linked = '';
                if (trim(($marc_data['i1'] ?? '')) == '0') {
                    $subfields = $marc_data['subfields'];
                    foreach ($subfields as $subfield) {
                        if ($subfield['code'] == 'v') {
                            $field_num = $subfield['data'];
                        } elseif ($subfield['code'] == '6') {
                            $explodedSubfield = explode('-', $subfield['data']);
                            if (count($explodedSubfield) > 1) {
                                $index = $explodedSubfield[1];
                                $linked = $this->getMarcReader()->getLinkedField(
                                    '880',
                                    '490',
                                    $index,
                                    range('a', 'z')
                                );
                                $field_linked = implode(' ', $this->getSubfieldArray($linked, range('a', 'z')));
                            }
                        } else {
                            $field_vals[] = $subfield['data'];
                        }
                    }
                }
                if (!empty($field_vals)) {
                    $matches[] = [
                                'name' => implode(' ', $field_vals),
                                'number' => $field_num,
                                'linked' => $field_linked,
                    ];
                }
            }
        }
        return $matches;
    }

    /**
     * Support method for getSeries() -- given a field specification, look for
     * series information in the MARC record.
     *
     * @param array $fieldInfo Associative array of field => subfield information
     * (used to find series name)
     *
     * @return array
     */
    protected function getSeriesFromMARC($fieldInfo)
    {
        $matches = [];

        // Loop through the field specification....
        foreach ($fieldInfo as $field => $subfields) {
            // Did we find any matching fields?
            $series = $this->getMarcReader()->getFields($field);
            foreach ($series as $currentField) {
                // Can we find a name using the specified subfield list?
                $name = $this->getSubfieldArray($currentField, $subfields);
                if (isset($name[0])) {
                    $currentArray = ['name' => $name[0]];

                    // Can we find a number in subfield v?  (Note that number is
                    // always in subfield v regardless of whether we are dealing
                    // with 440, 490, 800 or 830 -- hence the hard-coded array
                    // rather than another parameter in $fieldInfo).
                    $number = $this->getSubfieldArray($currentField, ['v']);
                    if (isset($number[0])) {
                        $currentArray['number'] = $number[0];
                    }

                    // Check for a linked value
                    $linkedField = $this->getSubfieldArray($currentField, ['6']);
                    if (isset($linkedField[0])) {
                        $explodedSubfield = explode('-', $linkedField[0]);
                        if (count($explodedSubfield) > 1) {
                            $index = $explodedSubfield[1];
                            $linked = $this->getMarcReader()->getLinkedField(
                                '880',
                                $currentField['tag'],
                                $index,
                                range('a', 'z')
                            );
                            $currentArray['linked'] = implode(' ', $this->getSubfieldArray($linked, range('a', 'z')));
                        }
                    }

                    // Save the current match:
                    $matches[] = $currentArray;
                }
            }
        }

        return $matches;
    }

    /**
     * Get the transliterated values from the given field, mapping using the data in subfield 6
     *
     * @param string $field    Marc field to search within
     * @param array  $subfield Sub-fields to return or empty for all
     *
     * @return array the values within the subfields under the field
     */
    public function getMarcFieldLinked(string $field, ?array $subfield = null)
    {
        $vals = [];
        $marc = $this->getMarcReader();
        $marc_fields = $marc->getFields($field, ['6']);
        foreach ($marc_fields as $marc_data) {
            $linkedVal = '';
            $subfields = $marc_data['subfields'];
            if (count($subfields) === 1) {
                $explodedSubfield = explode('-', $subfields[0]['data']);
                if (count($explodedSubfield) > 1) {
                    $index = $explodedSubfield[1];
                    $linked = $marc->getLinkedField('880', $field, $index, $subfield);
                    if (isset($linked['subfields'])) {
                        $val = '';
                        foreach ($linked['subfields'] as $rec) {
                            if ($rec['code'] === '6') {
                                continue;
                            }
                            $val = $val . ' ' . $rec['data'];
                        }
                        $linkedVal = rtrim(rtrim(trim($val), ','), '.');
                    }
                }
            }

            $vals[] = $linkedVal;
        }
        return $vals;
    }

    /**
     * Deduplicate author information into associative array with main/corporate/
     * secondary keys.
     *
     * @param array $dataFields An array of extra data fields to retrieve (see
     * getAuthorDataFields)
     *
     * @return array
     */
    public function getDeduplicatedAuthors($dataFields = ['role', 'link'])
    {
        return parent::getDeduplicatedAuthors($dataFields);
    }

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        // PC-1105
        return $this->getFirstFieldValue('250', ['a', 'b', '3']);
    }

    /**
     * Get the former publication frequency
     *
     * @return string
     */
    public function getFormerPublicationFrequency()
    {
        // PC-1105
        return $this->getFieldArray('321', ['a', 'b']);
    }

    /**
     * Get the date coverage for a record which spans a period of time (i.e. a
     * journal). Use getPublicationDates for publication dates of particular
     * monographic items.
     *
     * @return array
     */
    public function getDateSpan()
    {
        // PC-1105, add more subfields
        return $this->getFieldArray('362', ['a', 'z']);
    }

    /**
     * Get the Produced field for the current record.
     *
     * @return string
     */
    public function getProduced()
    {
        // PC-1105
        return $this->getMarcFieldWithInd('264', ['a', 'b', '3'], [[2 => ['0']]]);
    }

    /**
     * PC-789 Return first non-empty highlighted text
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {
        // Only process snippets if the setting is enabled:
        if ($this->snippet) {
            // First check for preferred fields:
            foreach ($this->preferredSnippetFields as $current) {
                if (
                    empty($this->highlightDetails) ||
                    !in_array($current, $this->highlightDetails)
                ) {
                    continue;
                }
                foreach ($this->highlightDetails[$current] as $hl) {
                    if (!empty($hl)) {
                        return [
                            'snippet' => $hl,
                            'caption' => $this->getSnippetCaption($current),
                        ];
                    }
                }
            }

            // No preferred field found, so try for a non-forbidden field:
            if (
                isset($this->highlightDetails)
                && is_array($this->highlightDetails)
            ) {
                foreach ($this->highlightDetails as $key => $value) {
                    if ($value && !in_array($key, $this->forbiddenSnippetFields)) {
                        foreach ($value as $hl) {
                            if (!empty($hl)) {
                                return [
                                    'snippet' => $hl,
                                    'caption' => $this->getSnippetCaption($key),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // If we got this far, no snippet was found:
        return false;
    }
}
