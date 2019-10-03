#!/bin/sh
# Simple bash script to build the latest language packs for osTicket

# Define Script Name
scriptname="lang-build"

# Define Usage
function usage {
    echo "usage: $scriptname [-b 1.11.x] [-L \"ar es_ES\"] [-k API_KEY_HERE]"
    echo "  -b 1.11.x           Tag of branch you want to build languages from"
    echo "                              Default: Latest branch"
    echo "  -L \"ar es_ES\"     Space delimited list of lang codes (optional)"
    echo "                              Default: All languages"
    echo "  -k API_KEY_HERE     Crowdin API Key (required)"
    exit 1
}

# Set vars from command input
# Options:
# b - Branch to build languages from
# L - Space-separated list of language code(s) to build
# k - Crowdin API Key
while getopts b:L:k: option
do
case "${option}"
in
b) BRANCH=${OPTARG};;
L) LANG=${OPTARG};;
k) readonly SECRET=${OPTARG};;
esac
done

# If no lang(s) specified, assume building for all
if [ -z "$LANG" ]; then
    declare -a langs=( "ar" "bg" "ca" "zh-CN" "hr" "cs" "da" "nl" "en-GB" "et" "fi" "fr" "de" "el" "he" "hu" "id" "it" "ja" "lt" "ms" "mn" "no" "fa" "pl" "pt-PT" "pt-BR" "ro" "ru" "sr-CS" "sk" "sl" "es-ES" "es-AR" "es-MX" "sv-SE" "tr" "uk" "vi" "ko" "mk" "th" "zh-TW" )
else
    langs=( $LANG )
fi

# If no branch specified assume building for latest
if [ -z "$BRANCH" ]; then
    BRANCH="1.11.x"
fi

# Run Build foreach lang
for lang in "${langs[@]}"; do
    php -dphar.readonly=0  setup/cli/manage.php i18n build -b $BRANCH -L $lang -k $SECRET
done
