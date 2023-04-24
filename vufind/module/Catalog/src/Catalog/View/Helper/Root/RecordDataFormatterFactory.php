<?php

namespace Catalog\View\Helper\Root;

use VuFind\View\Helper\Root\RecordDataFormatter\SpecBuilder;

class RecordDataFormatterFactory extends \VuFind\View\Helper\Root\RecordDataFormatterFactory
{
    public function getDefaultCoreSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultCoreSpecs());

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
        $spec->setLine('Online Access', 'getDateCoverage');

        # Reorder the fields to get Genre next to Subjects
        $spec->reorderKeys(["Published in", "New Title", "Previous Title", "Authors",
                "Format", "Language", "Published", "Edition", "Series",
                "Subjects", "Genre", "Physical Description",
                "child_records", "Online Access", "Related Items", "Notes", "Tags"]);

        return $spec->getArray();
    }

    public function getDefaultDescriptionSpecs()
    {
        $spec = new SpecBuilder(parent::getDefaultDescriptionSpecs());

        # Remove Physical Description
        $spec->setLine('Physical Description', null);

        return $spec->getArray();
    }
}
