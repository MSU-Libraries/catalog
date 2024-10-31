<?xml version="1.0" encoding="UTF-8"?>
<!--
This XSLT will merge in the content of a secondary Solr schema.xml, called the modFile.

Example call to merge original schema.xml and schema-mod.xml:
    xsltproc - -stringparam modFile "schema-mod.xml" merge-schema.xslt schema.xml
              ^ remove space (XML comments not allowed to contain double-hyphen)

Adding elements:
    Include them in the modFile as they would appear in the original schema.

Removing elements:
    You can also tag elements to be removed from the original schema by including
    elements with an addded `deleteElement="true"` in the modFile.
    Examples:
     - <fieldType name="legacyDate" deleteElement="true"/>
     - <field name="title_fullStr" deleteElement="true"/>
     - <copyField source="title_full" dest="title_fullStr" deleteElement="true"/>

Replacing elements:
    Simply add the elements in the modFile, matching elements in the original schema will not be copied.
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="xml" indent="yes"/>

    <!-- Define a parameter for the schema modification file and load schema into var -->
    <xsl:param name="modFile" />
    <xsl:variable name="modSchema" select="document($modFile)/schema"/>

    <!-- Copy everything from original schema by default -->
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="/schema">
        <schema>
            <!-- Preserve attributes of the root element -->
            <xsl:apply-templates select="@*"/>

            <!-- Apply templates only for elements in the original schema -->
            <!-- Copy elements from the modFile unless they have @deleteElement='true' -->
            <types>
                <xsl:apply-templates select="types/*"/>
                <xsl:copy-of select="$modSchema/types/*[not(@deleteElement='true')]"/>
            </types>
            <fields>
                <xsl:apply-templates select="fields/*"/>
                <xsl:copy-of select="$modSchema/fields/*[not(@deleteElement='true')]"/>
            </fields>
            <xsl:apply-templates select="*[not(self::types or self::fields)]"/>
            <xsl:copy-of select="$modSchema/*[not(self::types or self::fields or @deleteElement='true')]"/>
        </schema>
    </xsl:template>

    <!-- From the original schema, exclude elements which match an element in the schema modification file -->
    <xsl:template match="fieldType">
        <xsl:if test="not($modSchema/types/*[@name=current()/@name])">
            <xsl:copy-of select="."/>
        </xsl:if>
    </xsl:template>
    <xsl:template match="field">
        <xsl:if test="not($modSchema/fields/*[@name=current()/@name])">
            <xsl:copy-of select="."/>
        </xsl:if>
    </xsl:template>
    <xsl:template match="copyField">
        <xsl:if test="not($modSchema/copyField[@source=current()/@source and @dest=current()/@dest])">
            <xsl:copy-of select="."/>
        </xsl:if>
    </xsl:template>
</xsl:stylesheet>
