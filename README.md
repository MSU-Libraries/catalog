# VuFind Public Catalog

## Installation and Configuration

Please refer to our documentation for instructions on how to setup and configure an initial
VuFind Public Catalog instance:

* [Technical User Documentation](https://msu-libraries.github.io/catalog/)

## Functionality

### Search

#### Main Search Box

A generic search under the Catalog tab in the VuFind public catalog searches a particular set of fields, weighted according to importance. Records with term matches in more heavily weighted fields will appear higher up in results lists. 

```
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
The weightings above (e.g. the `^750` part of `title_short^750`) add a relative boost to records in which search terms appear in the `title_short` field. 


A number of fielded searches can beSearch configurations are specified in the local [`searchspecs.yaml` file](https://gitlab.msu.edu/msu-libraries/devops/catalog/-/blob/main/vufind/local/config/vufind/searchspecs.yaml). The default 

## Background & How It Works

### Indexing

#### The Local Catalog (FOLIO)

All FOLIO inventory records are updated in the VuFind Public Catalog index at least once daily. After an initial full load of all records, incremental updates to new, changed, or deleted records are be updated at a specific hour every evening. 

All MARC bibliographic records stored in the FOLIO inventory are harvested (via OAI-PMH) and indexed in the public catalog according to a particular indexing scheme. The default settings for this scheme come from [VuFind’s marc.properties file](https://github.com/vufind-org/vufind/blob/release-8.1/import/marc.properties), but some are extended or overridden by MSUL’s own local customizations in the [marc_local.properties file](https://gitlab.msu.edu/msu-libraries/devops/catalog/-/blob/main/vufind/local/import/marc_local.properties). 

These configuration files are quite powerful and allow for a good deal of customization. For example, the `title` field (used in search results) is specified by VuFind as `title = 245ab, first`. This indicates that the `245` fields `a` and `b` are concatenated to form the title. The `first` argument indicates that if there are multiple `245` fields, only the first one will be selected and indexed. This default title specification is, however, overridden by a local configuration:

```
title = 245ab, (pattern_map.title), first
pattern_map.title.pattern_0 = (.*)\s?\/$=>$1
pattern_map.title.pattern_1 = keepRaw
```

The new configuration has the additional specification `(pattern_map.title)`, which processes the text of the title to eliminate the trailing slash (`/`) at the end of the `245a` subfield. Small changes to the text of any given field can be made using this programmatic method. Changes to individual fields should be made in FOLIO.

Multi-valued fields can be populated from a whole set of fields:
```
topic_facet = 600x:610x:611x:630x:648x:650a:650x:651x:655x
```
The topic facet pulls values from the MARC fields specified above. 

In addition to the bibliographic content, each record pulled from FOLIO is amended to include some holdings-specific information, which allows VuFind to provide real-time information about the status of individual items.  
```
952	f	f	|a Michigan State Unversity-Library of Michigan  |b Michigan State University  |c MSU Special Collections  |d MSU Special Collections - Comic Art  |t 0  |e PN6727.K53 K5 1994  |h Library of Congress classification  |i Printed Material  |n 1 
```
This data includes the functional call number in `952e` and other data important to search and browse functionality within the public catalog. Subfields `c` and `d` are used to populate the `Location` facet in search results.

#### Holdings Link Management

TODO


