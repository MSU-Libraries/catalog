# Alphabrowse Internals

To understand this, it is best to read the first author's documentation:
[The VuFind Browse Handler](https://teaspoon-consulting.com/articles/vufind-browse-handler.html)
and then read [Alphabetical Heading Browse](https://vufind.org/wiki/indexing:alphabetical_heading_browse) in VuFind's documentation.

One thing that is not mentioned in that VuFind documentation page is the Solr field displayed for autocomplete is in `searches.ini`, section `Autocomplete_Types`.

Our customizations were to:
- add a new browse type: `series` (PC-110)
- add a new normalizer: `TitleNormalizer` (PC-362); it is part of VuFind now (VUFIND-1630)
- add alternative titles to title browse (PC-686, PC-1151); this required using a custom function for import (`BrowseUtilMixin`)
- improve autocomplete suggestions (PC-1151, PC-1181)

We use 2 new schema fields for title browse: `title_browse` and `title_browse_sort` to respectively display and sort title browse entries. They are both multivalued (as opposed to VuFind's default `title_fullStr` and `title_sort`), and the values should match between the 2 (in practice they don't when a filtered title ends up being empty). The values for these fields are created with `BrowseUtilMixin` (referenced by `marc_local.properties`), and used when creating the heading databases from `index-alphabetic-browse.sh`.
