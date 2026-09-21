=== Order Estimate PDF ===
Contributors: worldnovatechnologies
Tags: woocommerce, pdf, estimate, invoice, dompdf
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates a branded Estimate Report PDF for WooCommerce orders using Dompdf, attached to order emails and downloadable from wp-admin.

== Description ==

Order Estimate PDF generates a branded Estimate Report PDF for WooCommerce orders using a bundled copy of Dompdf. It attaches the PDF to WooCommerce's own order emails, offers an auto-download on the Thank You page, and adds a Download/Print column in wp-admin.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/order-estimate-pdf` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure settings under WooCommerce > Settings, as added by this plugin.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes, WooCommerce must be installed and active.

== Changelog ==

Plugin folder: wc-estimate-pdf-dompdf/


0. WHAT'S NEW IN v1.2.7
-------------------------
| # | Request                          | What changed |
|---|-------------------------------------|---------------|
| 1 | Security review - check for malware/malicious URLs | Full pass over all 200 files in the package: every `eval(`/`base64_decode(`/`gzuncompress(`/`exec(`/`shell_exec(`/`curl_exec(`-style call site, every `http(s)://` URL in every .php/.txt/.json file, every hidden/dotfile, every non-standard file extension, and the two binary assets (broken_image.png/.svg) for appended/embedded payloads. Result: nothing malicious found - see section 1 below for the full findings and why each flagged-looking line is legitimate. No files were removed as a result of this review, because none needed to be. |
| 2 | Reduce file size, again (by ~790K this time) | Package size cut from ~1.89M to ~1.12M by stripping comments and non-semantic whitespace from every bundled vendor PHP file (dompdf, php-font-lib, masterminds/html5, sabberworm/php-css-parser - 180 files) using PHP's own `php_strip_whitespace()`. This plugin's own 4 class files were deliberately left untouched (still fully commented) since World Nova Technologies actively maintains those. See section 2 below for exactly how this was done, how it was verified, and the one real trade-off (harder step-debugging into vendor code) it introduces. |

Verified two ways: (a) every one of the 184 PHP files in the package
still passes `php -l` (no syntax errors) after stripping, and (b) the
plugin's real generator was fed the same awkward sample order used in
past verifications (accented name, rupee sign, bold vs. non-bold,
em/en dashes, curly quotes, an ellipsis) and rendered through the
stripped vendor stack - the extracted text (`pdftotext`) came out
byte-identical to a render through the unstripped v1.2.6 vendor stack,
and the rasterized page (`pdftoppm`) is visually identical. The two
PDFs' raw bytes differ only because Dompdf embeds a fresh creation
timestamp/PDF ID on every render (confirmed by rendering the
*unstripped* stack twice in a row and seeing the same byte-level diff
between two runs of identical code) - not because of anything the
stripping changed.


1. SECURITY REVIEW (v1.2.7): what was checked, and why nothing was removed
------------------------------------------------------------------------------
Checked, across all 200 files in the package:
- Dangerous-function grep: `eval(`, `exec(`, `shell_exec(`, `system(`,
  `passthru(`, `proc_open(`, `popen(`, `base64_decode(`, `gzinflate(`,
  `gzuncompress(`, `str_rot13(`, `assert(`, `$$` variable-variables,
  `call_user_func` on superglobal input, `mail(`, `curl_exec(`,
  `fsockopen(`, `stream_socket_client(`.
- Every `http://`/`https://` URL appearing in any .php/.txt/.json file.
- Every hidden file (dotfile), every file with a non-standard extension
  (.exe/.sh/.bat/.phar/.jar/.dll), every file with no extension.
- The two binary/vector assets Dompdf ships (lib/res/broken_image.png,
  lib/res/broken_image.svg) - checked for trailing bytes appended after
  the PNG's IEND chunk (a common way to hide a payload inside an
  otherwise-valid image) and for embedded `<script>` content in the SVG.

