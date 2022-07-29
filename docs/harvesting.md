# Harvesting
These are the commands should be run within the `vufind` or `harvest` Docker container.

The preferred method is to use the included wrapper script with this repository.
The [harvest-and-import.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/harvest-and-import.sh)
script can run either, or both, the harvest and import of data from FOLIO to Vufind. Use the `--help` flag
to get information on how to run that script.

But should you choose to run the commands included directly with Vufind, below is documentation on how to
do that.

## Harvesting from Folio
```bash
cd /usr/local/vufind/harvest
php harvest_oai.php

## This step is optional, it will combine the xml files into a single file
## to improve the speed of the next import step.
find *.xml | xargs xml_grep --wrap collection --cond "marc:record" > combined.xml
mkdir unmerged
mv *oai*.xml unmerged/
```

## Importing into Vufind
```bash
cd /usr/local/vufind/harvest
./batch-import-marc.sh folio
```
