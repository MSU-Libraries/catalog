package org.solrmarc.mixin;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexerMixin;
import org.msu.index.FormatCalculator;

public class MaterialTypeMixin extends SolrIndexerMixin {

    public List<String> getMaterialType(final Record record) {
        // PC-1144 Use format to help determine material type
        FormatCalculator fc = new FormatCalculator();
        Set<String> formats = fc.getFormats(record);
        List<String> contentTypes = getValuesMatching(record, "336", "a");
        List<String> mediaTypes = getValuesMatching(record, "337", "a");
        List<String> carrierTypes = getValuesMatching(record, "338", "a");
        List<String> electronicLocationLinkText = getValuesMatching(record, "856", "y");

        // Define sets to use for format checks below
        Set<String> physicalVideoFormats = Set.of(
            "VideoReel", "VideoCartidge", "VideoCassette", "VideoDisc",
            "FilmStrip", "BRDisc", "LaserDisc", "MotionPicture"
        );
        Set<String> physicalMapFormats = Set.of(
            "Atlas", "Map", "Globe"
        );
        Set<String> electronicMapFormats = Set.of(
            "Atlas", "Map"
        );
        Set<String> physicalComputerMediaFormats = Set.of(
            "DataSet", "Electronic Resource", "Software", "Video Game",
            "CDROM", "FloppyDisk", "TapeCartridge", "TapeCassette", "TapeReel"
        );
        Set<String> journalFormats = Set.of(
            "Serial", "Article", "SerialComponentPart", "Journal", "Newspaper"
        );
        Set<String> physicalMaterialFormats = Set.of(
            "PhysicalIntegratingResource", "ProjectedMedium", "Slide", "Transparency",
            "SensorImage", "PostCard", "Poster", "PhysicalObject",
            "FlashCard", "Chart", "Drawing", "Print", "Painting", "Photo", "Photonegative", "Collage"
        );
        Set<String> electronicMaterialFormats = Set.of(
            "DataSet", "PhysicalIntegratingResource", "SensorImage",
            "PostCard", "Poster", "Font", "FlashCard", "Chart", "Drawing", "Print",
            "Painting", "Photo", "Photonegative", "Collage"
        );

        List<String> result = new ArrayList<String>();

        if ((formats.contains("Atlas") && !formats.contains("Electronic")) ||
                (formats.contains("Book") && !formats.contains("Electronic"))
            )
            result.add("1/At the Libraries/Books/");

        if (formats.contains("eBook") ||
                (formats.contains("Atlas") && formats.contains("Electronic")) ||
                (formats.contains("Book") && formats.contains("Electronic")) ||
                (formats.contains("BookComponentPart") && formats.contains("Electronic"))
            )
            result.add("1/Available Online/eBooks/");

        // disjoint returns true if there are no elements in common
        if (!Collections.disjoint(formats, physicalVideoFormats) ||
                (contentTypes.contains("two-dimensional moving image") &&
                    mediaTypes.contains("video") &&
                    (carrierTypes.contains("videodisc") || carrierTypes.contains("computer disc"))
                )
            )
            result.add("1/At the Libraries/Video (DVD, Blu-ray, etc.)/");

        // PC-413 Identify streaming video from electronic link text
        if (formats.contains("VideoOnline") ||
                (formats.contains("Electronic") && formats.contains("Slide")) ||
                (electronicLocationLinkText.stream().anyMatch(s -> s.toLowerCase().contains("streaming video")) ||
                    (contentTypes.contains("two-dimensional moving image") &&
                        mediaTypes.contains("computer") &&
                        carrierTypes.contains("online resource")
                    )
                )
            )
            result.add("1/Available Online/Video/");

        if ((!formats.contains("Electronic") && (formats.contains("MusicRecording") || formats.contains("SoundRecording"))) ||
                (contentTypes.contains("performed music") &&
                    mediaTypes.contains("audio") &&
                    carrierTypes.contains("audio disc")
                ) ||
                (contentTypes.contains("spoken word") &&
                    mediaTypes.contains("audio") &&
                    carrierTypes.contains("audio disc")
                ) ||
                formats.contains("CD")
            )
            result.add("1/At the Libraries/Music & Audio (CD, etc.)/");


        if ((formats.contains("Electronic") && (formats.contains("MusicRecording") || formats.contains("SoundRecording"))) ||
                (contentTypes.contains("performed music") &&
                    mediaTypes.contains("computer") &&
                    carrierTypes.contains("online resource")
                ) ||
                (contentTypes.contains("spoken word") &&
                    mediaTypes.contains("computer") &&
                    carrierTypes.contains("online resource")
                )
            )
            result.add("1/Available Online/Music & Audio/");

        if (!Collections.disjoint(formats, physicalMapFormats) && !formats.contains("Electronic"))
            result.add("1/At the Libraries/Maps/");

        if (!Collections.disjoint(formats, electronicMapFormats) && formats.contains("Electronic"))
            result.add("1/Available Online/Maps/");

        if (formats.contains("Microfilm"))
            result.add("1/At the Libraries/Microfilm/");

        if (!Collections.disjoint(formats, physicalComputerMediaFormats) ||
                (formats.contains("DataSet") && !formats.contains("Electronic"))
            )
            result.add("1/At the Libraries/Computer Media (CDROM, etc.)/");

        if (!Collections.disjoint(formats, journalFormats) &&
                formats.contains("Electronic")
            )
            result.add("1/Available Online/Journals and Newspapers/");

        if (!Collections.disjoint(formats, journalFormats) &&
                !formats.contains("Electronic")
            )
            result.add("1/At the Libraries/Journals and Newspapers/");

        if (formats.contains("MusicalScore") && !formats.contains("Electronic"))
            result.add("1/At the Libraries/Musical Scores/");

        if (formats.contains("MusicalScore") && formats.contains("Electronic"))
            result.add("1/Available Online/Musical Scores/");

        if (!Collections.disjoint(formats, physicalMaterialFormats) &&
                !formats.contains("Electronic")
            )
            result.add("1/At the Libraries/Other Materials/");

        if ((!Collections.disjoint(formats, electronicMaterialFormats) &&
                formats.contains("Electronic")) ||
                formats.contains("Website") ||
                formats.contains("OnlineIntegratingResource")
            )
            result.add("1/Available Online/Other Materials/");

        // Add in the appropriate top level hierarchy for the nested facet to work
        if (result.stream().anyMatch(s -> s.toLowerCase().contains("/at the libraries/")))
            result.add("0/At the Libraries/");
        if (result.stream().anyMatch(s -> s.toLowerCase().contains("/available online/")))
            result.add("0/Available Online/");

        return result;
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
