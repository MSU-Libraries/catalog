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

// The IdP is selected in authsources.php.

// samling and samltest are for testing and debugging SAML.
$metadata['https://fujifish.github.io/samling/samling.html'] = [
    'name'                 => 'samling',
    'SingleSignOnService'  => 'https://fujifish.github.io/samling/samling.html',
    'SingleLogoutService'  => 'https://fujifish.github.io/samling/samling.html',
    'certificate'          => 'samling.pem',
];
$metadata['https://samltest.id/saml/idp'] = [
    'name'                 => 'samltest',
    'SingleSignOnService'  => 'https://samltest.id/idp/profile/SAML2/Redirect/SSO',
    'SingleLogoutService'  => 'https://samltest.id/idp/profile/SAML2/Redirect/SLO',
    'certificate'          => 'samltest.pem',
    'sign.logout'          => true,
];

// MSU DEV (for devel-authentication)
$metadata['http://www.okta.com/exkvspdzpbUAmE6mM357'] = [
    'name'                 => 'MSU DEV IdP',
    'SingleSignOnService'  => 'https://auth.msu.edu/app/msu_devlibpubliccatalogvufind_1/exkvspdzpbUAmE6mM357/sso/saml',
    'SingleLogoutService'  => 'https://auth.msu.edu/app/exkvspdzpbUAmE6mM357/sso/saml/metadata',
    'certificate'          => 'msu-dev.pem',
    'sign.logout'          => true,
];
// MSU QA (can be used with catalog-beta); running on Okta prod to avoid IP restrictions
$metadata['http://www.okta.com/exko9ngr82UjBdFPU357'] = [
    'name'                 => 'MSU QA IdP',
    'SingleSignOnService'  => 'https://auth.msu.edu/app/msu_qalibpubliccatalog_1/exko9ngr82UjBdFPU357/sso/saml',
    'SingleLogoutService'  => 'https://auth.msu.edu/login/signout?fromURI=https://catalog-beta.lib.msu.edu',
    'certificate'          => 'msu-qa.pem',
    'sign.logout'          => true,
];
// MSU PROD
$metadata['http://www.okta.com/exkng5q6tqDecNuDY357'] = [
    'name'                 => 'MSU PROD IdP',
    'SingleSignOnService'  => 'https://auth.msu.edu/app/msu_libpubliccatalog_1/exkng5q6tqDecNuDY357/sso/saml',
    'SingleLogoutService'  => 'https://auth.msu.edu/login/signout?fromURI=https://catalog.lib.msu.edu',
    'certificate'          => 'msu-prod.pem',
    'sign.logout'          => true,
];
