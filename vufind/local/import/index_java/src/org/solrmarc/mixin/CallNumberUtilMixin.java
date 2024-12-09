package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

import org.marc4j.marc.Record;

import org.solrmarc.callnum.DeweyCallNumber;
import org.solrmarc.callnum.LCCallNumber;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.index.SolrIndexerMixin;


public class CallNumberUtilMixin extends SolrIndexerMixin {

    /**
     * Extract the call number labels from a record.
     * fieldSpec might include 952e for FOLIO, in which case we do not know the type of call number
     * (also sometimes call numbers are in the wrong MARC field for their type).
     * If LCC: classLetters, classLetters + classDigits, classLetters + classDigits + classDecimal
     * If Dewey: classDigits, classDigits + classDecimal
     * Otherwise: everything up until the dot (or everything if there is no dot)
     * @param record MARC record
     * @param fieldSpec taglist for call number fields
     * @return Call number labels
     */
    public List<String> getMSUCallNumberLabels(final Record record, String fieldSpec) {
        Set<String> values = SolrIndexer.instance().getFieldList(record, fieldSpec);
        HashSet<String> result = new LinkedHashSet<String>();
        for (String cn: values) {
            String cnUp = cn.toUpperCase().trim();
            LCCallNumber lcc = new LCCallNumber(cnUp);
            DeweyCallNumber dewey = new DeweyCallNumber(cnUp);
            boolean other = cnUp.contains(":") || (cnUp.length() > 10 && !cnUp.contains("."));
            boolean isLcc = lcc.isValid() && !other;
            boolean isDewey = dewey.isValid() && !other;
            if (isLcc) {
                result.add(lcc.getClassLetters());
                result.add(lcc.getClassLetters() + lcc.getClassDigits());
                if (lcc.getClassDecimal() != null) {
                    result.add(lcc.getClassLetters() + lcc.getClassDigits() + lcc.getClassDecimal());
                }
            } else if (isDewey) {
                result.add(dewey.getClassDigits());
                String classDecimal = dewey.getClassDecimal();
                if (classDecimal != null) {
                    for (int i=1; i<5; i++) {
                        if (classDecimal.length() > i) {
                            result.add(dewey.getClassDigits() + classDecimal.substring(0, i+1));
                        }
                    }
                }
            } else {
                // NOTE: we could add other classifications like SuDoc here (and add related MARC fields to fieldSpec)
                int dotPos = cnUp.indexOf(".");
                if (dotPos < 0) {
                    result.add(cnUp);
                    continue;
                }
                if (dotPos == 0) {
                    continue;
                }
                result.add(cnUp.substring(0, dotPos).trim());
            }
        }
        return new ArrayList<String>(result);
    }

}
