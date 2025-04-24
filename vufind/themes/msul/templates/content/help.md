<!-- markdownlint-disable-next-line MD041 -->
## Public Catalog Functionality

The VuFind-based Public Catalog provides several different options for
searching and retrieving locally held materials, such as books and other
media, and journal articles and other online resources accessible to
MSUL users.

### Search

The public catalog search options are listed under three tabs on most
VuFind pages: "Catalog", "Journal Articles", and "All". Because of the
differing sources for the underlying data, and our ability to change
that data (or not), each tab offers a different set of options for
limiting search queries and faceting on the results.

* **Catalog**: This is the search of the main catalog index, which contains
  records for all unsuppressed records in FOLIO plus 1.8 million records
  stored in Holdings Link Management (HLM / FOLIO eHoldings app). Robust
  options for limiting searches and faceting results are available according
  to the comprehensive descriptive work of our technical services teams.
* **Journal Articles**: This is currently a search against all of EDS but
  in the future will be limited to exclude our local catalog and eBooks
  from our custom eBook catalogs.
* **All**: A combined view of all materials both from our local catalog and
EDS. Since this federated search has to display data from unlike sources,
the more advanced search and faceting options can't be very well recreated
here (although some improvements could be the target of future work).

#### Main Search Box

A generic search under the Catalog tab in the VuFind public catalog searches
a particular set of fields, weighted according to importance. Records with
term matches in more heavily weighted fields will appear higher up in
search results.

```yaml
AllFields:
  DismaxFields:
    - title_short^750
    - title_full_unstemmed^600
    - title_full^400
    - title^500
    - title_alt^200
    - title_new^100
    - series^50
    - series2^30
    - author^300
    - contents^10
    - topic_unstemmed^550
    - topic^500
    - geographic^300
    - genre^300
    - allfields_unstemmed^10
    - fulltext_unstemmed^10
    - allfields
    - fulltext
    - description
    - isbn
    - issn
    - long_lat_display
```

The weightings above (e.g. the `^750` part of `title_short^750`) add a
relative boost to records in which search terms appear in the specified
field. This ensures that a term that matches the title of an item will be
ranked higher (and appear earlier) in search results than if the match were
in, say, the description of the item.

The presence of a generic `allfields` field in the list indicates that all
fields indexed in VuFind should return results in the default search box.
Searching within the confines of a limiter, such as author or title,
simply reduces the number of results.

