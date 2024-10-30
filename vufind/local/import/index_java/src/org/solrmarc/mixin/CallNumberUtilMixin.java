package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.stream.Collectors;

import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;

import org.solrmarc.index.SolrIndexerMixin;

public class CallNumberUtilMixin extends SolrIndexerMixin {
    private static final Pattern lccPattern = Pattern.compile("^[A-HJ-NP-VZ][A-Z]{0,2}\\s*\\d[^:]*$");
    private static final Pattern firstCutterPattern = Pattern.compile("^([A-HJ-NP-VZ][A-Z]{0,2}\\s?\\.[A-Z0-9]+)");

    /**
     * Return a list of call number substrings, using all values from 952e and 50a.
     * - one with 2 letters
     * - one up until the dot
     * - one including the first cutter
     */
    public List<String> getMSUCallNumberLabels(final Record record) {
        List<String> callNumbers = getValuesMatching(record, "952", "e");
        callNumbers.addAll(getValuesMatching(record, "050", "a"));
        HashSet<String> result = new LinkedHashSet<String>();
        for (String cn : callNumbers) {
            String cnUp = cn.toUpperCase();
            if (cnUp.length() < 2) {
                result.add(cnUp);
                continue;
            }
            boolean isLcc = lccPattern.matcher(cnUp).matches() && (cnUp.length() <= 10 || cnUp.contains("."));
            if (isLcc && cnUp.length() >= 2) {
                result.add(cnUp.substring(0, 2));
            }
            if (cnUp.length() == 2) {
                continue;
            }
            int dotPos = cnUp.indexOf(".");
            if (dotPos < 0) {
                result.add(cnUp);
                continue;
            }
            if (dotPos == 0) {
                continue;
            }
            result.add(cnUp.substring(0, dotPos));
            if (!isLcc) {
                continue;
            }
            Matcher matcher = firstCutterPattern.matcher(cnUp);
            if (matcher.find()) {
                result.add(matcher.group(1));
            }
        }
        return new ArrayList<String>(result);
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
