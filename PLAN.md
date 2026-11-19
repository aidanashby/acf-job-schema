# acf-job-schema: plan

Status (19th November 2026): v0.1.0 released 19th November 2026. All automated tests pass on the local Studio site: 67 builder checks, 22 WordPress checks, 40 front-end checks. The complete fixture's markup passes Google's Rich Results Test as a valid Job Posting (one optional note: no `addressRegion`, because the fixture maps no county). Not yet tested: the admin screens by eye (needs a login), Rank Math and LiteSpeed Cache (not installed locally), and updating from this release to a later one.

## Goal

Output valid Google `JobPosting` JSON-LD on single posts, built from ACF fields. All configuration lives in ACF's own field settings: each field gets a **Job schema property** setting, and there is no plugin settings page. The plugin converts ACF values into the formats Google requires, removes the markup when a job closes, and shows notices when a post type's mapping is missing something Google requires.

It reads post meta, not rendered page content, so it works with any theme. It is built and tested against Divi 5.

Requires: ACF 6.0+ (free or PRO) or Secure Custom Fields, WordPress 6.5+, PHP 7.4+. Tested locally with ACF 6.8.11.

## Decisions

1. **One setting, on the field.** A **Job schema property** select is added to the General tab of each ACF field (`acf/render_field_general_settings`). ACF saves it with the field, in the database or local JSON, so mappings move with the field group.
2. **The post type is set in ACF, through the field group's location rule** (confirmed 19th November 2026). Nothing is hard-coded. A field group with the rule "Post Type is equal to Vacancy" and a mapped Description field turns job schema on for Vacancy posts. Changing the rule to another post type moves the schema with it. A separate toggle in ACF's post type editor would duplicate that rule, and would only exist for post types registered through ACF, not for ones registered in code or by another plugin.
3. **Choice values use Google's codes.** Employment type and salary unit fields are select, radio, checkbox or button group fields whose choice *values* are Google's codes, with readable labels (ACF's `value : label` syntax, for example `FULL_TIME : Full time`). This removes the need for a mapping table. A notice lists any choice value Google won't accept.
4. **Organisation details come from the site.** `hiringOrganization` uses Rank Math's Knowledge Graph name and logo when Rank Math is active. Otherwise it uses the site title, home URL and custom logo (or site icon). A filter allows overrides.
5. **Fixed defaults in code, not settings.** Currency is GBP. The address country and the remote-applicant country default to the United Kingdom. Filters allow overrides.
6. **Works with Rank Math free.** Rank Math Pro can fill schema from custom fields, but outputs ACF values unconverted (dates as `Ymd`, for example), and the free version can't do it at all. The free version's `rank_math/json_ld` filter is enough for the plugin to join Rank Math's graph.

## Google's requirements

Checked against Google's JobPosting documentation on 19th November 2026.

| Property | Status | Format the plugin must produce |
|---|---|---|
| `title` | required | job title only, no codes, places, dates, salary or organisation name |
| `description` | required | HTML. Google reads only `p`, `ul`, `li` and `br`. Must differ from `title` and cover duties, skills, hours and requirements |
| `datePosted` | required | ISO 8601 date or datetime |
| `hiringOrganization` | required | `Organization` with `name`, plus `sameAs` and `logo` where set |
| `jobLocation` | required unless fully remote | `Place` > `PostalAddress`, must include `addressCountry` |
| `validThrough` | required if the job has a closing date | ISO 8601 datetime |
| `employmentType` | recommended | one or more of `FULL_TIME`, `PART_TIME`, `CONTRACTOR`, `TEMPORARY`, `INTERN`, `VOLUNTEER`, `PER_DIEM`, `OTHER` (case-sensitive) |
| `baseSalary` | recommended | `MonetaryAmount` (`currency` GBP) > `QuantitativeValue` with `value`, or `minValue` and `maxValue`, and `unitText` of `HOUR`, `DAY`, `WEEK`, `MONTH` or `YEAR` |
| `jobLocationType` | recommended | `TELECOMMUTE`, only for fully remote jobs. Hybrid jobs must not use it |
| `applicantLocationRequirements` | recommended, needed for remote jobs | `Country` (for example United Kingdom) |
| `identifier` | recommended | `PropertyValue` with `name` (organisation) and `value` (job reference) |
| `directApply` | recommended | boolean. Left out, because Google shows no effect and the plugin can't know how applications work |

Placement rules the plugin enforces: only on a page about one job, never on archive or listing pages, one `JobPosting` per page, and only where the description is visible on that page.

Expired jobs: Google bans expired postings and may take manual action. Removing the markup is one of the three accepted fixes, and this plugin uses it.

## The field setting

The **Job schema property** select offers only the properties that suit the field's type:

| Property option | ACF field types | If not mapped |
|---|---|---|
| Job title | text | post title |
| Description | WYSIWYG, textarea | **error**: no output |
| Date posted | date picker, date-time picker | post publish date |
| Closing date (`validThrough`) | date picker, date-time picker | **warning**: no closing date, so no automatic expiry |
| Employment type | select, radio, checkbox, button group | left out |
| Salary minimum, Salary maximum (empty maximum = single figure) | number | `baseSalary` left out |
| Salary unit | select, radio, button group | `baseSalary` left out |
| Fully remote | true/false | job treated as on-site |
| Street address, town or city, county or region, postcode | text | part left out |
| Country | text, select | United Kingdom |
| Map location | Google Map (PRO) | uses its stored address parts |
| Job reference (`identifier`) | text, number | left out |

Fields inside a Group field can be mapped too. Repeater and flexible content subfields can't in v0.1.

## Notices

Two levels: **errors** stop the markup, **warnings** don't.

**Mapping notices** are worked out per post type, from field groups whose location rules include "Post Type is X". They appear on that field group's edit screen and on the post type's list screen in wp-admin.
- Error: no field maps **Description**.
- Error: no location is possible (no address field, no Map location and no Fully remote field). Country alone defaults to the UK, but an address with only a country is too vague for a job search, so at least a town or postcode is required.
- Error: two fields in the groups for one post type map the same property.
- Warning: no field maps **Closing date**.
- Warning: a mapped employment type or salary unit field has choice values Google won't accept (listed).
- Warning: salary minimum and maximum are mapped without a salary unit, or the reverse.

**Post notices** report each post's data, using the same builder function as the front end, so there is one source of truth.
- A **Job schema** column on the post type's list screen: *Valid*, *Missing: description, location*, *Closed* or *Not set up*.
- A notice on the post edit screen when required values are empty, plus a link to Google's Rich Results Test for published posts.
- Nothing blocks publishing.

## Value conversion

- **Dates.** Read the raw stored value (`get_field( $name, $post_id, false )`), so the field's display format doesn't matter. ACF stores date pickers as `Ymd` and date-time pickers as `Y-m-d H:i:s`, both in site time. Convert using the site timezone and output with the offset. A date-only closing date means 23:59:59 on that day, as in `date-conditions-for-divi`.
- **Description.** Take the formatted value (a WYSIWYG field gets `wpautop`), keep only `p`, `ul`, `li` and `br`, and turn headings into `p`. An empty description, or one the same as the title, counts as missing.
- **Choices.** Keep values Google accepts and drop the rest. A checkbox field outputs an array.
- **Numbers.** Strip `£`, commas and spaces, then check the value is numeric. Anything else counts as missing, so `baseSalary` is left out.

## Front-end output

- Runs on `wp_head` for singular views of a published, non-password-protected post whose type has a valid mapping, and only when the post's values pass the required checks.
- **With Rank Math (free) active:** add the JobPosting to Rank Math's graph with the `rank_math/json_ld` filter, so the page has one connected graph. If the post's own Rank Math schema already contains a `JobPosting` (free lets editors add one by hand), the plugin outputs nothing and shows a warning on the edit screen.
- **Without Rank Math:** print its own `<script type="application/ld+json">`, encoded with `JSON_HEX_TAG` so a `</script>` in a field can't break the page.
- **After the closing date:** output nothing.

## Expiry and page caching

Page caching (LiteSpeed Cache is the case handled) would keep serving the markup after the closing date. On save, the plugin schedules a one-off WP-Cron event for the closing time. The event fires `litespeed_purge_post` for that post, and does nothing if LiteSpeed isn't active. It reschedules if the date changes and unschedules if the post is trashed. WP-Cron runs on the next visit after the due time, so a purge can be a few minutes late. A server cron for `wp-cron.php` closes that gap.

## Divi notes

- Description can't be mapped to post content, because a Divi-built post's content is Divi's stored block code, not readable text. The plugin won't try to render Divi content in `wp_head`, because Divi 5 never passes builder content through `the_content` and rendering it outside the page is unreliable. Use a WYSIWYG field and show it on the page through a Divi Theme Builder template with dynamic content, which also meets Google's rule that the description must be visible.
- No Divi module or Visual Builder code.

## Uninstall

Removes scheduled expiry events. The **Job schema property** setting stays saved inside ACF field definitions, where it has no effect without the plugin. The plugin doesn't edit field groups on uninstall, because that would rewrite the user's local JSON files.

## Later phase (not in v0.1)

- **Google Indexing API.** Google recommends it for job pages: it notifies Google on publish, update and expiry, so changes are crawled within hours. It needs a Google Cloud service account key stored on the site, and it makes external HTTP requests to Google.
- More than one location per job (from a repeater).

## Planned layout

```
acf-job-schema/
  acf-job-schema/              plugin folder (zipped for releases)
    acf-job-schema.php
    includes/field-setting.php ACF field setting, options by field type
    includes/mapping.php       finds mapped fields per post and per post type
    includes/build.php         builds JobPosting or returns errors and warnings
    includes/output.php        wp_head and Rank Math integration
    includes/expiry.php        cron scheduling and LiteSpeed purge
    includes/admin.php         notices, list column, edit screen notice
    assets/                    update icons (PNG) and their white SVG source
    plugin-update-checker/     bundled, reads public GitHub releases
    readme.md
    uninstall.php
  tests/                       fixture field group + build tests via studio wp eval-file
  build/  dist/
  PLAN.md  README.md  CHANGELOG.md  LICENSE
```

## Build order

1. **Tests first**, for `build.php`: date and timezone conversion, date-only closing dates, choice filtering, salary range, single and missing salary, remote and on-site, description cleaning, title equals description, each missing required value, closed post.
2. `mapping.php` and `build.php` until the tests pass.
3. ACF field setting, then mapping notices (tested against a fixture field group).
4. Front-end output without Rank Math. Then Rank Math free, which isn't on the local Studio site yet, so I'll ask before downloading it from wordpress.org.
5. Expiry scheduling and purge. The test checks that the cron event is scheduled for the right time and fires `litespeed_purge_post` with the right post ID. The purge itself is LiteSpeed's job.
6. List column and edit screen notice.
7. Validate the output by pasting each fixture page's source into the Rich Results Test and the Schema Markup Validator (both accept code, since they can't reach localhost).
8. Readme, update checker, uninstall, release zip.

