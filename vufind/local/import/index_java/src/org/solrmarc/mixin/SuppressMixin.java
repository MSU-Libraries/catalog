package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;

public class SuppressMixin extends SolrIndexerMixin {

    public List<String> getSuppress(final Record record) {
        List<String> result = new ArrayList<String>();
        List<VariableField> fields = record.getVariableFields("999");
        List<Subfield> subfields = getSubfieldsMatching(fields, "t");
        if (subfields.stream().anyMatch(f -> "1".equals(f.getData()))) {
            result.add("true");
        } else {
            result.add("false");
        }
        return result;
    }

    private List<Subfield> getSubfieldsMatching(List<VariableField> fields, String subfieldCodes) {
        ArrayList<Subfield> subfields = new ArrayList<Subfield>();
        for (VariableField vf : fields) {
            if (!(vf instanceof DataField))
                return(subfields);
            DataField df = (DataField)vf;
            subfields.addAll(df.getSubfields(subfieldCodes));
        }
        return(subfields);
    }

}
