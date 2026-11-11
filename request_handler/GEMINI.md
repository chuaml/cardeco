# Context
- PHP files here are Tier-2 controllers: each file maps to a pathname directly and is executed when that pathname is navigated to (see `src/Routing/RouteResolver.php` for the exact precedence)
- once a PHP file here is executed, its last step is to set `$_view_html_path` so `router.php` renders a file from @../view/** inside `view/_layout.main.html`
- do NOT call `$con->close()` here: `router.php` owns the connection and some views query while rendering
- new pages must NOT be added here; implement them as `App\Handler\<Name>Handler` classes in @../src/Handler/** (Tier 1 is resolved before this directory). The daily-order pages (`index`, `lazada`, `bigseller`, `tiktok`, `shopee`) and `ShippedItemReport` have already moved there
- sadly, not all legacy pages are mapped and follow this pattern yet; some pathname navigation bypass this directory @./** directly (see @../legacy_pages/request_handler/**)
