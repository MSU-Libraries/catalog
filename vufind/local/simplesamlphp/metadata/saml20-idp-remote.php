<?php

/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 * 
 * If you have the metadata of the remote IdP as an XML file, you can use the built-in XML
 * to SimpleSAMLphp metadata converter, which by default is available as
 * /admin/metadata-converter.php in your SimpleSAMLphp installation.
 */
$metadata[getenv('SAML_IDP_ENTITY_ID')] = [
    'SingleSignOnService'  => 'https://fujifish.github.io/samling/samling.html',
    'SingleLogoutService'  => 'https://fujifish.github.io/samling/samling.html',
    /*'certificate'          => getenv('SIMPLESAMLPHP_CUSTOM_DIR').'/cert/example.pem',*/
];
