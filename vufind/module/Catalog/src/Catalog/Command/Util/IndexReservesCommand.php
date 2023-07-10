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

    protected function revalidateLocalRecords($output)
    {
        $output->writeln(date('Y-m-d H:i:s') .
            " Validating all locally prefixed Solr records to ensure there are no duplicate folio prefixed records");

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

        $output->writeln(date('Y-m-d H:i:s') . " Found local record count: " . $response->response->numFound);

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
                $output->writeln(date('Y-m-d H:i:s') . " Found matching folio prefixed record in solr. Deleting: " . $doc->id);
                $this->solr->deleteRecords('Solr', [$doc->id]);
                $this->solr->commit('Solr');
            }
        }
        $output->writeln(date('Y-m-d H:i:s') . " Completed validation of locally prefixed records");
    }

    protected function validateReserves($reserves, $output)
    {
        // Verify Folio records exist in Solr
        $idx = 0;
        foreach ($reserves as $reserve) {
            $output->writeln(date('Y-m-d H:i:s') . " -- Progress: " . $idx . "/" . count($reserves) . " --");

            // Skip HLM records
            if (str_contains($reserve['BIB_ID'],'hlm.')) {
                $output->writeln(date('Y-m-d H:i:s') . " Skipping HLM record " . $reserve['BIB_ID']);
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
                $output->writeln(date('Y-m-d H:i:s') .
                                 " Updating/creating solr record for professor owned copy with id: " . $reserves[$idx]['BIB_ID']);
                $this->createLocalSolrRecord($reserves[$idx], $output);
            }
            else {
                $output->writeln(date('Y-m-d H:i:s') . " Using found record in biblio index with folio prefix.");
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
        $response = $this->solr->save('Solr', $updates);
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
        $output->writeln(date('Y-m-d H:i:s') . " Starting reserves processing");
        $startTime = date('Y-m-d H:i:s');

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
                $output->writeln(date('Y-m-d H:i:s') . " " . $e->getMessage());
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
                $output->writeln(date('Y-m-d H:i:s') . " Retrieving instructors");
                $instructors = $this->catalog->getInstructors();
                $output->writeln(date('Y-m-d H:i:s') . " Found instructor count: " . count($instructors));
                $output->writeln(date('Y-m-d H:i:s') . " Retrieving courses");
                $courses = $this->catalog->getCourses();
                $output->writeln(date('Y-m-d H:i:s') . " Found course count: " . count($courses));
                $output->writeln(date('Y-m-d H:i:s') . " Retrieving departments");
                $departments = $this->catalog->getDepartments();
                $output->writeln(date('Y-m-d H:i:s') . " Found department count: " . count($departments));
                $output->writeln(date('Y-m-d H:i:s') . " Retrieving course reserves");
                $reserves = $this->catalog->findReserves('', '', '');
                $output->writeln(date('Y-m-d H:i:s') . " Found reserve count: " . count($reserves));
                $output->writeln(date('Y-m-d H:i:s') . " Validating and mapping reserves to correct Solr record");
                $reserves = $this->validateReserves($reserves, $output);
            } catch (\Exception $e) {
                $output->writeln(date('Y-m-d H:i:s') . " " . $e->getMessage());
                return 1;
            }
        }

        // Make sure we have reserves and at least one of: instructors, courses,
        // departments:
        if ((!empty($instructors) || !empty($courses) || !empty($departments))
            && !empty($reserves)
        ) {
            // Delete existing records
            $output->writeln(date('Y-m-d H:i:s') . " Clearing existing reserves");
            $this->solr->deleteAll('SolrReserves');

            // Build and Save the index
            $output->writeln(date('Y-m-d H:i:s') . " Building new reserves");
            $index = $this->buildReservesIndex(
                $instructors,
                $courses,
                $departments,
                $reserves
            );

            // Build and Save the index
            $output->writeln(date('Y-m-d H:i:s') . " Writing new reserves");
            $this->solr->save('SolrReserves', $index);

            // Commit and Optimize the Solr Index
            $this->solr->commit('SolrReserves');
            $this->solr->commit('Solr');
            $this->solr->optimize('SolrReserves');

            $output->writeln(date('Y-m-d H:i:s') . ' Successfully loaded ' . count($reserves) . ' rows.');
            $endTime = date('Y-m-d H:i:s');
            $output->writeln(date('Y-m-d H:i:s') . " Stated at: " . $startTime . " Completed at: " . $endTime);
            return 0;
        }
        $missing = array_merge(
            empty($instructors) ? ['instructors'] : [],
            empty($courses) ? ['courses'] : [],
            empty($departments) ? ['departments'] : [],
            empty($reserves) ? ['reserves'] : []
        );
        $output->writeln(
            date('Y-m-d H:i:s') . ' Unable to load data. No data found for: ' . implode(', ', $missing)
        );
        return 1;
    }
}