The search configurations for each of these fielded searches are
specified in the local
[`searchspecs.yaml` file](https://gitlab.msu.edu/msu-libraries/devops/catalog/-/blob/main/vufind/local/config/vufind/searchspecs.yaml).
For each particular search type, the fields searched are listed
in the same format as above. The author search for example:

```yaml
Author:
  DismaxFields:
    - author^100
    - author2
    - author_additional
    - author_corporate
    - author_variant
    - author2_variant
  DismaxHandler: edismax
```

#### Advanced Search

The advanced search interface provides a similar, though not identical, set
of fields to search by. This interface can be customized and we expect with
use we might identify particular options to add or remove.

#### Search Tools

The search results page includes a few options to save and send results.

* **Save Search**: Requires login. Saved searches will appear on your
  user account page.
* **Email Search**: No login required. Send the URL of your search to
  any email address.
* **RSS Feed**: TODO
* **With Selected: Email**: No login required. In addition to emailing
  a search URL, as above, you can also select some or all records on a
  page and send them to an email address of your choosing.
* **With Selected: Export**: No login required. All built-in VuFind
  export options are currently turned on: RefWorks, EndNote, EndNoteWeb,
  MARC, MARCXML, RDF, BibTeX, and RIS.
* **With Selected: Print**: Print out selected sections of the search
  results page. To print all results, the same result can be obtained
  by using the File-->Print option from the browser menu.
* **With Selected: Save**: Requires login. Save selected records to a
  list of your creation.
* *Citation Export*: There are no default export options in the VuFind
  catalog that format results according to a particular citation style
  or format. These options are available and working on individual
  record pages.

### Browse

The main search box dropdown menu offers 4 "Browse" options which instead
of returning item records, return left-anchored lists according to the field
selected: Author, Title, Topic, or Call Number. This same functionality
is available via the "Browse Alphabetically" link at the bottom of every page.

A more elaborate faceted browsing experience is available by using the
"Browse the Catalog" link also located at the bottom of every page. This
section allows you to combine browsing across different fields to arrive
at views like:

* [American Cooking by era](https://catalog-beta.lib.msu.edu/vufind/Browse/Era?findby=topic&category=&query=%22Cooking%2C+American%22&query_field=topic_facet&facet_field=era_facet)
* [Almanacs by Author](https://catalog-beta.lib.msu.edu/vufind/Browse/Author?findby=genre&category=&query=%22Almanacs%22&query_field=genre_facet&facet_field=author_facet)
* [Most Common Topics in the 17th Century](https://catalog-beta.lib.msu.edu/vufind/Browse/Topic?findby=era&category=&query=%2217th+century%22&query_field=era_facet&facet_field=topic_facet)

## Background & How It Works

### Indexing

#### The Local Catalog (FOLIO)

All FOLIO inventory records are updated in the VuFind Public Catalog
index at least once daily. After an initial full load of all records,
incremental updates to new, changed, or deleted records are updated
at a specific hour every evening.

All MARC bibliographic records stored in the FOLIO inventory are
harvested (via OAI-PMH) and indexed in the public catalog according
to a particular indexing scheme. The default settings for this scheme
come from [VuFind’s marc.properties file](https://github.com/vufind-org/vufind/blob/release-8.1/import/marc.properties),
but some are extended or overridden by MSUL’s own local customizations
in the [marc_local.properties file](https://gitlab.msu.edu/msu-libraries/devops/catalog/-/blob/main/vufind/local/import/marc_local.properties).

These configuration files are quite powerful and allow for a good deal of
customization. For example, the `title` field (used in search results)
is specified by VuFind as `title = 245ab, first`. This indicates that
the `245` fields `a` and `b` are concatenated to form the title. The
`first` argument indicates that if there are multiple `245` fields,
only the first one will be selected and indexed. This default title
specification is, however, overridden by a local configuration:

```ini
title = 245ab, (pattern_map.title), first
pattern_map.title.pattern_0 = (.*)\s?\/$=>$1
pattern_map.title.pattern_1 = keepRaw
```

The new configuration has the additional specification `(pattern_map.title)`,
which processes the text of the title to eliminate the trailing slash (`/`)
at the end of the `245a` subfield. Small *global* changes to the text of any
given field can be made using this programmatic method. Changes to field
values for individual records should be made in FOLIO.

Multi-valued fields, such as Topic, can be populated from a whole set of
fields:

```ini
topic_facet = 600x:610x:611x:630x:648x:650a:650x:651x:655x
```

The topic facet field pulls values from all of the MARC fields specified above.

In addition to the bibliographic content, each record pulled from FOLIO is
appended with holdings-specific information, which allows VuFind to provide
information about the location of individual items. Real time information about
availability, on the other hand, is pulled from FOLIO any time a user loads a page.

<!-- markdownlint-disable MD013 -->
```txt
952 f   f   |a Michigan State Unversity-Library of Michigan  |b Michigan State University  |c MSU Special Collections  |d MSU Special Collections - Comic Art  |t 0  |e PN6727.K53 K5 1994  |h Library of Congress classification  |i Printed Material  |n 1
```
<!-- markdownlint-enable MD013 -->

This data includes the functional call number in `952e` and other data
important to search and browse functionality within the public catalog.
Subfields `c` and `d` are used to populate the `Location` facet in
search results.

#### Holdings Link Management

TODO

## Installation and Configuration

Please refer to our documentation for instructions on how to setup and
configure an initial VuFind Public Catalog instance:

* [Technical User Documentation](https://msu-libraries.github.io/catalog/)

## Help and Feature Requests

If you have any particular questions, or features you'd like to see, please
do get in touch at **[lib.dl.cdawg@msu.edu](mailto:lib.dl.cdawg@msu.edu)**.
