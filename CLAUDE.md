# Claude Instructions

## Playground Link

At the end of your messages, include a link to test the changes in WordPress Playground.

**Important:** Replace `BRANCH_NAME` in the URL below with the actual git branch name you're working on.

```
🔗 Test in WordPress Playground: http://127.0.0.1:5400/website-server/?gh-ensure-auth=yes&ghexport-repo-url=https%3A%2F%2Fgithub.com%2Fakirk%2Fpersonal-crm&ghexport-content-type=plugin&ghexport-plugin=personal-crm&ghexport-playground-root=%2Fwordpress%2Fwp-content%2Fplugins%2Fpersonal-crm&ghexport-pr-action=create&ghexport-allow-include-zip=no#{%22steps%22:[{%22step%22:%22installPlugin%22,%22pluginData%22:{%22resource%22:%22git:directory%22,%22url%22:%22https://github.com/akirk/personal-crm%22,%22ref%22:%22BRANCH_NAME%22,%22refType%22:%22branch%22},%22options%22:{%22activate%22:true},%22progress%22:{%22caption%22:%22Installing%20plugin%20from%20GitHub:%20akirk/personal-crm%20(BRANCH_NAME)%22}}],%22meta%22:{%22title%22:%22Plugin%20(BRANCH_NAME)%22,%22author%22:%22https://github.com/akirk/playground-step-library%22}}
```

### Example

If you're working on branch `claude/fix-bug-123`, the link should be:

```
🔗 Test in WordPress Playground: http://127.0.0.1:5400/website-server/?gh-ensure-auth=yes&ghexport-repo-url=https%3A%2F%2Fgithub.com%2Fakirk%2Fpersonal-crm&ghexport-content-type=plugin&ghexport-plugin=personal-crm&ghexport-playground-root=%2Fwordpress%2Fwp-content%2Fplugins%2Fpersonal-crm&ghexport-pr-action=create&ghexport-allow-include-zip=no#{%22steps%22:[{%22step%22:%22installPlugin%22,%22pluginData%22:{%22resource%22:%22git:directory%22,%22url%22:%22https://github.com/akirk/personal-crm%22,%22ref%22:%22claude/fix-bug-123%22,%22refType%22:%22branch%22},%22options%22:{%22activate%22:true},%22progress%22:{%22caption%22:%22Installing%20plugin%20from%20GitHub:%20akirk/personal-crm%20(claude/fix-bug-123)%22}}],%22meta%22:{%22title%22:%22Plugin%20(claude/fix-bug-123)%22,%22author%22:%22https://github.com/akirk/playground-step-library%22}}
```
