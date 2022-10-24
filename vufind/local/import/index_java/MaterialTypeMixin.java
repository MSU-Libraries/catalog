package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;

public class MaterialTypeMixin extends SolrIndexerMixin {

    public List<String> getMaterialType(final Record record) {
        List<String> contentTypes = getValuesMatching(record, "336", "a");
        List<String> mediaTypes = getValuesMatching(record, "337", "a");
        List<String> carrierTypes = getValuesMatching(record, "338", "a");

        List<String> result = new ArrayList<String>();

        if (contentTypes.contains("text") &&
                mediaTypes.contains("unmediated") &&
                carrierTypes.contains("volume"))
            result.add("Physical Book");

        if (contentTypes.contains("text") &&
                mediaTypes.contains("computer") &&
                carrierTypes.contains("online resource"))
            result.add("Electronic Book");

        if (contentTypes.contains("two-dimensional moving image") &&
                mediaTypes.contains("video") &&
                (carrierTypes.contains("videodisc") || carrierTypes.contains("computer disc")))
            result.add("Physical Video (DVD or Blu-ray)");

        if (contentTypes.contains("two-dimensional moving image") &&
                mediaTypes.contains("computer") &&
                carrierTypes.contains("online resource"))
            result.add("Streaming Video");

        if (contentTypes.contains("performed music") &&
                mediaTypes.contains("audio") &&
                carrierTypes.contains("audio disc"))
            result.add("Physical Music (CD)");

        if (contentTypes.contains("performed music") &&
                mediaTypes.contains("computer") &&
                carrierTypes.contains("online resource"))
            result.add("Streaming Music");

        if (contentTypes.contains("spoken word") &&
                mediaTypes.contains("audio") &&
                carrierTypes.contains("audio disc"))
            result.add("Physical Spoken Word (audiobook)");

        if (contentTypes.contains("spoken word") &&
                mediaTypes.contains("computer") &&
                carrierTypes.contains("online resource"))
            result.add("Streaming Spoken Word");
        
        return result;
    }

    private List<String> getValuesMatching(Record record, String fieldCode, String subfieldCodes) {
        List<VariableField> fields = record.getVariableFields(fieldCode);
        ArrayList<Subfield> subfields = new ArrayList<Subfield>();
        for (VariableField vf : fields) {
            if (!(vf instanceof DataField))
                return(subfields);
            DataField df = (DataField)vf;
            subfields.addAll(df.getSubfields(subfieldCodes));
        }
        List<String> values = subfields.stream().map(f -> f.getData()).collect(Collectors.toList());
        return(values);
    }

}
