package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;
import org.marc4j.marc.DataField;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;

public class StaticDefaultMixin extends SolrIndexerMixin {

    /**
     *  Get all data values from the field and subfield combination, but if not present, use
     *  the provided default value.
     *  @param record - record to search
     *  @param field - marc field to use if present
     *  @param subfield - marc subfield to use if present
     *  @param defaultValue - default value to return if the marc data was not found
     *  @return a Set of values in the field and subfield combination, or the default value in a Set
     */
    public Set getStaticDefaultStr(final Record record, String field, String subfield, String defaultValue) {
        Set<String> result = new LinkedHashSet<String>();
        List<VariableField> lvf = record.getVariableFields(field);

        for (VariableField vf : lvf)
        {
            DataField df = (DataField)vf;
            List<Subfield> lsf = df.getSubfields(subfield);
            for (Subfield sf : lsf) {
                result.add(sf.getData());
            }
        }

        if (result.size() == 0) {
            result.add(defaultValue);
        }

        return result;
    }

}

