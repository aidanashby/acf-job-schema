# ACF Job Schema

Adds Google job posting markup (`JobPosting` structured data) to your vacancies, built from your ACF fields. Google can then list the vacancies in its job search results.

## What you need

- WordPress 6.5 or later, PHP 7.4 or later
- **Advanced Custom Fields** 6.0 or later (free or PRO), or **Secure Custom Fields**

## Set up

1. Create a field group for your vacancy post type in ACF. Its location rule, for example **Post Type is equal to Vacancy**, decides which posts get job markup.
2. Open each field you want to use, and on its **General** tab set **Job schema property**. Each field only offers the properties that suit its type.
3. Save the field group. If something Google requires isn't mapped, a notice on the field group screen and on the post list says what to add.

| Property | Field types | If you don't map it |
|---|---|---|
| Job title | Text | The post title |
| Description (required) | WYSIWYG, Text area | No markup |
| Date posted | Date picker, Date time picker | The publish date |
| Closing date | Date picker, Date time picker | No automatic removal when the job closes |
| Employment type | Select, Radio, Checkbox, Button group | Left out |
| Salary minimum, Salary maximum | Number | Salary left out |
| Salary unit | Select, Radio, Button group | Salary left out |
| Fully remote | True / false | The job is treated as on-site |
| Street address, Town or city, County or region, Postcode | Text | That part is left out |
| Country | Text, Select | United Kingdom (`GB`) |
| Map location | Google Map | Fills any address part you haven't mapped |
| Job reference | Text, Number | Left out |

On-site jobs need a **Town or city** or a **Postcode**. Fully remote jobs don't need an address. Fields inside a Group field can be mapped. Repeater and flexible content subfields can't.

### Choice values

For **Employment type** and **Salary unit**, each choice's *value* must be one of Google's codes. Use ACF's `value : label` format, so people still see a readable label:

```
FULL_TIME : Full time
PART_TIME : Part time
TEMPORARY : Temporary
```

Employment type codes: `FULL_TIME`, `PART_TIME`, `CONTRACTOR`, `TEMPORARY`, `INTERN`, `VOLUNTEER`, `PER_DIEM`, `OTHER`.
Salary unit codes: `HOUR`, `DAY`, `WEEK`, `MONTH`, `YEAR`.

### Salary

Salary needs number fields. Free text such as "£24,000 to £26,000 pro rata" can't be read reliably, so keep a text field for what visitors see and add **Salary minimum**, **Salary maximum** (leave it empty for a single figure) and **Salary unit** for the markup. For pro rata roles, enter the pay the post holder will actually receive, because Google asks for the real base salary.

### Description

Use a WYSIWYG or text area field, and show it on the page (in Divi, through a Theme Builder template with dynamic content). Google requires the description to be visible on the page, and to be different from the title. Only paragraphs, lists and line breaks are kept.

## What happens on the site

- The markup is added to single vacancy pages only, never to archive or listing pages.
- A vacancy with something required left empty gets no markup. The **Job schema** column on the post list shows each vacancy's status, and the edit screen says what's missing.
- After the closing date, the markup is removed. If LiteSpeed Cache is active, the page's cached copy is purged at the closing time, so the change reaches Google. The purge runs on the first visit after the closing time (WP-Cron), unless your host runs WP-Cron on a schedule. With another caching plugin, set cached pages to expire within a day, or clear the vacancy's cache when it closes.
- Valid markup makes a vacancy eligible for Google's job search, but Google decides whether to show it. Each published vacancy's edit screen links to Google's Rich Results Test.
- With Rank Math active, the job is added to Rank Math's structured data, so the page has one set. If a vacancy also has a Job Posting schema added in Rank Math itself, this plugin leaves its markup out and the edit screen warns you.
- The hiring organisation is Rank Math's Knowledge Graph name and logo if set, otherwise the site title and logo.

## For developers

Filters:

- `acf_job_schema_defaults`: the timezone, `now`, organisation, `currency` (`GBP`), `default_country` (`GB`) and `remote_country` (`United Kingdom`).
- `acf_job_schema_organization`: the hiring organisation's `name`, `url` and `logo`.
- `acf_job_schema_data`: the final JobPosting array for a post (`$schema`, `$post_id`). Return an empty value to leave it out.

Action: `acf_job_schema_closed` (`$post_id`) fires when a job's closing time passes.

## Uninstall

Deleting the plugin removes its scheduled events and update data. The **Job schema property** settings stay saved in your ACF fields, where they do nothing without the plugin.
