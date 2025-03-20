## Release Notes

# 11.1.9

- Improved entries locations queries
- Using Brotli instead of Gzip, with compression quality set to 9 in Apache

apache2.conf

```
<IfModule mod_brotli.c>
    BrotliCompressionQuality 9
</IfModule>
```

# 11.1.8

- Optimized archive (CSV/JSON) queries by adding indexes and enforcing their use.
- Re-introduced simdjon use.

# 11.1.7

- Removed simdjson calls for the time being due to regression bugs

# 11.1.6

- Potential improvements for CSV & JSON archive download

# 11.1.5

- Fix memory leaks in seeders scripts

# 11.1.4

- Fixed regression bugs introduced in the previous release.
- Improved semantics for better clarity and consistency.
- Made several small fixes to enhance stability and performance.

# 11.1.3

- Improved CSV/JSON archive downloads, using lazyById()
- Added user lock to archive downloads, restricting users to one project at a time.
- Refactored Entries trait to avoid calling static methods.
- Fixed branch locations query failure caused by a missing method.
- Added an index to the `branch_entries` table to improve query performance.

# 11.1.2

- Updated favicon

# 11.1.1

- Updated deletion operations so that only project creators can remove entries in bulk.
- Introduced a locking mechanism to prevent simultaneous deletion actions with clear error messages when conflicts
  occur.
- Enhanced the user interface with explicit warnings for users lacking proper deletion permissions.
- Optimized caching settings to improve overall application performance.
- Expanded automated tests to verify proper handling of concurrent deletions and role-based access restrictions.

# 11.0.1

- Added check to stop deleting single entry if project is locked

# 11.0.0

- Upgraded Laravel to version 11
- Upgraded PHP from 7.1 to 8.3
- Upgraded MySQL to 8
- Added Leave a project feature
- Added project app links & QR codes
- Fixed bugs and stability improvements
