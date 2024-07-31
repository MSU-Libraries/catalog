# Writing & Running Tests

# Writing Tests
VuFind has [extensive documentation](https://vufind.org/wiki/development:testing:unit_tests)
on writing unit tests for your custom code. Particularly note the `Related Video` links at the
bottom, which are very helpful in getting started.

# Running Tests
We have included in this repository [a script](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/run-tests)
to run the commands for both unit tests and code quality tests.

```bash
# From within a running VuFind container
run-tests
```

# Coverage Tests
To get the current coverage status:

```bash
# From within a running VuFind container
get-coverage-summary
```

You can also locally view coverage progress in an html page by running an image on your computer.

```bash
# Build the image locally
DOCKER_BUILDKIT=1 docker build --build-arg BUILDKIT_INLINE_CACHE=1 --build-arg VUFIND_VERSION="9.1.2" --build-arg SIMPLESAMLPHP_VERSION="2.1.1" --tag validate vufind/
# Run the image locally
docker run --rm -it -v $(pwd)/vufind/module/Catalog:/usr/local/vufind/module/Catalog -v /tmp/coverage:/usr/local/vufind/coverage validate bash
# Update phpunit settings
mv module/Catalog/tests/vufind_phpunit.xml module/VuFind/tests/phpunit.xml
# Generate the coverage report
XDEBUG_MODE=coverage vendor/bin/phing phpunitfaster -D "phpunit_extra_params=/usr/local/vufind/module/Catalog/tests/unit-tests/ --coverage-html coverage"
```

Now go to your browser at [file:///tmp/coverage/index.html](file:///tmp/coverage/index.html)
to view the interactive report to easily identify gaps.

That same locally built docker image can be used to run the code quality tests as well as the unit tests.

```bash
docker run --rm -it -v $(pwd)/vufind/module/Catalog:/usr/local/vufind/module/Catalog -v /tmp/coverage:/usr/local/vufind/coverage validate bash
run-tests
```
