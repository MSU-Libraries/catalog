#!/bin/bash

echo 'ERROR : DO NOT USE THIS SCRIPT ANYMORE !!' >&2
echo 'This script is deprecated and will be remove soon, use folio-harvest-and-import.sh instead' >&2
echo 'ERROR : DO NOT USE THIS SCRIPT ANYMORE !!'
echo 'This script is deprecated and will be remove soon, use folio-harvest-and-import.sh instead'

# Sourcing other file needed for the requested action
# shellcheck disable=SC2128
if [[ $BASH_SOURCE = */* ]]; then
    script_path=${BASH_SOURCE%/*}/
else
    script_path=./
fi

script_path="${script_path}folio-harvest-and-import.sh"

${script_path} "$@"