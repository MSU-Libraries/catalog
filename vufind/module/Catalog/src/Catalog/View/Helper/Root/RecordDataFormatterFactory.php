<?php

namespace Catalog\View\Helper\Root;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory
{
    public function getDefaultCoreSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultCoreSpecs());

        # Add Genre to record page
        $spec->setTemplateLine(
            'Genre',
            'getGenre',
            'data-genre.phtml'
        );

        # Reorder the fields to get Genre next to Subjects
        $spec->reorderKeys(["Published in", "New Title", "Previous Title", "Authors",
                                "Format", "Language", "Published", "Edition", "Series",
                                "Subjects", "Genre", "child_records", "Online Access",
                                "Related Items", "Tags"]);

        return $spec->getArray();
    }
}
