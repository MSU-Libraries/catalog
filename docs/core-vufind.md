# Using core-vufind only

Sometimes, when developing a feature for core-vufind mostly, you do not want MSUL catalog code.

To do so, when running the pipelines on your environment (on `devel-*` environments only), pass the variable 
`VUFIND_CORE_INSTALLATION` with the value of `1` which will adapt the setup of the environment to exclude MSUL Catalog 
module
