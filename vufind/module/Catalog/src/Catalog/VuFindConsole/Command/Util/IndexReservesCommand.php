<?php

namespace Catalog\VuFindConsole\Command\Util;

use VuFindSearch\Backend\Solr\Document\UpdateDocument;
use VuFindSearch\Backend\Solr\Record\SerializableRecord;

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
            // check the bib_id value is not already in the array before adding
            if (!in_array($record['BIB_ID'], $index[$id]['bib_id'])) {
                $index[$id]['bib_id'][] = $record['BIB_ID'];
            }
        }

        $updates = new UpdateDocument();
        foreach ($index as $id => $data) {
            if (!empty($data['bib_id'])) {
                $updates->addRecord(new SerializableRecord($data));
            }
        }
        return $updates;
    }

}
