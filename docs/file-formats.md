# File formats

| Format | Notes |
| --- | --- |
| `.csv`, `.txt` | Delimiter auto-detected (`, ; \| tab`). UTF-8 used as is; UTF-16 and Windows-1252 are converted. |
| `.xlsx`, `.xlsm`, `.ods` | Streamed row by row (constant memory). |
| `.xls` | Needs `composer require phpoffice/phpspreadsheet`; loaded fully into memory, so keep files modest (or save as `.xlsx`). |

- The **first row must hold the column headers**.
- Workbooks with several sheets ask which sheet to import; the console command takes `--sheet=<index>`.
- Cell values are read as displayed values; dates become `Y-m-d H:i:s`. Fully blank rows are skipped.
- Uploads are converted to a UTF-8 CSV next to the original on the same disk; the original is kept (its SHA-256 goes in the
  [audit](audit-and-approval.md) when it is on).
- Typed cells: numbers stay numbers, dates become `Y-m-d H:i:s`, booleans `1`/`0` and formulas their cached result — in `.xlsx`, `.ods` **and legacy `.xls`**. See [Other spreadsheet libraries](interop.md) for files from Laravel Excel, Simple Excel, Fast Excel and friends.
