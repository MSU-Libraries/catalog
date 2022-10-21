package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;
import org.marc4j.marc.DataField;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;

public class StaticDefaultMixin extends SolrIndexerMixin {

    /**
     *  Get the data from the field and subfield combination, but if not present, use the provided
     *  default value.
     *  @param record - record to search
     *  @param field - marc field to use if present
     *  @param subfield - marc subfield to use if present
     *  @param defaultValue - default value to return if the marc data was not found
     *  @return the first value in the field and subfield combination, or the default value
     */
    public String getStaticDefaultStr(final Record record, String field, String subfield, String defaultValue) {
        String result = defaultValue;
        List<VariableField> fieldVals = record.getVariableFields(field);

        if (!fieldVals.isEmpty()) {
                // Only use the first value from the field and sub field since we're returning a string
                List<Subfield> subVals = ((DataField)fieldVals.get(0)).getSubfields(subfield);
                if (!subVals.isEmpty()) {
                        result = subVals.get(0).getData();
                }
        }

        return result;
    }

}

