package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;

import org.marc4j.marc.Record;

import org.solrmarc.index.SolrIndexer;
import org.solrmarc.index.SolrIndexerMixin;


public class BrowseUtilMixin extends SolrIndexerMixin {

    private static final String TITLE_SPEC = "245abkp";
    private static final String AUTH_SPEC = "245ab";
    private static final String ALT_SPEC = "100t:130adfgklnpst:240a:246abnp:505t:700t:710t:711t:730adfgklnpst:740a:LNK245anbp";
    private static final int MAX_ALT = 15;

    /**
     * Return a list with the title, auth title and alt titles, in order.
     */
    public List<String> getTitleBrowse(final Record record) {
        List<String> result = new ArrayList<String>();
        SolrIndexer indexer = SolrIndexer.instance();
        String title = indexer.getFirstFieldVal(record, TITLE_SPEC);
        if (title != null) {
            result.add(title);
        }
        String auth = indexer.getFirstFieldVal(record, AUTH_SPEC);
        if (auth != null) {
            result.add(auth);
        }
        List<String> alt = indexer.getFieldListAsList(record, ALT_SPEC + ",notunique");
        if (!alt.isEmpty()) {
            if (alt.size() > MAX_ALT) {
                alt = alt.subList(0, MAX_ALT);
            }
            result.addAll(alt);
        }
        return result;
    }

    /**
     * Return a list with the title, auth title and alt titles, in order, filtered with titleSortLower.
     */
    public List<String> getTitleBrowseSort(final Record record) {
        // The indicator is not used with something like:
        // DataUtil.cleanByVal(s, DataUtil.getCleanValForParam("titleSortLower"))
        // So we need to create a custom indexer for each field (it gets cached).
        // getFieldListCollector(record, tagStr, mapStr, collector) does that and is public.
        List<String> result = new ArrayList<String>();
        String title = getFirstValueForSpec(record, TITLE_SPEC + ",titleSortLower");
        if (title != null) {
            result.add(title);
        }
        String auth = getFirstValueForSpec(record, AUTH_SPEC + ",titleSortLower");
        if (auth != null) {
            result.add(auth);
        }
        List<String> alt = getValuesForSpec(record, ALT_SPEC + ",titleSortLower,notunique");
        if (!alt.isEmpty()) {
            if (alt.size() > MAX_ALT) {
                alt = alt.subList(0, MAX_ALT);
            }
            result.addAll(alt);
        }
        return result;
    }

    private static String getFirstValueForSpec(final Record record, String spec) {
        List<String> result = getValuesForSpec(record, spec + ",first");
        return result.isEmpty() ? null : result.iterator().next();
    }

    private static List<String> getValuesForSpec(final Record record, String spec) {
        SolrIndexer indexer = SolrIndexer.instance();
        List<String> result = new ArrayList<String>();
        indexer.getFieldListCollector(record, spec, null, result);
        return result;
    }
}
