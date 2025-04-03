# Using Core VuFind Only

When developing a feature for core VuFind you do not want MSUL catalog code. To more easily enable this,
we added the ability to create a new environment not including any of our local customizations.

To do so, when running the pipelines on your environment (on `devel-*` environments only), pass the variable 
`VUFIND_CORE_INSTALLATION` with the value of `1` which will adapt the setup of the environment
to exclude MSUL Catalog module.

In GitLab, on the `Pipelines` page, click the button `Run pipeline`, choose your `devel-*` branch,
Use `VUFIND_CORE_INSTALLATION` as Input variable key and type `1` as Input variable value.
