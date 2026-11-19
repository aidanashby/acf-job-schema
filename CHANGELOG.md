# Changelog

## [0.1.0] - 2026-11-19

### Added
- **Job schema property** setting on ACF fields, offering only the properties that suit each field type.
- Google `JobPosting` markup on single posts of any post type whose field group maps a Description and a location.
- Conversion of ACF dates (site timezone, date-only closing dates end at 23:59:59), employment types, salaries and addresses into Google's formats.
- Markup removed after the closing date, with a LiteSpeed Cache purge scheduled for that time.
- Rank Math support: the job joins Rank Math's structured data, and is left out if Rank Math already has a Job Posting for the post.
- Notices on the field group and post list screens when a post type's mapping is missing something Google requires, a Job schema column, and an edit screen notice listing missing values.
- Updates from GitHub releases.
