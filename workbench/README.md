# Demo app

A tiny, self-contained Filament panel (Orchestra Workbench + SQLite) that shows the wizard working end to end.
No database or web server to set up.

```bash
composer install
composer serve        # http://127.0.0.1:8765/admin — signed in automatically as the demo user
```

## Try it (2 minutes)

1. Open **Import contacts** and drop `workbench/samples/contacts.xlsx` (or `contacts.csv`).
2. **Map** — the columns are matched for you (`Full Name` → Name, `Work Email` → Email …). Change one and watch the preview table update.
3. **Review** — 21 rows are checked. Four have problems and are highlighted in the preview: two malformed e-mails, one missing
   name, one duplicate e-mail. Hover a red cell for the message.
4. **Submit for approval** — the demo requires approval above 20 rows.
5. Open **Import audit**, press **Approve** → the import runs; **History** shows the whole trail with the file's SHA-256.

Also try a big file: generate one and upload it — the chunked uploader sends it in pieces even though PHP's own limit is 2 MB.

```bash
python3 -c "print('Full Name,Work Email'); [print(f'Person {i},p{i}@example.com') for i in range(260000)]" > /tmp/big.csv
```

## Where things are

| What | File |
| --- | --- |
| The panel and plugin settings (chunked uploads, approval rule) | `workbench/app/Providers/AdminPanelProvider.php` |
| The wizard page (with a `UniqueInFile` duplicate check) | `workbench/app/Filament/Pages/ImportContacts.php` |
| The importer (columns, aliases for mapping) | `workbench/app/Filament/Imports/ContactImporter.php` |
| The single rules class shared by forms and imports | `workbench/app/Rules/ContactRules.php` |
| Sample files | `workbench/samples/` |
| Auto sign-in used by the demo (**demo only**) | `workbench/app/Http/DemoLogin.php` |

## Notes

- The demo allows self-approval because it has a single user — never do that in production.
- It uses the `sync` queue, so an import finishes inside the request. Use a real queue in an app.
- - Switch the demo language with `?lang=zh_HK` (or `zh_TW`, `zh_CN`, `en`); it sticks until changed.
- Reset the demo data: `vendor/bin/testbench workbench:build`.
