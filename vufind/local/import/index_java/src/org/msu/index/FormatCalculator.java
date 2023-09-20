package org.msu.index;

import org.marc4j.marc.ControlField;

public class FormatCalculator extends org.vufind.index.FormatCalculator {

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

}
