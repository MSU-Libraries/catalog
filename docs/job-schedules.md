# Job Schedules

This page lists the current schedule for all the jobs that run on a regular
basis.

<!-- markdownlint-disable MD013 -->
## All Cron Jobs

| Environment         | Container        | Job                        | Time                                   |
| ------------------- | ---------------- | -------------------------- | -------------------------------------- |
| prod                | Solr Cron        | Alphabrowse Rebuild        | 1:15AM every day (build on node `1`)   |
| beta                | Solr Cron        | Alphabrowse Rebuild        | 2:15AM every day (build on node `2`)   |
| preview             | Solr Cron        | Alphabrowse Rebuild        | 3:15AM every day (build on node `3`)   |
| prod                | VuFind Cron      | FOLIO harvest & Import     | 3AM - 11PM @ :00, :15, :30, and :45    |
| beta                | VuFind Cron      | FOLIO harvest & Import     | 3AM - 11PM @ :15                       |
| preview             | VuFind Cron      | FOLIO harvest & Import     | 3AM - 11PM @ :45                       |
| prod                | VuFind Cron      | HLM harvest & Import       | 2:30AM every day                       |
| beta                | VuFind Cron      | HLM harvest & Import       | 2:15AM every day                       |
| preview             | VuFind Cron      | HLM harvest & Import       | 2:45AM every day                       |
| prod                | VuFind Cron      | Authority harvest & Import | 4:30AM every day                       |
| beta                | VuFind Cron      | Authority harvest & Import | 4:15AM every day                       |
| preview             | VuFind Cron      | Authority harvest & Import | 4:45AM every day                       |
| prod                | VuFind Cron      | Course Reserves Import     | Every hour @ :10                       |
| beta                | VuFind Cron      | Course Reserves Import     | Every hour @ :20                       |
| preview             | VuFind Cron      | Course Reserves Import     | Every hour @ :50                       |
| all                 | VuFind Cron      | Clear old VuFind searches  | 12:00AM every day                      |
| all                 | VuFind Cron      | Clear old VuFind sessions  | 12:15AM, 6:15AM, 6:15PM every day      |
| prod, beta, preview | VuFind Cron      | Clear VuFind cache         | At container start                     |
| devel-\*, review-\* | VuFind CacheCron | Clear VuFind cache         | At container start and every 5 minutes |

## Which node the VuFind Cron container lives on for each environment

| Environment        | VuFind Cron Node |
| ------------------ | ---------------- |
| prod               | `1`              |
| beta               | `2`              |
| preview            | `3`              |
| devel-\*, review\* |  `-`             |

## Which node the Solr Cron container lives on for each environment

| Environment        | Solr Cron Node |
| ------------------ | -------------- |
| prod               | `1`, `2`, `3`  |
| beta               | `1`, `2`, `3`  |
| preview            | `1`, `2`, `3`  |
| devel-\*, review\* | `1`, `2`, `3`  |
