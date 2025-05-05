# Documentation

## GitHub Pages

This site is hosted on GitHub Pages. It is build and deployed via
GitHub Actions.

### Troubleshooting

If there are issues with the page displaying there are two things you can try.

1. Change the branch in the [Pages Settings](https://github.com/MSU-Libraries/catalog/settings/pages)
   to something other than `gh-pages`, save it, then wait a few minutes, then
   change it back to `gh-pages` and make sure the path is `/ (root)` and save
   it again. It will take a few minutes for the GitHub Action to run to
   redeploy the update before you can check again.

2. If that does not work, verify the output of the GitHub Action job log to make
   sure there were no errors building the documentation site. Find the most
   recent [pipeline](https://github.com/MSU-Libraries/catalog/actions/workflows/ci.yml)
   then click into it, and then into the `deploy` job. You can expand each
   section to see messages. The one most likely to cause issues would be the step
   called `Run mkdocs gh-deploy --force`. There could be errors listed like
   syntax errors in the `.md` files, or possibly even just re-running
   the job could resolve the issue (maybe in combination with repeating
   step 1 afterwards).

### Building locally for testing

It is possible to build the GitHub pages documentation site locally on your
machine to test it before you commit and deploy it.

```bash
pip install mkdocs-material mkdocs-autorefs mkdocs-material-extensions mkdocstrings
cd ${CATALOG_REPO_DIR}
python3 -m mkdocs serve
# OR
python3 -m mkdocs serve -a localhost:9000
```

You should now be able to open your browser to the link in the `serve`
output to verify. Additionally, the output of the serve command would
display any errors with building the site.

As long as the `serve` command is running, it will auto-detect changes
to the files so you can continue to modify them and see the updates in
your browser.

## Running checks on Markdown files

```bash
cd ${CATALOG_REPO_DIR}
# Lint checks
docker run --rm -it \
  -v $PWD:/code \
  registry.gitlab.com/pipeline-components/markdownlint-cli2:latest \
  markdownlint-cli2 "**/**.md"

# Spell checks
docker run --rm -it \
  -v $PWD:/code \
  registry.gitlab.com/pipeline-components/markdown-spellcheck:latest \
  mdspell --report '**/*.md' --ignore-numbers --ignore-acronyms
```
