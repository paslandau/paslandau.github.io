---
extends: _layouts.post
section: content
title: "BigQuery: Extract URL parameters as ARRAY"
subheading: ""
h1: "How to extract URL parameters as ARRAY in Google BigQuery"
description: "Extracting parameters in the query of a URL as ARRAY with Google BigQuery"
author: "Pascal Landau"
published_at: "2018-04-08 12:00:00"
category: "development"
slug: "extract-url-parameters-array"
---

We're gonna use the [REGEXP_EXTRACT_ALL](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators?hl=de#regexp_extract_all) 
function provided in the Standard SQL dialect of BigQuery
to extract parameters from the query part of a URL and return them as an ARRAY.

## Code
```
#standardSQL
SELECT
  REGEXP_EXTRACT_ALL(query,r'(?:\?|&)((?:[^=]+)=(?:[^&]*))') as params,
  REGEXP_EXTRACT_ALL(query,r'(?:\?|&)(?:([^=]+)=(?:[^&]*))') as keys,
  REGEXP_EXTRACT_ALL(query,r'(?:\?|&)(?:(?:[^=]+)=([^&]*))') as values
FROM
  table
```

## Working Example

<script src="https://gist.github.com/paslandau/6c46020211a00c39607d5eab1d093f3a.js"></script>

### Result
|Row|id|query|params|keys|values|description|
|--- |--- |--- |--- |--- |--- |--- |
|1|1|?foo=bar|foo=bar|foo|bar|simple|
|2|2|?foo=bar&bar=baz|foo=bar|foo|bar|multiple params|
| | | |bar=baz|bar|baz| |
|3|3|?foo[]=bar&foo[]=baz|foo[]=bar|foo[]|bar|arrays|
| | | |foo[]=baz|foo[]|baz| |
|4|4| | | | |no query|

## Run on BigQuery
[Open in BigQuery Console](https://bigquery.cloud.google.com/savedquery/106862046541:e5da849d652a4502b12443a2f14b355a)

[![BigQuery Console: Extract URL parameters example](/img/bigquery-snippets/extract-url-parameters-array/extract-url-parameters-array-bigquery-example.png "BigQuery Console: Extract URL parameters example")](/img/bigquery-snippets/extract-url-parameters-array/extract-url-parameters-array-bigquery-example.png)

## Notes
- `REGEXP_EXTRACT_ALL` only excepts 1 capturing group, hence we need to mark all other groups 
  as non-capturing with `(?:`
- if the URL contains a fragment part (e.g. https://example.org/?foo=bar#baz), the fragment is currently not removed.
  To do so, remove the fragment prior to extraction with 
  [REGEXP_REPLACE](https://cloud.google.com/bigquery/docs/reference/standard-sql/functions-and-operators?hl=de#regexp_replace), 
  e.g. like so:
  ```
  REGEXP_EXTRACT_ALL(
    REGEXP_EXTRACT(query, r'#.*', ''),
  r'(?:\?|&)(?:(?:[^=]+)=([^&]*))') as values
  ``` 
  
## Links
- [Gist on Github](https://gist.github.com/paslandau/6c46020211a00c39607d5eab1d093f3a)
- [Example on BigQuery](https://bigquery.cloud.google.com/savedquery/106862046541:e5da849d652a4502b12443a2f14b355a)
- [REGEX explanation on Regex101](https://regex101.com/r/iqwgxD/1/)

## Use cases
- compile a list of all parameters from your log files
- evaluate the frequency of parameters keys/values