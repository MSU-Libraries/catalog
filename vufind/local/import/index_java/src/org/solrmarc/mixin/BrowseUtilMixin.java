package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;

import org.marc4j.marc.Record;

import org.solrmarc.index.extractor.formatter.FieldFormatter;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.index.SolrIndexerMixin;
import org.solrmarc.tools.DataUtil;


public class BrowseUtilMixin extends SolrIndexerMixin {

    private static final String TITLE_SPEC = "245abkp";
    private static final String AUTH_SPEC = "245ab";
    private static final String ALT_SPEC = "100t:130adfgklnpst:240a:246abnp:505t:700t:710t:711t:730adfgklnpst:740a:LNK245anbp";

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
        List<String> alt = indexer.getFieldListAsList(record, ALT_SPEC);
        if (!alt.isEmpty()) {
            result.addAll(alt);
        }
        return result;
    }

    /**
     * Return a list with the title, auth title and alt titles, in order, filtered with titleSortLower.
     */
    public List<String> getTitleBrowseSort(final Record record) {
        EnumSet<FieldFormatter.eCleanVal> cleanValue = DataUtil.getCleanValForParam("titleSortLower");
        return getTitleBrowse(record).stream()
            .map(s -> DataUtil.cleanByVal(s, cleanValue))
            .collect(Collectors.toList());
    }
}
