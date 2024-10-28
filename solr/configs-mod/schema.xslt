<?xml version="1.0" encoding="UTF-8"?>
<!-- Call schema merge in the form of:
    xsltproc - -stringparam modFile "schema-mod.xml" merge-schema.xslt schema.xml
              ^ remove space (XML comments not allowed to contain double-hyphen)
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="xml" indent="yes"/>

    <!-- Define a parameter for the schema modification file -->
    <xsl:param name="modFile" />

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

            <!-- Merge `types` from both files -->
            <types>
                <xsl:apply-templates select="types/*"/>
                <xsl:apply-templates select="document($modFile)/schema/types/*"/>
            </types>
            <!-- Merge `fields` from both files -->
            <fields>
                <xsl:apply-templates select="fields/*"/>
                <xsl:apply-templates select="document($modFile)/schema/fields/*"/>
            </fields>
            <!-- Include any other elements from both files -->
            <xsl:apply-templates select="*[not(self::types or self::fields)]"/>
            <xsl:apply-templates select="document($modFile)/schema/*[not(self::types or self::fields)]"/>
        </schema>
    </xsl:template>
</xsl:stylesheet>
