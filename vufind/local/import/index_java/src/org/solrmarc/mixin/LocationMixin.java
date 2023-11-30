package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;

public class LocationMixin extends SolrIndexerMixin {

    public List<String> getLocations(final Record record) {
        List<String> result = new ArrayList<String>();
        List<VariableField> cniFields = record.getVariableFields("003");
        if (cniFields.stream().anyMatch(vf -> "EBZ".equals(((ControlField)vf).getData()))) {
            result.add("0/MSU Online Resource/");
            return result;
        }
        List<VariableField> locationFields = record.getVariableFields("952");
        locationFields.forEach(location -> {
            List<Subfield> tSubfields = getSubfieldsMatching(location, "t");
            if (tSubfields.stream().anyMatch(t -> "1".equals(t.getData().trim()))) {
                // Ignore location when 952t is 1
                return;
            }
            List<Subfield> bSubfields = getSubfieldsMatching(location, "b");
            if (bSubfields.stream().anyMatch(b -> "Library of Michigan".equals(b.getData().trim()))) {
                // Ignore location when 952b is "Library of Michigan"
                return;
            }
            List<Subfield> cSubfields = getSubfieldsMatching(location, "c");
            List<Subfield> dSubfields = getSubfieldsMatching(location, "d");
            List<String> cValues = cSubfields.stream().map(f -> f.getData().replace("/", " ").trim()).collect(Collectors.toList());
            List<String> dValues = dSubfields.stream().map(f -> f.getData().replace("/", " ").trim()).collect(Collectors.toList());
            cValues.forEach(c -> {
                result.add("0/" + c + "/");
            });
            dValues.forEach(d -> {
                if (d.contains("-")) {
                    int index = d.indexOf("-");
                    String first = d.substring(0, index).trim();
                    String second = d.substring(index + 1).trim();
                    if (cValues.contains(first)) {
                        result.add("1/" + first + "/" + second + "/");
                    }
                }
            });
            for (int i = 0; i < cValues.size() && i < dValues.size(); i++) {
                String c = cValues.get(i);
                String d = dValues.get(i);
                if (c != null && d != null && !c.equals(d) && !d.contains("-")) {
                    result.add("1/" + c + "/" + d + "/");
                }
            }
        });
        return result.stream().distinct().collect(Collectors.toList());
    }

    private List<Subfield> getSubfieldsMatching(VariableField vf, String subfieldCodes) {
        ArrayList<Subfield> subfields = new ArrayList<Subfield>();
        if (!(vf instanceof DataField)) {
            return(subfields);
        }
        DataField df = (DataField)vf;
        subfields.addAll(df.getSubfields(subfieldCodes));
        return(subfields);
    }

}