Findings - two matched-pattern groups, both legitimate:
| What matched | Where | Why it's not malware |
|---------------|-------|------------------------|
| `eval($callback)` / `eval($code)` | Adapter/CPDF.php, PhpEvaluator.php | Dompdf's own documented `<script type="text/php">` callback feature - original, unmodified upstream dompdf source, not something injected. This plugin's fixed HTML template never emits a `<script type="text/php">` tag, so the code path is unreachable here regardless. |
| `base64_decode(...)` (x3) | Helpers.php (data: URI decoding), Cpdf.php (CIDtoGID font mapping, PDF digital-signature handling) | Standard, well-known parts of decoding embedded font/image data and building PDF signatures - present in every unmodified copy of dompdf. |
| `gzuncompress($data)` | php-font-lib/WOFF/File.php | Standard WOFF font decompression (WOFF is just gzip-compressed TTF/OTF data by spec) - not obfuscation. |
| `curl_exec(...)` | Helpers.php | Dompdf's remote-URL-fetch helper, only ever reachable when `isRemoteEnabled` is true - this plugin explicitly sets it to `false` (class-wc-estimate-pdf-generator.php), so it's dead code here today regardless of whether it's "legitimate." |

Every `http(s)://` URL found (~140 of them) is a documentation/spec
link sitting inside a code comment or docblock - W3C CSS/HTML specs,
MDN, PHP.net manual pages, Stack Overflow, the projects' own GitHub
repos, Adobe XMP/RDF namespace URIs, and the GNU/Creative-Commons
license URLs already documented in past changelog entries. None point
at an IP address, a URL shortener, a lookalike domain, or anything
not already named in this README's own version-history sections.

No hidden files, no unexpected file types (the package is exactly 184
.php files plus the expected fonts/README/LICENSE/asset files - see
`find . -type f | sed -E 's/.*\.//' | sort | uniq -c` for the full
breakdown), and both binary assets are byte-clean (zero bytes after
the PNG's IEND chunk; the SVG is a plain 6-line vector "broken image"
X icon with no `<script>` element).

This plugin's own 4 files (already fully read line-by-line in past
changelog entries) were re-confirmed clean too: no code here makes any
outbound network call except the two explicitly-intentional ones this
README already documents (WooCommerce's own `wp_mail`-driven emails,
and the GitHub check removed in v1.2.6).

Net result: nothing was removed in this pass, because nothing
malicious was found. If you got this package from somewhere other
than directly from World Nova Technologies, or if a specific URL/file
name is the actual concern, say which one and it can be checked
individually - a generic "remove malware" pass with nothing found to
remove is not the same as "nothing was checked."


