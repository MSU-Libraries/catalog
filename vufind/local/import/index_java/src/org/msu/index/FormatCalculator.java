package org.msu.index;
/**
 * Format determination logic.
 *
 * Copyright (C) Villanova University 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.VariableField;
import java.util.ArrayList;
import java.util.List;

/**
 * Format determination logic.
 */
public class FormatCalculator extends org.vufind.index.FormatCalculator
{
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
            if (desc.equals("computer program")) {
                return List.of(); // rely on getFormatFromRecordType(), 008/26 is more precise
            }
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

    /**
     * Return the best format string based on record type in leader; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param Record record
     * @param char recordType
     * @param ControlField marc008
     * @param List formatCodes007
     * @return String
     */
    @Override protected String getFormatFromRecordType(Record record, char recordType, ControlField marc008, List formatCodes007)
    {
        // PC-1424 MSUL - Add logic to change 'Kit' and 'Collection' assignment
        String leader = record.getLeader().toString();
        char bibLevel = Character.toLowerCase(leader.charAt(7));
        switch (recordType) {
            case 'o':
                return "Kit";
            case 'p':
                if (bibLevel == 'c') {
                    return "Collection";
                }
                break;
        }

        return super.getFormatFromRecordType(record, recordType, marc008, formatCodes007);
    }

    /**
     * MSUL - PC-1424 Override "last resort" logic for format
     * Determine Record Format(s)
     *
     * @param  Record record
     * @return Set format(s) of record
     */
    @Override protected List<String> getFormatsAsList(Record record) {
        List<String> result = new ArrayList<String>();
        String leader = record.getLeader().toString();
        ControlField marc008 = (ControlField) record.getVariableField("008");
        String formatString;
        char formatCode = ' ';
        char recordType = Character.toLowerCase(leader.charAt(6));
        char bibLevel = Character.toLowerCase(leader.charAt(7));

        // This record could be a book... until we prove otherwise!
        boolean couldBeBook = true;

        // Some format-specific special cases:
        if (isGovernmentDocument(record)) {
            result.add("GovernmentDocument");
        }
        if (isThesis(record)) {
            result.add("Thesis");
        }
        if (isElectronic(record, recordType)) {
            result.add("Electronic");
        }
        if (isConferenceProceeding(record)) {
            result.add("ConferenceProceeding");
        }

        // check the 33x fields; these may give us clear information in newer records;
        // in current partial implementation of getFormatsFrom33xFields(), if we find
        // something here, it indicates non-book content.
        List formatsFrom33x = getFormatsFrom33xFields(record);
        if (formatsFrom33x.size() > 0) {
            couldBeBook = false;
            result.addAll(formatsFrom33x);
        }

        // check the 007 - this is a repeating field
        List<Character> formatCodes007 = new ArrayList<Character>();
        for (VariableField variableField : record.getVariableFields("007")) {
            ControlField formatField = (ControlField) variableField;
            formatString = formatField.getData().toLowerCase();
            formatCode = formatString.length() > 0 ? formatString.charAt(0) : ' ';
            formatCodes007.add(formatCode);
            if (definitelyNotBookBasedOn007(formatCode)) {
                couldBeBook = false;
            }
            if (formatCode == 'v') {
                // All video content should get flagged as video; we will also
                // add a more detailed value in getFormatFrom007 to distinguish
                // different types of video.
                result.add("Video");
            }
            String formatFrom007 = getFormatFrom007(formatCode, formatString);
            if (formatFrom007.length() > 0) {
                result.add(formatFrom007);
            }
        }

        // check the Leader at position 6
        if (definitelyNotBookBasedOnRecordType(recordType, marc008)) {
            couldBeBook = false;
        }
        // If we already have 33x results, skip the record type:
        String formatFromRecordType = formatsFrom33x.size() == 0
            ? getFormatFromRecordType(record, recordType, marc008, formatCodes007)
            : "";
        if (formatFromRecordType.length() > 0) {
            result.add(formatFromRecordType);
        }

        // check the Leader at position 7
        String formatFromBibLevel = getFormatFromBibLevel(
            record, recordType, bibLevel, marc008, couldBeBook, formatCodes007
        );
        if (formatFromBibLevel.length() > 0) {
            result.add(formatFromBibLevel);
        }

        // Nothing worked -- time to set up a value of last resort!
        if (result.isEmpty()) {
            // START OF MSUL CUSTOMIZATION (ADDED IN PC-1424)
            String formatLastResort = getLastResortFormatForRecord(record, recordType, bibLevel);
            if (formatLastResort.length() > 0) {
                result.add(formatLastResort);
            }
            // END OF MSUL CUSTOMIZATIONS
        }

        return result;
    }

    /**
     * MSUL -- PC-1424 Update last resort logic; eventually turn this into an override
     * if accepted in a PR.
     * Return the best format string to use as a last resort.
     *
     * @param Record record
     * @param char recordType
     * @param char bibLevel
     * @return String
     */
    protected String getLastResortFormatForRecord(Record record, char recordType, char bibLevel) {
        // MSUL PC-1424 Update last resort to use collection if bibLevel is 'c'
        if (bibLevel == 'c' || bibLevel == 'd') {
            return "Collection";
        } else if (recordType == 'a') {
            // If LDR/06 indicates "Language material," map to "Text";
            // this helps cut down on the number of unknowns.
            return"Text";
        }
        return "Unknown";
    }
}
