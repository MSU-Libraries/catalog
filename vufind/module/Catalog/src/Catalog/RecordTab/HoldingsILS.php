<?php

namespace Catalog\RecordTab;

class HoldingsILS extends \VuFind\RecordTab\HoldingsILS
{

   /**
    * Support method used by template -- extract all unique holding statement
    * summaries, sort them, and return them as a string
    *
    * @param array $items       Items to search through.
    *
    * @return string
    */
   public function getHoldingsSummary($items)
   {
        $holdingsSummary = [];

        foreach ($items as $item) {
            foreach ($item['issues'] as $issue) {
                if (strlen($issue ?? '') > 0 && !in_array(rtrim($issue,','), $holdingsSummary) ) {
                    $holdingsSummary[] = rtrim($issue,',');
                }
            }
        }

        sort($holdingsSummary);

        return implode (', ', $holdingsSummary);
   }

}

