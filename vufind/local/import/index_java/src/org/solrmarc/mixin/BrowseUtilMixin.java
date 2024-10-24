package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;

import org.marc4j.marc.Record;

import org.solrmarc.index.SolrIndexer;
import org.solrmarc.index.SolrIndexerMixin;


public class BrowseUtilMixin extends SolrIndexerMixin {

    // Make sure these specs match the ones in marc_local.properties
    private static final String TITLE_SPEC = "245abkp";
    private static final String AUTH_SPEC = "245ab";
    private static final String ALT_SPEC = "100t:130adfgklnpst:240a:246abnp:505t:700t:710t:711t:730adfgklnpst:740a:LNK245anbp";
    private static final String ALL_SPEC = TITLE_SPEC + ":" + AUTH_SPEC + ":" + ALT_SPEC;
    private static final int MAX_TITLES = 20;

    /**
     * Return a list of unique titles, auth titles and alt titles, in order.
     */
    public List<String> getTitleBrowse(final Record record) {
        return getTitleBrowseNotUnique(record).stream()
            .distinct()
            .collect(Collectors.toList());
    }

    /**
     * Return a list with titles, auth titles and alt titles, in order, filtered with titleSortLower.
     * Titles that are duplicates when unfiltered are removed.
     */
    public List<String> getTitleBrowseSort(final Record record) {
        // The indicator is not used with something like:
        // DataUtil.cleanByVal(s, DataUtil.getCleanValForParam("titleSortLower"))
        // or with getTitleBrowseNotUnique().
        // So we need to create a custom indexer for each field (it gets cached).
        // getFieldListCollector(record, tagStr, mapStr, collector) does that and is public.
        List<String> result = getValuesForSpec(record, ALL_SPEC + ",titleSortLower,notunique");
        if (result.size() > MAX_TITLES) {
            result = result.subList(0, MAX_TITLES);
        }
        // Remove from the results the filtered titles that are related to duplicates in the unfiltered list
        // (duplicates might be different in filtered results, but the number of records must be the same).
        List<String> titleBrowseNotUniqe = getTitleBrowseNotUnique(record);
        return removeFromIndices(result, indicesOfDuplicates(titleBrowseNotUniqe));
    }

    private List<String> getTitleBrowseNotUnique(final Record record) {
        SolrIndexer indexer = SolrIndexer.instance();
        List<String> result = indexer.getFieldListAsList(record, ALL_SPEC + ",cleanEnd,notunique");
        if (result.size() > MAX_TITLES) {
            result = result.subList(0, MAX_TITLES);
        }
        return result;
    }

    private static List<Integer> indicesOfDuplicates(List l) {
        List<Integer> indices = new ArrayList<Integer>();
        if (l.size() < 2) {
            return indices;
        }
        for (int i=1; i<l.size(); i++) {
            if (l.subList(0, i).contains(l.get(i))) {
                indices.add(i);
            }
        }
        return indices;
    }

    private static List<String> removeFromIndices(List<String> l, List<Integer> indices) {
        List<String> result = new ArrayList<String>();
        for (int i=0; i<l.size(); i++) {
            if (!indices.contains(i)) {
                result.add(l.get(i));
            }
        }
        return result;
    }

    private static List<String> getValuesForSpec(final Record record, String spec) {
        SolrIndexer indexer = SolrIndexer.instance();
        List<String> result = new ArrayList<String>();
        indexer.getFieldListCollector(record, spec, null, result);
        return result;
    }
}
