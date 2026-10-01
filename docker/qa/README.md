# QA tooling for the dev stack

Scripts used to test the running Docker demo (`docker compose up`, app at http://localhost:8080, Mailpit at http://localhost:8026). The PHP ones run **inside** the app container; the Node ones run on the host against the published port.

```sh
# copy a script into the container, then run it
docker compose cp docker/qa/view-smoke.php app:/tmp/view-smoke.php
docker compose exec -T app php /tmp/view-smoke.php /admin/1/invoices /admin/1/bills      # HTTP status per page, as the demo owner
docker compose exec -T app php /tmp/route-sweep.php bookkeeper@apex.test                  # every admin GET route as one demo user
docker compose exec -T app php /tmp/print-sweep.php                                        # every print / export format from demo data
docker compose exec -T app php /tmp/xcheck.php                                             # financial cross-checks (aging = ledger, stock = GL, FA = GL …)
docker compose exec -T app php /tmp/page-html.php /admin/1/tax-returns/create              # raw HTML of a page (inspect markup)
docker compose exec -T app php /tmp/resolve-routes.php > /tmp/urls.json                    # every admin page as a concrete URL with real record ids

# browser passes (Playwright drives the installed Google Chrome; `npx playwright install chromium` if Chrome is absent)
OUT=/tmp/qa && mkdir -p $OUT && cp /tmp/urls.json $OUT/urls.json
NODE_PATH=$PWD/node_modules node docker/qa/audit.cjs $OUT     # every page screenshotted to $OUT/vault-out/QA/screenshots/<date>/, findings in audit-results.json
```

Demo logins (all with password `password`): owner@apex.test, accountant@apex.test, approver@apex.test, bookkeeper@apex.test, viewer@apex.test.
Database backups the dev scripts leave in the `app-data` volume: `/data/database.sqlite.before-*`.
