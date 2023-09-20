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
// MSU TEST (for catalog-preview)
$metadata['http://www.okta.com/exkvuaj28f9YbgMpE357'] = [
    'name'                 => 'MSU TEST IdP',
    'SingleSignOnService'  => 'https://auth.msu.edu/app/msu_testlibpubliccatalogvufind_1/exkvuaj28f9YbgMpE357/sso/saml',
    'SingleLogoutService'  => 'https://auth.msu.edu/app/exkvuaj28f9YbgMpE357/sso/saml/metadata',
    'certificate'          => 'msu-test.pem',
    'sign.logout'          => true,
];
// MSU QA (for catalog-beta)
$metadata['http://www.okta.com/exkvvw8b28ES52GeY357'] = [
    'name'                 => 'MSU QA IdP',
    'SingleSignOnService'  => 'https://auth.msu.edu/app/msu_qalibpubliccatalogvufind_1/exkvvw8b28ES52GeY357/sso/saml',
    'SingleLogoutService'  => 'https://auth.msu.edu/app/exkvvw8b28ES52GeY357/sso/saml/metadata',
    'certificate'          => 'msu-qa.pem',
    'sign.logout'          => true,
];
// MSU PROD
$metadata['http://www.okta.com/exkvvwpups2ANo53d357'] = [
    'name'                 => 'MSU PROD IdP',
    'SingleSignOnService'  => 'https://auth.msu.edu/app/msu_libpubliccatalogvufind_1/exkvvwpups2ANo53d357/sso/saml',
    'SingleLogoutService'  => 'https://auth.msu.edu/app/exkvvwpups2ANo53d357/sso/saml/metadata',
    'certificate'          => 'msu-prod.pem',
    'sign.logout'          => true,
];
