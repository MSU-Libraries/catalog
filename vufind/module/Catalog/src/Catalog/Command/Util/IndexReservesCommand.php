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
use Symfony\Component\Console\Output\OutputInterface;

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
            } catch (\Exception $e) {
                $output->writeln(date('Y-m-d H:i:s') . " " . $e->getMessage());
                return 1;
            }
        }

        // Make sure we have reserves and at least one of: instructors, courses,
        // departments:
        if (
            (!empty($instructors) || !empty($courses) || !empty($departments))
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
