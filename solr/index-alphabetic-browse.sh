#!/bin/bash

#####################################################
# Build java command
#####################################################
if [ "$JAVA_HOME" ]
then
  JAVA="$JAVA_HOME/bin/java"
else
  JAVA="java"
fi


##################################################
# Set VUFIND_HOME
##################################################
if [ -z "$VUFIND_HOME" ]
then
  # set VUFIND_HOME to the absolute path of the directory containing this script
  # https://stackoverflow.com/questions/4774054/reliable-way-for-a-bash-script-to-get-the-full-path-to-itself
  VUFIND_HOME="$(cd "$(dirname "$0")" && pwd -P)"
  if [ -z "$VUFIND_HOME" ]
  then
    exit 1
  fi
fi


if [ -z "$SOLR_HOME" ]
then
  SOLR_HOME="$VUFIND_HOME/solr/vufind"
fi

# This can point to an external Solr in e.g. a Docker container
if [ -z "$SOLR_JAR_PATH" ]
then
  SOLR_JAR_PATH="${SOLR_HOME}/../vendor"
fi

set -e
set -x

cd "$(dirname "$0")/import"
SOLRMARC_CLASSPATH=$(echo solrmarc_core*.jar)
if [[ $(wc -w <<<"$SOLRMARC_CLASSPATH") -gt 1 ]]
then
  echo "Error: more than one solrmarc_core*.jar in import/; exiting."
  exit 1
fi
CLASSPATH="browse-indexing.jar:${SOLRMARC_CLASSPATH}:${VUFIND_HOME}/import/lib/*:${SOLR_HOME}/jars/*:${SOLR_JAR_PATH}/modules/analysis-extras/lib/*:${SOLR_JAR_PATH}/server/solr-webapp/webapp/WEB-INF/lib/*"

# make index work with replicated index
# current index is stored in the last line of index.properties
function locate_index
{
    local indexDir=$1
    # default value
    local subDir="index"

    if [ -e "$indexDir/index.properties" ]
    then
        # read it into an array
        readarray farr < "$indexDir/index.properties"
        # get the last line
        indexline="${farr[${#farr[@]}-1]}"
        # parse the lastline to just get the filename
        subDir=${indexline#index=}
    fi

    echo "$indexDir/$subDir"
}

bib_index=$(locate_index "${SOLR_HOME}/biblio")
auth_index=$(locate_index "${SOLR_HOME}/authority")
index_dir="${SOLR_HOME}/alphabetical_browse"

mkdir -p "$index_dir"

function verbose
{
    LOG_TS=$(date +%Y-%m-%d\ %H:%M:%S)
    MSG="[${LOG_TS}] $1"
    echo "${MSG}"
}

function build_browse
{
    browse=$1
    field=$2
    skip_authority=$3

    extra_jvm_opts=$4

    verbose "Build browse: ${browse}"

    verbose "  Get the browse headings"
    if [ "$skip_authority" = "1" ]; then
        # shellcheck disable=SC2086
        # extra_jvm_opts may have several options (not currently used), in which case we want it to expand
        if ! output=$($JAVA ${extra_jvm_opts} -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp "$CLASSPATH" org.vufind.solr.indexing.PrintBrowseHeadings "$bib_index" "$field" "${browse}.tmp" 2>&1); then
            echo "ERROR: Failed to create browse headings for ${browse}. ${output}."
            exit 1
        fi
    else
        # shellcheck disable=SC2086
        # extra_jvm_opts may have several options (not currently used), in which case we want it to expand
        if ! output=$($JAVA ${extra_jvm_opts} -Dfile.encoding="UTF-8" -Dfield.preferred=heading -Dfield.insteadof=use_for -cp "$CLASSPATH" org.vufind.solr.indexing.PrintBrowseHeadings "$bib_index" "$field" "$auth_index" "${browse}.tmp" 2>&1); then
            echo "ERROR: Failed to create browse headings for ${browse}. ${output}."
            exit 1
        fi
    fi

    verbose "  Sort the browse headings"
    if ! output=$(sort -T /var/tmp --buffer-size=1G -u -t$'\1' -k1 "${browse}.tmp" -o "sorted-${browse}.tmp" 2>&1); then
        echo "ERROR: Failed to sort ${browse}. ${output}."
        exit 1
    fi

    verbose "  Build the SQLite database"
    if ! output=$($JAVA -Dfile.encoding="UTF-8" -cp "$CLASSPATH" org.vufind.solr.indexing.CreateBrowseSQLite "sorted-${browse}.tmp" "${browse}_browse.db" 2>&1); then
        echo "ERROR: Failed to build the SQLite database for ${browse}. ${output}."
        exit 1
    fi

    verbose "  Clear up temp files"
    if ! output=$(rm -f -- *.tmp 2>&1); then
        echo "ERROR: Failed to clear out temp files for ${browse}. ${output}."
        exit 1
    fi

    verbose "  Move the new database to the index directory"
    if ! output=$(mv "${browse}_browse.db" "$index_dir/${browse}_browse.db-updated" 2>&1); then
        echo "ERROR: Failed to move ${browse}_browse.db database to ${index_dir}/${browse}_browse.db-updated. ${output}."
        exit 1
    fi

    verbose "  Indicate that the new database is ready for use"
    if ! output=$(touch "$index_dir/${browse}_browse.db-ready" 2>&1); then
        echo "ERROR: Failed to mark the new ${browse} database as ready for use. ${output}."
        exit 1
    fi
}

# These parameters should match the ones in solr/vufind/biblio/conf/solrconfig.xml - BrowseRequestHandler
build_browse "hierarchy" "hierarchy_browse"
build_browse "title" "title_browse" 0 "-Dbibleech=StoredFieldLeech -Dsortfield=title_browse_sort -Dvaluefield=title_browse -Dbrowse.normalizer=org.vufind.util.TitleNormalizer"
build_browse "topic" "topic_browse"
build_browse "author" "author_browse"
build_browse "lcc" "callnumber-raw" 1 "-Dbrowse.normalizer=org.vufind.util.LCCallNormalizer"
build_browse "dewey" "dewey-raw" 1 "-Dbrowse.normalizer=org.vufind.util.DeweyCallNormalizer"
build_browse "series" "series_browse_str_mv"
