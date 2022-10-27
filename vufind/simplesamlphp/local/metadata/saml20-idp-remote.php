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
$metadata['https://fujifish.github.io/samling/samling.html'] = [
    'SingleSignOnService'  => 'https://fujifish.github.io/samling/samling.html',
    'SingleLogoutService'  => 'https://fujifish.github.io/samling/samling.html',
    'certificate'          => 'samling.pem',
];
$metadata['https://samltest.id/saml/idp'] = [
    'SingleSignOnService'  => 'https://samltest.id/idp/profile/SAML2/Redirect/SSO',
    'SingleLogoutService'  => 'https://samltest.id/idp/profile/SAML2/Redirect/SLO',
    'certificate'          => 'samltest.pem',
    'sign.logout'          => true,
];
$metadata['http://www.okta.com/exk1ed76cimmz56WW0h8'] = [
    'SingleSignOnService'  => 'https://auth.test.itservices.msu.edu/app/msutst_devlibpubliccatalog_1/exk1ed76cimmz56WW0h8/sso/saml',
    'SingleLogoutService'  => 'https://auth.test.itservices.msu.edu/login/signout?fromURI=https%3A%2F%2F'.getenv('SITE_HOSTNAME'),
    'certificate'          => 'msu-dev.pem',
    'sign.logout'          => true,
];
