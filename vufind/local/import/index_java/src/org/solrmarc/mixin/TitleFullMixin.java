package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;

public class TitleFullMixin extends SolrIndexerMixin {

    public List<String> getTitleFull(final Record record) {
        String titleFull = ""; 
        List<String> title = getValuesMatching(record, "245", "[a-z]");
        List<VariableField> titleAlts = SolrIndexer.instance().getFieldSetMatchingTagList(record, "100:130:240:246:505:700:710:711:730:740:LNK245");
        if (title.count > 0)
            titleFull = title[0].trim();

        // Add the first title alt that is not matching the original title
        for (VariableField titleAlt : titleAlts)
        {
            DataField titleAltField = (DataField) titleAlt;

            for (Subfield current : titleAltField.getSubfields("[a-z]")) {
                if (current.trim() != titleFull)
                    titleFull = titleFull + current.trim();
                    return titleFull;
            }
        }

        // If we haven't already returned yet (i.e. no titleAlts found to add)
        return titleFull;
    }

    private List<String> getValuesMatching(Record record, String fieldCode, String subfieldCodes) {
        List<VariableField> fields = record.getVariableFields(fieldCode);
        List<String> values = new ArrayList<String>();
        for (VariableField vf : fields) {
            if (!(vf instanceof DataField))
                continue;
            DataField df = (DataField)vf;
            values.addAll(df.getSubfields(subfieldCodes)
                .stream().map(f -> f.getData()).collect(Collectors.toList()));
        }
        return(values);
    }

}
