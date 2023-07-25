package org.solrmarc.mixin;

import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;
import org.apache.log4j.Logger;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.index.SolrIndexerMixin;

/**
 * Transliterated linked values indexing routines.
 */
public class TransliteratedToolsMixin extends SolrIndexerMixin {

    //static Logger logger = Logger.getLogger(TransliteratedToolsMixin.class.getName());

    /**
     * Get all available publishers, including translitereted, from the record.
     *
     * @param  record MARC record
     * @return set of publishers
     */
    public Set<String> getTransliteratedPublishers(final Record record) {
        Set<String> publishers = new LinkedHashSet<String>();

        // First check old-style 260b name:
        //List<VariableField> list260 = record.getVariableFields("260");
        // MSUL: Pull both subject and linked transliteration
        List<VariableField> list260 = SolrIndexer.instance().getFieldSetMatchingTagList(record, "260:LNK260");
        for (VariableField vf : list260)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }
            if (currentString.length() > 0) {
                publishers.add(currentString);
            }
        }

        // Now track down relevant RDA-style 264b names; we only care about
        // copyright and publication names (and ignore copyright names if
        // publication names are present).
        Set<String> pubNames = new LinkedHashSet<String>();
        Set<String> copyNames = new LinkedHashSet<String>();

        //List<VariableField> list264 = record.getVariableFields("264");
        // MSUL: Pull both subject and linked transliteration
        List<VariableField> list264 = SolrIndexer.instance().getFieldSetMatchingTagList(record, "264:LNK264");
        for (VariableField vf : list264)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }
            if (currentString.length() > 0) {
                char ind2 = df.getIndicator2();
                switch (ind2)
                {
                    case '1':
                        pubNames.add(currentString);
                        break;
                    case '4':
                        copyNames.add(currentString);
                        break;
                }
            }
        }
        if (pubNames.size() > 0) {
            publishers.addAll(pubNames);
        } else if (copyNames.size() > 0) {
            publishers.addAll(copyNames);
        }

        return publishers;
    }
}
