---
extends: _layouts.post
section: content
title: "BigQuery: Convert timestamp/date/datetime to different timezone"
subheading: ""
h1: "How to convert a timestamp/date/datetime to a different timezone in Google BigQuery"
description: "Converting a timestamp/date/datetime to a different timezone with Google BigQuery"
author: "Pascal Landau"
published_at: "2018-04-08 18:00:00"
category: "development"
slug: "convert-timestamp-date-datetime-to-different-timezone"
---

BigQuery provides multiple functions to convert timestamps / dates / datetimes to a different timezone:
- [DATE(timestamp_expression, timezone)](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators#date)
- [TIME(timestamp, timezone)](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators#time)
- [DATETIME(timestamp_expression, timezone)](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators#datetime)

According to the [docu](https://cloud.google.com/bigquery/docs/reference/standard-sql/data-types#time-zones) the `timezone` 
can be provided as UTC-offset (e.g. `+02:00`) or timezone name (e.g. `Europe/Berlin`). See this 
[list of IANA timezone offsets and names](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones).

The converted dates/times can than be formatted with via 
- [FORMAT_DATE(format_string, date_expression)](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators#format_date)
- [FORMAT_TIME(format_string, time_expression)](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators#format_time)
- [FORMAT_DATETIME(format_string, datetime_expression)](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators#format_datetime)

## Code
````
SELECT
  DATETIME(timestamp, "Europe/Berlin") as datetime_berlin,
  DATE(timestamp, "Europe/Berlin") as date_berlin,
  TIME(timestamp, "Europe/Berlin") as time_berlin,
  FORMAT_DATETIME("%c", DATETIME(timestamp, "Europe/Berlin")) as formatted_date_time_berlin
FROM
  table
````

## Working Example

<script src="https://gist.github.com/paslandau/b40d8e265884ce2c19b966e52fbf72b9.js"></script>

## Run on BigQuery
[Open in BigQuery Console](https://bigquery.cloud.google.com/savedquery/106862046541:12050165b14e437387aa63757ae7d60c)

[![BigQuery Console: Convert timestamp to different timezone example](/img/bigquery-snippets/convert-timestamp-date-datetime-to-different-timezone/convert-timestamp-date-datetime-to-different-timezone-bigquery-example.png "BigQuery Console: Convert timestamp to different timezone")](/img/bigquery-snippets/convert-timestamp-date-datetime-to-different-timezone/convert-timestamp-date-datetime-to-different-timezone-bigquery-example.png)
  
## Links
- [Gist on Github](https://gist.github.com/paslandau/b40d8e265884ce2c19b966e52fbf72b9)
- [Example on BigQuery](https://bigquery.cloud.google.com/savedquery/106862046541:12050165b14e437387aa63757ae7d60c)
- [Answer to "BigQuery converting to a different timezone" on Stackoverflow](https://stackoverflow.com/a/43349229/413531)

## Use cases
BigQuery displays data usually in UTC. That leads to problems when using date formatting functions because
dates and times can be off. Converting the datetimes prior formatting into the correct timezone solves those issues.

Common formats:
````
FORMAT_DATETIME("%c", DATETIME(timestamp, "Europe/Berlin")) # %Y-%m-%d %H:%M:%S => 2018-04-08 18:28:01
FORMAT_DATE("%F", DATETIME(timestamp, "Europe/Berlin"))     # %Y-%m-%d          => 2018-04-08
FORMAT_DATE("%V", DATETIME(timestamp, "Europe/Berlin"))     # calendar week     => 14
FORMAT_TIME("%T", DATETIME(timestamp, "Europe/Berlin"))     #          %H:%M:%S => 18:28:01
```