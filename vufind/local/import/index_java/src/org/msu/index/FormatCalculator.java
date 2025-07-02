package org.msu.index;

import org.marc4j.marc.ControlField;
import org.marc4j.marc.Record;
import org.marc4j.marc.DataField;
import org.marc4j.marc.VariableField;
import java.util.ArrayList;
import java.util.List;

public class FormatCalculator extends org.vufind.index.FormatCalculator {
    /**
     * Return the best format string based on codes extracted from 007; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param char formatCode
     * @param String formatString
     * @return String
     */
    @Override protected String getFormatFrom007(char formatCode, String formatString) {
        char formatCode2 = formatString.length() > 1 ? formatString.charAt(1) : ' ';
        if (formatCode == 'v' && formatString.length() > 4 && formatString.charAt(1) == 'd' && formatString.charAt(4) == 'g') {
            return "LaserDisc";
        }
        // PC-1409
        if (formatCode == 's' && formatCode2 == 'd' && formatString.length() > 10 && formatString.charAt(10) == 'p') {
            return "VinylRecord";
        }
        // PC-1409
        if (formatCode == 's' && formatCode2 == 'd' && formatString.length() > 10 && formatString.charAt(10) == 's') {
            return "ShellacRecord";
        }
        return super.getFormatFrom007(formatCode, formatString);
    }

    /**
     * Determine whether a record cannot be a book due to findings in leader
     * and fixed fields (008).
     *
     * @param char recordType
     * @param ControlField marc008
     * @return boolean
     */
    protected boolean definitelyNotBookBasedOnRecordType(char recordType, ControlField marc008) {
        if (recordType == 'c') {
            return true;
        }
        return super.definitelyNotBookBasedOnRecordType(recordType, marc008);
    }

    /**
     * Determines record formats using 33x fields.
     *
     * This is not currently comprehensive; it is designed to supplement but not
     * replace existing support for 007 analysis and can be expanded in future.
     *
     * @param  Record record
     * @return Set format(s) of record
     */
    @Override protected List<String> getFormatsFrom33xFields(Record record) {
        boolean isOnline = isOnlineAccordingTo338(record);
        List<String> formats = new ArrayList<String>();
        for (VariableField variableField : record.getVariableFields("336")) {
            DataField typeField = (DataField) variableField;
            String desc = getSubfieldOrDefault(typeField, 'a', "");
            String code = getSubfieldOrDefault(typeField, 'b', "");
            String source = getSubfieldOrDefault(typeField, '2', "");
            if ((desc.equals("two-dimensional moving image") || code.equals("tdi")) && source.equals("rdacontent")) {
                formats.add("Video");
                if (isOnline) {
                    formats.add("VideoOnline");
                }
            }
            boolean computerOrCartographicDS = desc.equals("computer dataset") || desc.equals("cartographic dataset");
            boolean crdOrCod = code.equals("crd") || code.equals("cod");
            if (source.equals("rdacontent") && (computerOrCartographicDS || crdOrCod)) {
                formats.add("DataSet");
            }
        }
        return formats;
    }
}