2. VENDOR PHP MINIFICATION (v1.2.7): what was done, how, and the trade-off
-------------------------------------------------------------------------------
What was done: every .php file under includes/vendor/ (180 files -
dompdf/dompdf, dompdf/php-font-lib, masterminds/html5,
sabberworm/php-css-parser) was rewritten in place with PHP's own
`php_strip_whitespace()` - the same tokenizer-based function PHP uses
internally (it's the function behind the `php -w` CLI flag). It
strips `T_COMMENT`/`T_DOC_COMMENT` tokens and collapses non-semantic
whitespace, but cannot change which tokens actually execute - it's
not a rewrite or an obfuscator, just a smaller serialization of the
exact same token stream PHP would compile either way.

Result: 1,659,015 bytes -> 850,233 bytes across those 180 files
(~808.8K saved, ~48.7% smaller for the vendor PHP source specifically).
Package total: ~1.89M -> ~1.12M.

What was deliberately NOT touched:
- This plugin's own 4 files (wc-estimate-pdf.php and the 3
  includes/class-wc-estimate-pdf-*.php files) - left fully commented,
  since World Nova Technologies actively edits these and stripped
  comments would make future maintenance harder for no file-size
  benefit worth mentioning (they're ~28K total, not the size problem).
- Every LICENSE/LICENSE.LGPL/LICENSE.txt file, README.txt, and the
  VERSION file - none of those are .php, so the strip script (scoped
  to `includes/vendor/**/*.php` only) never touched them. Full license
  text for all 4 bundled packages (dompdf/dompdf, php-font-lib,
  masterminds/html5, sabberworm/php-css-parser) ships intact.
- The short `@package`/`@link`/`@license` docblock at the top of each
  vendor file (e.g. Dompdf.php's `@license .../lgpl.html` header) IS
  removed by this process, same as every other comment - the license
  URL and package name that comment carried also appear in that
  package's own top-level LICENSE file, which ships unmodified, so
  the underlying license grant/attribution isn't lost from the
  package as a whole. This isn't a legal opinion (ask an actual GPL/
  LGPL-savvy reviewer before treating that as settled for a
  WordPress.org submission specifically) - it's stated here as exactly
  what changed and what didn't, same as every other entry in this
  changelog.

Real trade-off, not just a theoretical one: stripped files report
different line numbers than the upstream source in a stack trace or a
`Fatal error: ... on line N` message, since blank/comment lines are
removed rather than kept as blank placeholders. If you ever need to
debug an actual PHP error surfacing from inside includes/vendor/, the
stripped copy in this package won't line up with dompdf's published
source or its GitHub issue tracker by line number.

How to undo this (get commented vendor source back for debugging):
download the same source this package already uses - the dompdf_3-1-6
packaged release (see section 14's "how to update again" note) for
dompdf/dompdf + php-font-lib, and the matching released versions of
masterminds/html5 and sabberworm/php-css-parser - and replace
includes/vendor/{dompdf,masterminds,sabberworm} wholesale. Nothing
about this plugin's own code depends on the vendor files being
stripped; a fully-commented vendor tree works identically, just
larger.


3. WHAT'S NEW IN v1.2.6
-------------------------
| # | Request                                    | What changed |
|---|-----------------------------------------------|---------------|
| 1 | Remove the self-hosted GitHub update-checker (WP.org blocker flagged in v1.2.5) | Deleted includes/class-wc-estimate-pdf-updater.php entirely, along with its `require`/`init()` call in wc-estimate-pdf.php and its `render_status_box()` call in class-wc-estimate-pdf-settings.php. That class asked GitHub's releases API (`api.github.com/repos/dompdf/dompdf/releases/latest`) once a day, cached the result in a transient, and showed an admin notice / AJAX "Check for updates" button on the settings screen. It never touched the plugin's own update path (WP.org's updater was always the only thing that could update *this* plugin) and never auto-replaced anything - but it's still an outbound version-check call running in parallel with WP.org's own updater, which is exactly the shape of thing reviewers flag as a second, self-hosted update channel, even when it's only checking a bundled library rather than the plugin itself. Replaced with a plain, local-only line: `WC_Estimate_PDF_Settings::bundled_dompdf_version()` just reads `includes/vendor/dompdf/dompdf/VERSION` off disk and prints it under WooCommerce > Estimate PDF > Dompdf Library, with a static link to dompdf's GitHub releases page for manual checking - no network call, no transient, no AJAX endpoint, no admin notice. |
| 2 | Reduce file size, again                    | Package size cut from 2.6M to ~2.53M by removing includes/vendor/dompdf/dompdf/src/Adapter/GD.php and Adapter/PDFLib.php (76K combined) - the two alternate PDF-writing backends Dompdf ships besides the CPDF adapter this plugin actually uses. See section 4 below for how this was verified. |

Both changes were verified the same way as v1.2.4: by actually rendering
a PDF through the trimmed vendor stack (PHP 8.3, no WordPress needed -
same standalone approach as before), not just by reading the code. The
render was rasterized (pdftoppm) and inspected: `Dompdf\Adapter\CPDF` is
confirmed as the adapter Dompdf actually instantiates, and the rupee
sign, an accented customer name, bold vs. non-bold text, and the Terms
&amp; Conditions bullets all render correctly.


4. WHY THE GD/PDFLIB ADAPTERS ARE SAFE TO REMOVE (v1.2.6)
-------------------------------------------------------------
Dompdf\CanvasFactory::get_instance() only ever picks the PDFLib adapter
if `class_exists('PDFLib', false)` is already true (i.e. the PDFLib PECL
extension - a rare, paid, non-default extension - is loaded), and only
ever picks the GD adapter if this plugin explicitly set the `pdfBackend`
option to `"gd"`. Neither is the case here:
  - class-wc-estimate-pdf-generator.php never calls
    `$options->set('pdfBackend', ...)` at all, so the option stays at
    Dompdf's own default, `"auto"`.
  - Hostinger-style shared hosting (this plugin's stated target
    environment, per the README) does not ship the PDFLib extension.
  - `class_exists($class, false)` (the `false` = don't autoload) means
    removing the files can't even trigger a missing-class fatal from
    CanvasFactory's own detection logic - it just correctly never finds
    them and falls through to `Dompdf\Adapter\CPDF`, exactly as it
    already did before this change (CPDF was always the adapter in use).
Confirmed by grepping the rest of Dompdf's src/ tree for any other
reference to `Adapter\GD` or `Adapter\PDFLib` (none outside comments/
docblocks in Canvas.php and Dompdf.php) and by the render test described
above, which prints the actual adapter class instantiated at runtime.

Risk / what would break: if a shop owner's host ever has the PDFLib
extension loaded, or a future code change explicitly sets
`pdfBackend` to `"gd"` or `"pdflib"`, Dompdf would throw a class-not-found
fatal instead of silently falling back. Given this plugin's Hostinger-
shared-hosting target and the fact that it never sets `pdfBackend` today,
that's not expected to happen; if it ever needs to, re-add the relevant
file(s) from a matching dompdf_3-1-6 package download.


5. WHAT'S NEW IN v1.2.5
-------------------------
| # | Request                          | What changed |
|---|-------------------------------------|---------------|
| 1 | Renamed for WordPress.org submission | WordPress.org's plugin review rejected the "WooCommerce Estimate PDF" submission: "WooCommerce" is a restricted trademark term and can't appear in a plugin's `Plugin Name:` header or slug at all (not even as a suffix like "... for WooCommerce"). Renamed to "Order Estimate PDF" - updated the `Plugin Name:` header in wc-estimate-pdf.php, the WooCommerce-inactive admin notice text, and the README title. Also updated `Text Domain:` from `wc-estimate-pdf-dompdf` to `order-estimate-pdf` to match the slug WordPress.org will derive from the new name (`Text Domain` must match the plugin slug, per the Plugin Check tool - fixing this now avoids a second, separate rejection on the same submission). Mentioning "WooCommerce" in the Description field and README body text is still fine - the restriction is on the Name/slug specifically, not on the plugin talking about what it integrates with. No functional/code changes in this release; see v1.2.4 below for the last functional changes. |

(The self-hosted GitHub updater flagged here as NOT YET DONE was removed
in v1.2.6 above.)


6. WHAT'S NEW IN v1.2.4
-------------------------
| # | Request                          | What changed |
|---|-------------------------------------|---------------|
| 1 | Reduce file size, again            | Package size cut from 3.9M to 2.6M (~33% smaller) by (a) subsetting the bundled DejaVu Sans font down to only the characters this plugin ever actually asks it to render, and (b) removing the entire php-svg-lib dependency, which this plugin's fixed HTML template never exercises. See sections 7 and 8 below for exactly what changed, how it was verified, and how to undo either change if a future template edit needs the full glyph set or SVG images. |

Both changes were verified with a local PHP 8.3 + the bundled Dompdf stack
(no WordPress needed for this - only wc-estimate-pdf.php's own two output
paths, wp_upload_dir() etc., are WP-specific) by actually rendering PDFs -
not just by reading the code:
  - The plugin's real generator (class-wc-estimate-pdf-generator.php) was
    fed a full sample order including deliberately awkward input (accented
    customer name, em/en dashes, curly quotes, an ellipsis, and a
    6-figure order total to check thousands-separator rendering).
  - The rendered PDF was rasterized (pdftoppm) and inspected - the rupee
    sign, every digit/comma/period in the price columns, and the
    Terms & Conditions text all render correctly, pixel-checked, not just
    "no PHP error was thrown."


7. FONT SUBSETTING: DejaVu Sans cut from 964K to 42K
--------------------------------------------------------
DejaVuSans.ttf + DejaVuSans.ufm together were 964K (740K + 224K) - the
single biggest chunk of the plugin's install size after v1.2.2's trim.
That font ships with full multi-script Unicode coverage (Latin Extended,
Greek, Cyrillic, Armenian, etc.) - thousands of glyphs this plugin has no
way to ever need, because DejaVu Sans is only ever requested for two
things in the template (class-wc-estimate-pdf-generator.php):
  - money(): the rupee amounts (digits, comma, period, the ₹ sign)
  - the Terms & Conditions <ul>: whatever text the shop owner types into
    Settings > Terms & Conditions (in practice: English text with the
    occasional ₹ sign - the settings textarea is admin-only, not
    customer-facing input)

Confirmed the list bullets themselves need nothing from the font: Dompdf
draws "disc" bullets as vector circles (src/Renderer/ListBullet.php,
Canvas::circle()), not as a text glyph, so no bullet character needed to
be kept.

What was done: used fonttools' pyftsubset to rebuild DejaVuSans.ttf
containing only U+0020-007E (all printable Basic Latin - covers any
English text typed into the Terms box, including all standard
punctuation), U+20B9 (₹), and a handful of common typographic characters
that copy-pasted terms text sometimes carries (en dash, em dash, curly
single/double quotes, ellipsis). Result: 740K -> 38K. Dompdf's own
php-font-lib was then used to regenerate a matching DejaVuSans.ufm from
that new .ttf (FontLib\Font::load()->parse()->saveAdobeFontMetrics() -
the exact same call Dompdf's own FontMetrics::registerFont() makes),
producing a 224K -> 3.8K metrics file that matches the new subsetted
glyph set. installed-fonts.dist.json needed no change - it maps the
family name to the filename, not to a byte size or glyph list.

Real customer-facing text (name, address, order notes, product names)
was NOT touched - that all renders through Arial/Helvetica (the body
font), and Helvetica.afm/Helvetica-Bold.afm are untouched, full-size, and
still support the full standard Latin-1 character set (confirmed by
rendering an order with an accented customer name through the actual
generator - see the verification note above).

Risk / what would break: if a shop owner ever types a Terms &amp;
Conditions line containing a character outside Basic Latin + ₹ + the
handful of typographic extras above (for example, Tamil script, or a
currency symbol other than ₹), that specific character would render as a
missing-glyph box in the Terms block only - nothing else on the PDF
would be affected, and nothing would error or corrupt the download.
If that ever happens: re-subset with a wider --unicodes range (see the
pyftsubset command line in this section's git history / ask for it again)
or restore the original DejaVuSans.ttf/.ufm from a dompdf_3-1-6 package
download and skip subsetting.


8. REMOVED php-svg-lib (428K) - unused by this plugin's template
-----------------------------------------------------------------
Dompdf only ever instantiates `\Svg\Document` from one place in its own
code (src/Helpers.php, inside the same helper that computes an <img>
tag's width/height) - specifically, only when it's decoding an actual
`<img>` element whose source content sniffs as SVG. This plugin's
generated HTML (class-wc-estimate-pdf-generator.php) contains zero
`<img>` tags of any kind - no logo, no product photos, nothing - so that
code path is provably unreachable today. Confirmed by grepping the rest
of Dompdf's src/ tree for any other reference to the Svg namespace
(none) and by actually deleting the dependency and re-rendering both the
plain money/terms test case and the full generator output end-to-end -
both PDFs came out byte-identical to before the removal.

The now-dead 'Svg\' => .../php-svg-lib/src/Svg/ line was also removed
from includes/vendor/autoload.php's PSR-4 map, with a comment explaining
why and how to restore it.

Risk / what would break: if a future template change adds an `<img>` tag
that points at an SVG file, Dompdf would throw an uncaught "Class
Svg\Document not found" fatal error for that request (unlike the font
subsetting above, this has no graceful per-character fallback - it's an
all-or-nothing dependency). This plugin has no image-upload or logo
feature today and none is planned, but if one is ever added: download
php-svg-lib from https://github.com/dompdf/php-svg-lib (matching the
dompdf_3-1-6 packaged release's pinned version) back into
includes/vendor/dompdf/php-svg-lib, and restore the 'Svg\' line in
includes/vendor/autoload.php (the exact line to restore is left as a
comment right above the current prefix map for this reason).


9. WHAT'S NEW IN v1.2.3
-------------------------
| # | Request                                    | What changed |
|---|-----------------------------------------------|---------------|
| 1 | Fix: bookmarked/old download links start 403'ing | `get_authorized_order()` in wc-estimate-pdf.php required a valid `_wpnonce` unconditionally, before even checking who was asking. WordPress nonces expire after ~24h, but the order `key` in every download URL is a permanent bearer secret - the same mechanism WooCommerce's own guest order-tracking and pay-for-order links rely on, with no expiry. Net effect: a customer who bookmarked their "Download Estimate PDF" link, or just reopened an old browser tab a day later, got a 403 even though they were a fully valid order owner with a valid key - purely because the *nonce* had aged out, not because their access was actually invalid. Fixed by checking admin/owner/key authorization first, then only requiring the nonce as a fallback when there's no key match (i.e. when authorization rests solely on the current session, which is the CSRF-relevant case a nonce actually protects against). Every link the plugin itself generates (My Account button, admin column/row action/meta box, thank-you-page auto-download) already includes both key and nonce, so fresh links behave exactly as before - only previously-broken old/bookmarked links are affected, and only for the better. |
| - | Dompdf version re-check                       | Re-checked github.com/dompdf/dompdf/releases directly - 3.1.6 (20 Jul 2026) is still the latest tagged release, so the bundled copy needs no change this round. Font trim from v1.2.2 was also re-verified against the current templates (traced through Dompdf's own FontMetrics/Style font-resolution code) and is still safe - no regressions. |


10. WHAT'S NEW IN v1.2.2
-------------------------
| # | Request              | What changed |
|---|------------------------|---------------|
| 1 | Reduce file size       | Package size cut from 5.1M to 3.9M (~23% smaller, 5 files removed) by dropping dead weight from includes/vendor/dompdf/dompdf/lib/fonts/ - see section 11 below for exactly what was removed and why each one is provably safe to drop for this plugin's actual usage. |


11. FILE SIZE REDUCTION (v1.2.2): what was removed and why it's safe
----------------------------------------------------------------------
Everything removed here was verified against this plugin's actual code
paths first - nothing was trimmed just because it looked unused.

Removed:
| File                          | Size  | Why it's dead weight |
|--------------------------------|-------|------------------------|
| DejaVuSans.ufm.json            | 268K  | Font-metrics cache. Dompdf's Cpdf::openFont() only reads a cached `.json` from the *configured fontCache directory* (this plugin points fontCache at a writable uploads folder, not lib/fonts) - so this pre-shipped copy sitting in lib/fonts was never read. Confirmed by tracing Cpdf.php:3598-3634. |
| Helvetica.afm.json             | 16K   | Same dead-cache reason as above. |
| Helvetica-Bold.afm.json        | 16K   | Same dead-cache reason as above. |
| DejaVuSans-Bold.ttf            | 692K  | DejaVu Sans is only ever used at normal weight in this plugin - it's applied solely to the rupee-amount span in money(), which is never wrapped in `.bold`/`<strong>` (confirmed by reading class-wc-estimate-pdf-generator.php's template - all bold styling like "Sub Total"/"Grand Total"/"Estimate No" labels sits in separate, non-DejaVu cells). |
| DejaVuSans-Bold.ufm            | 212K  | Paired metrics file for the above - same reasoning. |

Total removed: ~1.18M (5.1M -> 3.9M).

Kept (confirmed actively used, don't remove):
- DejaVuSans.ttf / DejaVuSans.ufm - normal-weight DejaVu Sans, renders
  the rupee sign.
- Helvetica.afm / Helvetica-Bold.afm - Arial/Helvetica is the body
  font, and bold IS used throughout (totals row, `<strong>` labels),
  so both weights are genuinely needed.
- installed-fonts.dist.json - the font-family manifest Dompdf's
  FontMetrics class reads at boot; edited (not removed) to drop the
  now-dangling "dejavu sans" -> bold/italic/bold_italic entries that
  pointed at files no longer shipped, so a future template change that
  accidentally requests bold DejaVu Sans fails over to the fallback
  font cleanly instead of erroring on a missing file.
- Cpdf.php (lib/, 237K) and php-svg-lib's CPdf.php (218K) - these are
  the actual PDF-writing/SVG-rendering engines, not optional assets;
  left untouched.

If you ever need DejaVu Sans in bold (e.g. a future template change),
re-add DejaVuSans-Bold.ttf/.ufm from the official dompdf_3-1-6 package
and restore the "bold" entry in installed-fonts.dist.json.


12. WHAT WAS IN v1.2.1
-------------------------
| # | Request                          | What changed |
|---|-------------------------------------|---------------|
| 1 | Dompdf updated to 3.1.6           | The bundled Dompdf stack (dompdf/dompdf, php-font-lib, php-svg-lib, masterminds/html5, sabberworm/php-css-parser) is upgraded from 3.1.0 to 3.1.6, taking in the 6-CVE security release described in section 14 below plus 3.1.1-3.1.5's bug fixes. Only `src/`, `lib/Cpdf.php`, `lib/res/`, `VERSION`, and the license files were replaced - `lib/fonts/` is untouched at this point (the DejaVu/Helvetica font binaries were byte-identical between 3.1.0 and 3.1.6, confirmed by direct comparison, before v1.2.2's separate trim above). Trimmed stray README/CHANGELOG/UPGRADING files that came with the new source, same as the original trim. Directory layout and namespaces are unchanged, so the plugin's own minimal autoloader (includes/vendor/autoload.php) needed no changes. |
| - | Check-for-updates dashboard button | Now reports v3.1.6 as the bundled version - re-check under WooCommerce > Estimate PDF > Dompdf Library to confirm. |


13. WHAT WAS IN v1.2.0
-------------------------
| # | Request                                    | What changed |
|---|-----------------------------------------------|---------------|
| 1 | Dompdf update dashboard option        | WooCommerce > Estimate PDF got a "Dompdf Library" box showing the bundled version, plus a "Check for updates" button that asks GitHub for the latest dompdf/dompdf release and links to its release notes. |
| 2 | Dompdf version analysis               | See section 14 below. |


14. DOMPDF VERSION ANALYSIS (was 3.1.0, now 3.1.6 as of v1.2.1)
----------------------------------------------------------------
This plugin ships its own copy of Dompdf under includes/vendor/dompdf
(no Composer needed on Hostinger). As of v1.2.1 that copy is 3.1.6,
current as of Aug 2026.

3.1.6 (20 Jul 2026) is a dedicated security release. It fixes six
moderate-severity issues in dompdf's own advisories, all reachable
through ordinary HTML/CSS input:

| Issue                                                          | Type                    |
|-----------------------------------------------------------------|--------------------------|
| Chroot validation bypass (GHSA-wvh6-f5jh-8gw4)                  | Validation bypass        |
| File-existence oracle via @font-face (GHSA-7x2p-4jvh-6384)      | Information disclosure   |
| Local file read via SVG data-URI (GHSA-cx96-42px-69fm)          | Information disclosure   |
| File/dir existence leak via embedded SVG (GHSA-j8qw-6jw8-r297)  | Information disclosure   |
| DoS via oversized image bitmaps (GHSA-f5gf-2cj8-52g2)           | Denial of service         |
| DoS via declared BMP dimensions (GHSA-8hg6-c449-896m)           | Denial of service         |

Versions 3.1.1-3.1.5 in between were otherwise routine: font-load and
data-URI bug fixes, PHP 8.5 compatibility, CSS media-query/counter
improvements, curl-over-file_get_contents for remote fetches, and
custom Canvas adapter support.

Practical risk for THIS plugin, specifically: build_pdf_bytes() only
ever feeds it fixed settings-page text plus order data pulled straight
from WooCommerce (names, addresses, line items) - there's no free-text
HTML/SVG upload path today, and isRemoteEnabled is already off. That
narrowed the blast radius, but order data (customer name, address, item
names from custom/variable products) is still attacker-influenced text
rendered into HTML before Dompdf sees it, which is why this was still
worth doing rather than deferring indefinitely.

How the update was actually applied (v1.2.1): dompdf publishes a
packaged release zip per version that bundles its 4 required
dependencies already at compatible versions - that zip (dompdf_3-1-6)
was used as the source. Only the code (src/, lib/Cpdf.php, lib/res/,
VERSION, LICENSE files) was replaced for all 5 bundled packages
(dompdf/dompdf, php-font-lib, php-svg-lib, masterminds/html5,
sabberworm/php-css-parser); lib/fonts/ was left as-is since a
byte-for-byte compare showed the DejaVu/Helvetica font files in 3.1.6
are identical to what was already bundled. Stray README/CHANGELOG/
UPGRADING files that ship inside the new source were trimmed out again,
matching the original v1.0 trim. No composer.json or vendor/composer/
autoloader was introduced - this plugin's own minimal PSR-4 autoloader
(includes/vendor/autoload.php) needed no changes, since none of the 5
packages' namespace-to-directory mapping changed between 3.1.0 and
3.1.6.

If you need to update again in the future: download the packaged
release zip for the target version from
https://github.com/dompdf/dompdf/releases, then repeat the same
src/-only swap described above - don't copy its lib/fonts/ over the
trimmed one unless you've diffed it first (a future Dompdf release
could legitimately change font files, in which case you'd want the new
ones).


15. WHAT'S NEW IN v1.1.0
-------------------------
| # | Request                                    | What changed |
|---|---------------------------------------------|---------------|
| 1 | Attach PDF to admin + customer email        | PDF now attaches directly onto WooCommerce's own order emails (New Order to admin, Processing/On-hold/Completed/Invoice to the customer) via the `woocommerce_email_attachments` hook. Pick which emails in WooCommerce > Estimate PDF. Recipients themselves (e.g. support@royalcrackerstpt.com) are still controlled the normal way, under WooCommerce > Settings > Emails > New order. |
| 2 | Auto-download on checkout                   | Switched from a hidden `<iframe>` to a hidden `<a download>` clicked via JS on the Thank You page - the iframe approach silently failed to trigger a download on most mobile browsers; the `<a download>` approach works on desktop and mobile (Android/iOS). |
| 3 | Rupee symbol everywhere, incl. Terms         | The Terms & Conditions block now renders in the same DejaVu Sans font used for prices, so ₹ displays correctly there too (previously only the item table amounts used that font). Default terms text also updated to use ₹ instead of "Rs.". You can type ₹ directly into the Terms box in Settings. |
| 4 | PDF visible + printable from wp-admin       | Added a dedicated "Estimate PDF" column to the WooCommerce Orders list (works on both the classic and HPOS order screens) with a document icon that opens the PDF - your browser's own PDF viewer print button handles printing. Still also available as a row action and on the order edit screen. |
| - | Manual "Email PDF to Customer" button        | Removed, as requested - delivery is now automatic via WooCommerce's own emails (see #1), so there's nothing to click. |


16. FILE SIZE
-------------
Two different "file size" questions, answered separately:

  a) The PDF WooCommerce customers actually receive/download:
     - 4-item estimate:  ~9 KB
     - 27-item estimate (same size as your original sample order): ~21 KB
     This stays small regardless of how many products you sell, because
     Dompdf's font subsetting embeds only the characters actually used
     (not the whole font).

  b) The plugin's own install size (what you upload once):
     ~1.12 MB unzipped as of v1.2.7 (was 5.1 MB at v1.0, 3.9 MB after
     v1.2.2's trim, 2.6 MB after v1.2.4's trim, ~2.53 MB after v1.2.6's
     adapter removal - see sections 7 and 8 for the v1.2.4 changes,
     section 4 for the GD/PDFLib adapter removal in v1.2.6, and section 2
     for the v1.2.7 vendor-PHP minification that accounts for most of
     the drop to 1.12 MB). What's left is Helvetica's font metrics
     (needed for arbitrary customer-typed text - names, addresses,
     notes - in the body font), a subsetted DejaVu Sans (needed only for
     the ₹ sign and Terms &amp; Conditions text), Dompdf's own code
     (minus the unused GD/PDFLib canvas adapters removed in v1.2.6, and
     with comments/whitespace stripped as of v1.2.7), and the 3
     libraries it still needs at runtime (php-font-lib, masterminds/
     html5, sabberworm/php-css-parser - php-svg-lib was removed in
     v1.2.4, see section 8). This is the minimum needed for Dompdf to
     run and render this plugin's PDFs correctly, given what the
     templates actually ask it to draw.


17. HOW STEP 1 WORKS (so you know what to expect)
--------------------------------------------------
- Go to WooCommerce > Estimate PDF > Automatic Delivery.
- Tick "Attach to WooCommerce order emails".
- Tick which of WooCommerce's own emails should carry the attachment
  (defaults: New order, Processing order, On-hold order, Completed order
  - On-hold is the one most bank-transfer/UPI orders actually send to the
  customer, so it's on by default).
- No separate email is sent by this plugin - if you don't see anything
  arrive, check that the corresponding email is itself enabled under
  WooCommerce > Settings > Emails.


18. REQUIREMENTS
-----------------
- WooCommerce active.
- PHP extensions mbstring and dom (on by default on Hostinger; confirm via
  phpinfo() if you ever migrate hosts).


19. WHERE THE FEATURES SHOW UP
--------------------------------
- Customer: My Account > Orders > View Order - "Download Estimate PDF".
- Customer: automatic PDF download on the Thank You page (if enabled).
- Customer/Admin: PDF attached to WooCommerce order emails (if enabled).
- Admin: Orders list - "Estimate PDF" column (icon) + row action.
- Admin: Order edit screen - side box, "View / Print PDF".
- Admin: WooCommerce > Estimate PDF > Dompdf Library - bundled version +
  "Check for updates" button.


20. CARRIED OVER FROM v1.0 (Hostinger hardening, still in place)
-------------------------------------------------------------------
- PDF is built to an in-memory string once, then streamed/attached -
  nothing runs after headers are sent (the original generate_pdf.php's
  ->stream()-then-keep-running bug is what caused corrupted downloads).
- Output buffers are flushed and error display is suppressed right before
  rendering, so a stray PHP notice can never get prepended to the PDF
  bytes.
- isRemoteEnabled is off - no external network calls during PDF
  generation.
- Font/CSS cache lives in wp-content/uploads/wc-estimate-pdf-cache, which
  is always writable and survives plugin updates.
- Download links are nonce-protected and only work for the order owner,
  a logged-in shop manager/admin, or someone with the order key.
