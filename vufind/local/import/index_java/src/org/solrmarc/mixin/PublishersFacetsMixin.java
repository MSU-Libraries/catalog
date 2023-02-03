package org.solrmarc.mixin;

import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;
import org.vufind.index.PublisherTools;

public class PublishersFacetsMixin extends SolrIndexerMixin {

    /**
     * Get all available publishers from the record, formatted for facets.
     *
     * @param  record MARC record
     * @return set of publishers, facet formatted
     */
    public Set<String> getPublishersFacets(final Record record) {
        Set<String> publishers = new PublisherTools().getPublishers(record);
        Set<String> facets = new LinkedHashSet<String>();
        for (String pub : publishers) {
            pub = pub.replaceAll(",+\\s*$", "");
            pub = pub.replaceAll("\\s*[\\[\\]]\\s*", "");
            for (String part : pub.split("\\s*;\\s*")) {
                part = part.replaceFirst("^\"(.+?)\"$", "$1");
                part = part.replaceFirst("\\s*[,;:]$", "");
                if (!part.isEmpty() && !part.equals("publisher not identified")) {
                    facets.add(part);
                }
            }
        }
        return facets;
    }

}

