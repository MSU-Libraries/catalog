<?php
/**
 * Console command: index course reserves into Solr.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Catalog\Command\Util;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Reserves\CsvReader;
use VuFindSearch\Backend\Solr\Document\UpdateDocument;
use VuFindSearch\Backend\Solr\Record\SerializableRecord;
use VuFindSearch\Backend\Solr\Command\RawJsonSearchCommand;
use VuFind\Search\Factory\SolrDefaultBackendFactory;

/**
 * Console command: index course reserves into Solr.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class IndexReservesCommand extends \VuFindConsole\Command\Util\IndexReservesCommand
{

    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/index_reserves';

    /**
     * Default delimiter for reading files
     *
     * @var string
     */
    protected $defaultDelimiter = ',';

    /**
     * Default template for reading files
     *
     * @var string
     */
    protected $defaultTemplate = 'BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT';

    /**
     * Keys required in the data to create a valid reserves index.
     *
     * @var string[]
     */
    protected $requiredKeys = ['INSTRUCTOR_ID', 'COURSE_ID', 'DEPARTMENT_ID'];

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Course reserves index builder')
            ->setHelp(
                'This tool populates your course reserves Solr index. If run with'
                . ' no options, it will attempt to load data from your ILS.'
                . ' Switches may be used to index from delimited files instead.'
            )->addOption(
                'filename',
                'f',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'file(s) containing delimited values'
            )->addOption(
                'delimiter',
                'd',
                InputOption::VALUE_REQUIRED,
                'specifies the delimiter used in file(s)',
                $this->defaultDelimiter
            )->addOption(
                'template',
                't',
                InputOption::VALUE_REQUIRED,
                'provides a template showing where important values can be found '
                . "within the file.\nThe template is a comma-separated list of "
                . "values.  Choose from:\n"
                . "BIB_ID     - bibliographic ID\n"
                . "COURSE     - course name\n"
                . "DEPARTMENT - department name\n"
                . "INSTRUCTOR - instructor name\n"
                . "SKIP       - ignore data in this position\n",
                $this->defaultTemplate
            );
    }

    /**
     * Build the reserves index from date returned by the ILS driver,
     * specifically: getInstructors, getDepartments, getCourses, findReserves
     *
     * @param array $instructors Array of instructors $instructor_id => $instructor
     * @param array $courses     Array of courses     $course_id => $course
     * @param array $departments Array of department  $dept_id => $department
     * @param array $reserves    Array of reserves records from driver's
     * findReserves.
     *
     * @return UpdateDocument
     */
    protected function buildReservesIndex(
        $instructors,
        $courses,
        $departments,
        $reserves
    ) {
        $index = [];
        foreach ($reserves as $record) {
            $requiredKeysFound
                = count(array_intersect(array_keys($record), $this->requiredKeys));
            if ($requiredKeysFound < count($this->requiredKeys)) {
                throw new \Exception(
                    implode(' and/or ', $this->requiredKeys) . ' fields ' .
                    'not present in reserve records. Please update ILS driver.'
                );
            }
            $instructorId = $record['INSTRUCTOR_ID'];
            $courseId = $record['COURSE_ID'];
            $departmentId = $record['DEPARTMENT_ID'];
            $id = $courseId . '|' . $instructorId . '|' . $departmentId;

            if (!isset($index[$id])) {
                $index[$id] = [
                    'id' => $id,
                    'bib_id' => [],
                    'instructor_id' => $instructorId,
                    'instructor' => $instructors[$instructorId] ?? '',
                    'course_id' => $courseId,
                    'course' => $courses[$courseId] ?? '',
                    'department_id' => $departmentId,
                    'department' => $departments[$departmentId] ?? ''
                ];
            }
            $index[$id]['bib_id'][] = $record['BIB_ID'];
        }

        $updates = new UpdateDocument();
        foreach ($index as $id => $data) {
            if (!empty($data['bib_id'])) {
                $updates->addRecord(new SerializableRecord($data));
            }
        }
        return $updates;
    }


    /**
     * Construct a CSV reader.
     *
     * @param array|string $files     Array of files to load (or single filename).
     * @param string       $delimiter Delimiter used by file(s).
     * @param string       $template  Template showing field positions within
     * file(s).  Comma-separated list containing BIB_ID, INSTRUCTOR, COURSE,
     * DEPARTMENT and/or SKIP.  Default = BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT
     *
     * @return CsvReader
     */
    protected function getCsvReader(
        $files,
        string $delimiter,
        string $template
    ): CsvReader {
        return new CsvReader($files, $delimiter, $template);
    }

    protected function revalidateLocalRecords($output)
    {
        $output->writeln("Validating all locally prefixed Solr records to ensure there are no duplicate folio prefixed records");

        // Query all local prefixed Solr records
        $params = new \VuFindSearch\ParamBag();
        $query = new \VuFindSearch\Query\Query(
            'id:local\.*'
        );
        $params->set('fl', 'id');
        $command = new RawJsonSearchCommand(
            'Solr',
            $query,
            0,
            10000, // TODO -- don't love that this is an arbitrary number
            $params
        );

        $searchService = $this->getProperty($this->solr, 'searchService');
        $response = $searchService->invoke($command)->getResult();

        $output->writeln("Found local record count: " . $response->response->numFound);

        foreach ($response->response->docs as $doc) {
            // Check and see if there is an equivilent folo prefixed Solr record
            $doc_params = new \VuFindSearch\ParamBag();
            $doc_query = new \VuFindSearch\Query\Query(
                'id:folio\.' . str_replace("local.", "", $doc->id)
            );
            $doc_params->set('fl', 'id');
            $doc_command = new RawJsonSearchCommand(
                'Solr',
                $doc_query,
                0,
                1,
                $doc_params
            );

            $doc_response = $searchService->invoke($doc_command)->getResult();

            // If so, delete the local prefixed Solr record
            if ($doc_response->response->numFound == 1) {
                $output->writeln("Found matching folio prefixed record in solr. deleting  " . $doc->id);
                $this->solr->deleteRecords('Solr', [$doc->id]);
                $this->solr->save('Solr', $updates);
                $this->solr->commit('Solr');
            }
        }
        $output->writeln("Completed validation of locally prefixed records");
    }

    protected function validateReserves($reserves, $output)
    {
        // Verify Folio records exist in Solr
        $idx = 0;
        foreach ($reserves as $reserve) {
            $output->writeln("-- Progress: " . $idx . "/" . count($reserves) . " --");

            // Skip HLM records
            if (str_contains($reserve['BIB_ID'],'hlm.')) {
                $output->writeln("Skipping HLM record " . $reserve['BIB_ID']);
                $idx += 1;
                continue;
            }
            // Check Biblio index in Solr for the record, continue if found
            $params = new \VuFindSearch\ParamBag();
            $query = new \VuFindSearch\Query\Query(
                'id:*' . str_replace('folio.', '', $reserve['BIB_ID']), # Match any prefix in solr
            );
            $params->set('fl', 'id');
            $command = new RawJsonSearchCommand(
                'Solr',
                $query,
                0,
                1,
                $params
            );

            $searchService = $this->getProperty($this->solr, 'searchService');
            $response = $searchService->invoke($command)->getResult();

            // If not found this is a professor owned copy, Create a Solr record for it
            // replacing the prefix
            if ($response->response->numFound == 0 || str_contains($response->response->docs[0]->id, 'local.')) {
                $reserves[$idx]['BIB_ID'] = str_replace('folio.', 'local.', $reserve['BIB_ID']);
                $output->writeln("Updating/creating solr record for professor owned copy with id " . $reserves[$idx]['BIB_ID']);
                $this->createLocalSolrRecord($reserves[$idx], $output);
            }
            else {
                $output->writeln("Using found record in biblio index with folio prefix.");
                $reserves[$idx]['BIB_ID'] = $response->response->docs[0]->id;
            }
            $idx += 1;
        }
        return $reserves;
    }

    protected function createLocalSolrRecord($reserve, $output)
    {
        $instanceHrid = str_replace('local.', '', $reserve['BIB_ID']);
        $item = $this->catalog->getInstanceByBibId($instanceHrid);
        $holding = $this->catalog->getHolding($instanceHrid)['holdings'][0];

        $pubYear = is_array($item->publication) && count($item->publication) > 0 ? $item->publication[0]->dateOfPublication : '';
        $authors = is_array($item->contributors) ? array_column($item->contributors, 'name') : [];
        $firstAuthor = empty($authors) ? "" : $authors[0];
        $alternativeTitles = is_array($item->alternativeTitles) ? array_column($item->alternativeTitles, 'alternativeTitle') : [];

        $index = [
            'id' => $reserve['BIB_ID'],
            'ctrlnum' => [],
            'collection' => ['Catalog'],
            'institution' => ['Michigan State University'],
            'building' => [
                '0/MSU Main Library/',
                '1/MSU Main Library/Reserve - Circulation, 1 Center/'
            ],
            'fullrecord' =>
                '<oai_dc:dc>
                <dc:identifier>' . $reserve['BIB_ID'] . '</dc:identifier
                <dc:title>' . $item->title . '</dc:title>
                <dc:type>Book</dc:type>
                <dc:creator>' . ($firstAuthor) . '</dc:creator>
                <dc:date>' . $pubYear  . '</dc:date>
                </oai_dc:dc>',
            'record_format' => 'oai_dc',
            'spelling' => [],
            'language' => '', //$item->languages, (need to see if this an object or array)
            'format' => ['Book'],
            'author' => $authors,
            'spellingShingle' => [],
            'author_facet' => [],
            'author_varient' => [],
            'author_role' => [],
            'author2' => [],
            'author2_variant' => [],
            'author2_role' => [],
            'author_sort' => $firstAuthor,
            'title' => $item->title,
            'title_short' => $item->title . " /",
            'title_full' => $item->title . " / " . $firstAuthor,
            'title_fullStr' => $item->title . " / " . $firstAuthor,
            'title_full_unstemmed' => $item->title . " / " . $firstAuthor,
            'title_auth' => $item->title . " /",
            'title_alt' => $alternativeTitles,
            'title_sort' => $item->title,
            'publisher' => [],
            'publishDate' => [$pubYear],
            'physical' => [],
            'edition' => is_array($item->editions) ? implode(' ', $item->editions) : [],
            'contents' =>  [],
            'isbn' => [],
            'callnumber-first' => '',
            'callnumber-subject' => '',
            'callnumber-label' => $holding['callnumber'],
            'callnumber-sort' => $holding['callnumber'],
            'callnumber-raw' => [$holding['callnumber']],
            'callnumber-search' => [$holding['callnumber']],
            'topic' => [], //$item->subjects,
            'geographic' => [],
            'topic_facet' => [],
            'geographic_facet' => [],
            'illustrated' => '',
            'oclc_num' => [],
            'work_keys_str_mv' => [],
            'uuid_str' => $item->id,
            'first_indexed' => date('Y-m-d\TH:i:s\Z'),
            'last_indexed' => date('Y-m-d\TH:i:s\Z'),
            'callnumber-full_str_mv' => [$holding['callnumber_prefix'] . " " . $holding['callnumber']],
            'suppress-from-discovery_boolean' => false,
            'material-type_str_mv' => ['Physical Book'],
            'publisher_txtF_mv' => [],
        ];
        $updates = new UpdateDocument();
        $updates->addRecord(new SerializableRecord($index));
        $this->solr->deleteRecords('Solr', [$reserve['BIB_ID']]);
        $response = $this->solr->save('Solr', $updates);
        $commit_response = $this->solr->commit('Solr');
    }

    protected function getProperty($object, $property)
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Starting reserves processing");
        // Check time limit; increase if necessary:
        if (ini_get('max_execution_time') < 3600) {
            ini_set('max_execution_time', '3600');
        }

        $delimiter = $input->getOption('delimiter');
        $template = $input->getOption('template');

        if ($file = $input->getOption('filename')) {
            try {
                $reader = $this->getCsvReader($file, $delimiter, $template);
                $instructors = $reader->getInstructors();
                $courses = $reader->getCourses();
                $departments = $reader->getDepartments();
                $reserves = $reader->getReserves();
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
                return 1;
            }
        } elseif ($delimiter !== $this->defaultDelimiter) {
            $output->writeln('-d (delimiter) is meaningless without -f (filename)');
            return 1;
        } elseif ($template !== $this->defaultTemplate) {
            $output->writeln('-t (template) is meaningless without -f (filename)');
            return 1;
        } else {
            try {
                $this->revalidateLocalRecords($output);
                // Connect to ILS and load data
                $instructors = [];
                $courses = [];
                $departments = [];
                $output->writeln("Retrieving instructors");
                $instructors = $this->catalog->getInstructors();
                $output->writeln("Found instructor count: " . count($instructors));
                $output->writeln("Retrieving courses");
                $courses = $this->catalog->getCourses();
                $output->writeln("Found course count: " . count($courses));
                $output->writeln("Retrieving departments");
                $departments = $this->catalog->getDepartments();
                $output->writeln("Found department count: " . count($departments));
                $output->writeln("Retrieving course reserves");
                $reserves = $this->catalog->findReserves('', '', '');
                $output->writeln("Found reserve count: " . count($reserves));
                $output->writeln("Validating and mapping reserves to correct Solr record");
                $reserves = $this->validateReserves($reserves, $output);
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
                return 1;
            }
        }

        // Make sure we have reserves and at least one of: instructors, courses,
        // departments:
        if ((!empty($instructors) || !empty($courses) || !empty($departments))
            && !empty($reserves)
        ) {
            // Delete existing records
            $output->writeln("Clearing existing reserves");
            $this->solr->deleteAll('SolrReserves');

            // Build and Save the index
            $output->writeln("Building new reserves");
            $index = $this->buildReservesIndex(
                $instructors,
                $courses,
                $departments,
                $reserves
            );

            // Build and Save the index
            $output->writeln("Writing new reserves");
            $this->solr->save('SolrReserves', $index);

            // Commit and Optimize the Solr Index
            $this->solr->commit('SolrReserves');
            $this->solr->optimize('SolrReserves');

            $output->writeln('Successfully loaded ' . count($reserves) . ' rows.');
            return 0;
        }
        $missing = array_merge(
            empty($instructors) ? ['instructors'] : [],
            empty($courses) ? ['courses'] : [],
            empty($departments) ? ['departments'] : [],
            empty($reserves) ? ['reserves'] : []
        );
        $output->writeln(
            'Unable to load data. No data found for: ' . implode(', ', $missing)
        );
        return 1;
    }
}

