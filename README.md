# ACF Job Schema

**Add Google job posting markup to your vacancies, using the ACF fields you already have.**

If your WordPress site lists vacancies, Google can include them in its job search results. To do that it needs `JobPosting` structured data on each vacancy page, in a strict format. This plugin builds that data from your Advanced Custom Fields (ACF) fields. You choose which field holds the description, the closing date, the location and so on, in each field's own ACF settings. There is no separate settings page.

It also:

- converts ACF dates, employment types and salaries into the formats Google requires
- removes the markup when a job's closing date passes, because Google doesn't allow closed jobs to stay listed
- warns you in wp-admin when a post type is missing something Google requires, and shows each vacancy's status in the post list

It works with any theme. It has been tested with Divi 5.

## What you need

- WordPress 6.5 or later, PHP 7.4 or later
- **Advanced Custom Fields** 6.0 or later (free or PRO), or **Secure Custom Fields**
- A post type for your vacancies, with an ACF field group on it

## Install

1. Download `acf-job-schema.zip` from the [latest release](../../releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and activate it.
3. In ACF, open your vacancy field group. On each field you want to use, open its **General** tab and set **Job schema property**.

After that, new versions show up under **Dashboard → Updates** like any other plugin.

## Example

A field group with the location rule **Post Type is equal to Vacancy**:

| Field | ACF field type | Job schema property |
|---|---|---|
| Job description | WYSIWYG | Description |
| Closing date | Date picker | Closing date |
| Hours | Select, with choices `FULL_TIME : Full time` and `PART_TIME : Part time` | Employment type |
| Town | Text | Town or city |
| Postcode | Text | Postcode |
| Salary from | Number | Salary minimum |
| Salary to | Number | Salary maximum |
| Pay period | Select, with choices `YEAR : Per year` and `HOUR : Per hour` | Salary unit |

The job title comes from the post title and the date posted from the publish date, unless you map fields to them. The plugin's [readme](acf-job-schema/readme.md) lists every property, the choice codes Google accepts, and what happens on the site.

## Good to know

- Valid markup makes a vacancy eligible for Google's job search. Google decides whether, and when, to show it.
- Only single vacancy pages get markup, never archive or listing pages, as Google requires.
- Salary has to come from number fields. Free text such as "£24,000 to £26,000 pro rata" can't be read reliably, so keep a text field for visitors and add number fields for the markup.
- If your site uses page caching, closed jobs keep their markup in the cached page until the cache is cleared. The plugin clears LiteSpeed Cache automatically at the closing time. With another caching plugin, set cached pages to expire within a day, or clear the vacancy's cache when it closes.
- Defaults are for UK sites: salaries in pounds and addresses in the United Kingdom. Developers can change these with a filter (see the readme).

Changes in each version are in the [changelog](CHANGELOG.md).

## For developers

Filters and the closing action are listed in the [plugin readme](acf-job-schema/readme.md#for-developers). Design decisions are in [PLAN.md](PLAN.md).

### Tests

Needs PHP and Python on PATH, and a local [WordPress Studio](https://developer.wordpress.com/studio/) site with ACF active.

```
php tests/build-test.php                                  # builder only, no WordPress
AJS_SITE=<Studio site folder> bash tests/run.sh           # deploy, build fixtures, run everything
AJS_SITE=<Studio site folder> bash tests/run.sh teardown  # remove fixtures and test post types
```

`run.sh` copies the plugin into the site, adds two test post types (and registers a third through ACF), creates field groups and one post per case, then checks scheduling, notices, the admin column and each post's front-end markup.

## Licence

[MIT](LICENSE).
