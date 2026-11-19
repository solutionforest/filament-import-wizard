# Works with other spreadsheet libraries

You do **not** have to choose. This package reads and writes spreadsheets itself (OpenSpout, already a Filament dependency; PhpSpreadsheet for legacy `.xls`), and every
popular Laravel spreadsheet package produces or consumes the same standard files. So a file written by any of them imports through the wizard, and a file we export opens in any of them.
That is **tested with the real libraries**, not assumed:

| Library | Underneath | Files it writes → our import | Our export → read by it | Notes |
| --- | --- | --- | --- | --- |
| [maatwebsite/excel](https://github.com/SpartnerNL/Laravel-Excel) 4.x | PhpSpreadsheet | ✅ xlsx · csv · xls · ods | ✅ xlsx · csv | Its default value binder turns text that looks like a number (`+14155550101`, `0071234567`) into a **number when it writes**, dropping `+` and leading zeros before we ever see the file. Use a `StringValueBinder` (or a text column format) for phone numbers and codes. |
| [spatie/simple-excel](https://github.com/spatie/simple-excel) 3.x | OpenSpout | ✅ xlsx · csv · ods (incl. real numeric cells) | ✅ xlsx · csv | — |
| [rap2hpoutre/fast-excel](https://github.com/rap2hpoutre/fast-excel) 5.x | OpenSpout | ✅ xlsx · csv · ods | ✅ xlsx · csv | — |
| [nikazooz/laravel-simplesheet](https://github.com/nikazooz/laravel-simplesheet) | box/spout 3 | ✅ xlsx · csv · ods (tested with box/spout 3, its engine) | ✅ xlsx · csv | The package itself supports Laravel ≤ 8 and **cannot be installed** next to Filament 5 / Laravel 12+; files from apps that still use it import fine. |
| [viest/php-ext-xlswriter](https://github.com/viest/php-ext-xlswriter) (`xlswriter` extension) | libxlsxwriter | ✅ files in the same format — tested with fixtures written by **XlsxWriter**, libxlsxwriter's origin, in its constant-memory "fast mode" and with typed cells | ✅ standard xlsx | The extension itself could not be installed in the test environment (no PHP dev headers), so it is verified through its format family, not through the extension. |

How it is checked: `tests/Interop/*` (one file per library, run by the CI **interop** job, which installs the libraries) write the same tricky rows — commas, quotes, newlines, unicode, emoji,
leading zeros, `+` phone numbers, an empty cell — with each library, import them, and compare what lands in the table; then export from the database and read the file back with each library.
`tests/FileInteropTest.php` covers typed cells and `.xls` with fixtures and needs no extra library.

## What the reader does with typed cells

| Cell in the file | Becomes |
| --- | --- |
| text | the text, untouched (leading zeros kept) |
| number | its plain number (`42`, `12.5`, `4155550101`) |
| date / date-time | `Y-m-d H:i:s` (every format, including legacy `.xls`) |
| boolean | `1` / `0` |
| formula | its **cached result** (`84`), never the formula text |
| empty | empty (`null` after import) |

(Two real bugs were found and fixed while writing these tests: formulas were read as `=D2*2` instead of their result, and legacy `.xls` dates arrived as Excel serial numbers and booleans as `TRUE`.)

## Using the libraries together

Nothing special is needed — they are independent. For example, keep Laravel Excel for your reports and use the wizard for imports; or build the file in your own job with any of them and hand it to
`HeadlessImport::for(...)->file($path)`. If you write files for the wizard with Laravel Excel, register a `StringValueBinder` as above.
