# Harvesting
These are the commands should be run within the vufind Docker container.

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
./batch-import-march.sh folio
```
