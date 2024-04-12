package org.msu.index;

import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.DataField;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexer;
import org.apache.log4j.Logger;
import org.vufind.index.FieldSpecTools;
import java.util.Arrays;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;
import java.util.regex.Pattern;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;

public class CreatorTools extends org.vufind.index.CreatorTools {
    
    // Initialize logging category
    static Logger logger = Logger.getLogger(CreatorTools.class.getName());

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     * @param firstOnly            Return first result only?
     * @return List result
     */
    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators, Boolean firstOnly
    ) {
        List<String> result = new LinkedList<String>();
        String[] noRelatorAllowed = acceptWithoutRelator.split(":");
        String[] unknownRelatorAllowed = acceptUnknownRelators.split(":");
        HashMap<String, Set<String>> parsedTagList = FieldSpecTools.getParsedTagList(tagList);
        for (VariableField variableField : SolrIndexer.instance().getFieldSetMatchingTagList(record, tagList)) {
            DataField authorField = (DataField) variableField;
            // add all author types to the result set; if we have multiple relators, repeat the authors
            if (authorField.getSubfield('6').getData().contains("880-")) {
                String linkedKey = authorField.getSubfield('6').getData().split("-")[1];
                for (VariableField RawLinkedField : SolrIndexer.instance().getFieldSetMatchingTagList(record, "880")) {
                    DataField linkedField = (DataField) RawLinkedField;
                    if (linkedField.getSubfield('6').getData().contains("-")) {
                        String linkedVal = linkedField.getSubfield('6').getData().split("-")[1].replace("/r","");
                        if (linkedVal.equals(linkedKey)){
                            // Get the subfields of the original field
                            for (String subfields : parsedTagList.get(authorField.getTag())) {
                                // Get the data from the linked field for those subfields
                                String current = SolrIndexer.instance().getDataFromVariableField(authorField, "["+subfields+"]", " ", false);
                                String currentLinked = SolrIndexer.instance().getDataFromVariableField(linkedField, "["+subfields+"]", " ", false);
                                if (null != current && null != currentLinked) {
                                    result.add(fixTrailingPunctuation(current));
                                    result.add(fixTrailingPunctuation(currentLinked));
                                    if (firstOnly) {
                                        return result;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            else {
                for (String iterator: getValidRelators(authorField, noRelatorAllowed, relatorConfig, unknownRelatorAllowed, indexRawRelators)) {
                    for (String subfields : parsedTagList.get(authorField.getTag())) {
                        String current = SolrIndexer.instance().getDataFromVariableField(authorField, "["+subfields+"]", " ", false);
                        if (null != current) {
                            result.add(fixTrailingPunctuation(current));
                            if (firstOnly) {
                                return result;
                            }
                        }
                    }
                }
            }
        }
        return result;
    }
}
