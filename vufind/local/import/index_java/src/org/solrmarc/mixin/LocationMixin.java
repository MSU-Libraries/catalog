package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;

public class LocationMixin extends SolrIndexerMixin {

    public List<String> getLocations(final Record record, String dummy) {
        List<String> result = new ArrayList<String>();
        List<VariableField> locationFields = record.getVariableFields("952");
        List<Subfield> cSubfields = getSubfieldsMatching(locationFields, "c");
        List<Subfield> dSubfields = getSubfieldsMatching(locationFields, "d");
        List<String> cValues = cSubfields.stream().map(f -> f.getData().replace("/", " ")).collect(Collectors.toList());
        List<String> dValues = dSubfields.stream().map(f -> f.getData().replace("/", " ")).collect(Collectors.toList());
        cValues.forEach(c -> {
            result.add("0/" + c + "/");
        });
        dValues.forEach(d -> {
            if (d.contains("-")) {
                int index = d.indexOf("-");
                String first = d.substring(0, index).trim();
                String second = d.substring(index + 1).trim();
                if (cValues.contains(first))
                    result.add("1/" + first + "/" + second + "/");
            }
        });
        for (int i = 0; i < cValues.size() && i < dValues.size(); i++) {
            String c = cValues.get(i);
            String d = dValues.get(i);
            if (c != null && d != null && !c.equals(d) && !d.contains("-")) {
                result.add("1/" + c + "/" + d + "/");
            }
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