## Salary

Vacancy sites often store salaries as free text. The plugin won't parse text, because wording such as "£24,000 to £26,000 pro rata (£30k FTE)" can't be turned into numbers reliably, and Google treats `baseSalary` as the employer's own statement of pay. Recommended: keep the text field for display, and add three fields used for the schema: **Salary minimum** (number), **Salary maximum** (number, empty for a single figure) and **Salary unit** (select with Google's codes). Without them, the plugin leaves `baseSalary` out and the posting is still valid.

For part-time pro rata roles, enter the pay the post holder will receive, not the full-time equivalent, because Google asks for the actual base salary.

## Testing

All testing happens on the local WordPress Studio site (`http://localhost:8881`), never on a live site. The plugin has to work on any site with ACF, so it doesn't target a particular one.

- **Fixtures** (`tests/fixtures/`, run with `studio wp eval-file`): register a `job_test` post type in code, and import a field group in which every field type and property the plugin supports is mapped. Then create posts covering each case: complete, remote, hybrid, salary range, single salary, no salary, closed, missing description, invalid choice value, and a description equal to the title.
- A second field group, on a second post type, that leaves Description unmapped, to test the mapping error notices.
- A third post type registered through ACF's own post type screen, to confirm both registration routes behave the same.
- **Front-end check:** a script fetches each fixture post from `localhost:8881` and compares its JSON-LD with the expected output.
- **Rank Math free and LiteSpeed Cache** aren't installed locally. I'll ask before downloading either from wordpress.org. LiteSpeed Cache only needs to be active so its purge action exists, because Studio doesn't run a LiteSpeed server.
- Studio uses SQLite. The plugin runs no SQL of its own (only ACF and WordPress functions), so it doesn't need a separate MySQL test.
