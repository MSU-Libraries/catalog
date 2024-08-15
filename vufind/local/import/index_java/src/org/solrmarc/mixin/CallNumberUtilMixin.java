package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;

import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;

import org.solrmarc.index.SolrIndexerMixin;

public class CallNumberUtilMixin extends SolrIndexerMixin {

    /**
     * We want this, but it doesn't work with Solrmarc (it returns fields in document order):
     * callnumber-label = custom, getCallNumberLabel("952e:050a,first")
     * Instead this method is called like this:
     * callnumber-label = custom, getMSUCallNumberLabel
     */
    public String getMSUCallNumberLabel(final Record record) {
        List<String> callNumbers = getValuesMatching(record, "952", "e");
        if (callNumbers.isEmpty()) {
            callNumbers = getValuesMatching(record, "050", "a");
        }
        if (callNumbers.isEmpty()) {
            return null;
        }
        String res = callNumbers.get(0);
        int dotPos = res.indexOf(".");
        if (dotPos > 0) {
            res = res.substring(0, dotPos);
        }
        return res.toUpperCase();
    }

    private List<String> getValuesMatching(Record record, String fieldCode, String subfieldCodes) {
        List<VariableField> fields = record.getVariableFields(fieldCode);
        List<String> values = new ArrayList<String>();
        for (VariableField vf : fields) {
            if (!(vf instanceof DataField)) {
                continue;
            }
            DataField df = (DataField)vf;
            values.addAll(df.getSubfields(subfieldCodes).stream()
                .map(f -> f.getData())
                .collect(Collectors.toList()));
        }
        return(values);
    }
}
