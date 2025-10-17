## Release Notes

# 12.4.4

- Fixed entries deletion bug due to a numeric underflow issue in MySQL database.

# 12.4.3

- Tests added/updated to verify uploaded media endpoints are private.

# 12.4.2

- Fixed permissions of file uploads to S3, making S3 endpoints private by default.

# 12.4.1

- Added a “Sort By” control to the admin Projects list with Entries and Storage options.

# 12.4.0

- Project storage overview: total files/bytes, per-type breakdown (photo/audio/video)
- Console command to recalculate project storage on demand.
- Admin projects table shows formatted storage and loads faster
- Uploads and deletions now update project storage counters immediately.
- Project details page shows refreshed quota/media counters with refresh button.
- Extensive new and updated media upload/delete tests covering local and S3 paths.

# 12.3.8

- Fixed a bug where storage total was wrong in the admin projects panel

# 12.3.7

- New Storage page for projects (superadmins) with quota panel
- New panel on project details page showing detailed media usage (photo/audio/video)
- Project details now loads live storage statistics.
- Admin projects list shows per-project storage totals.

# 12.3.6

- Fixed a bug where local storage was incorrectly set by api media endpoints using override middleware

# 12.3.5

- Hard delete now removes project logos from both local and S3 storage.
- Deletion is transactional: projects are only removed after logo cleanup succeeds.
- S3 logo removal retries with backoff for transient errors and improved error handling.
- Hard-delete flow more reliable across storage backends.
- End-to-end tests added for hard-delete with mocked and real local and S3 storage.
- Repository review configuration and guidelines updated.

# 12.3.4

- Added a project-specific deletion modal for clearer feedback during project deletion.
- Clamped deletion progress bars to 100%.
- Improved placeholder selection and media counting consistency.
- Standardized media-related error responses.
- Unified media serving and temporary-media handling;
- Paths for media storage updated, dropped formats in file paths as generated at runtime
- Added tests for S3 upload retries and improved error handling.
- Enhanced S3 upload error handling to include retry counts and backoff information.
- Updated S3 upload tests to validate retry behavior and error responses.

# 12.3.3

- Added automatic retries with exponential backoff, better stream reopen/close handling, and retry-aware S3 error
  handling.
- Added tests covering success, retryable (429/503), non-retryable (403), and upload-failure scenarios for S3 uploads.
- Added CI/review configuration to streamline automated reviews by CodeRabbit

# 12.3.2

- Runtime generation of mobile-optimized project logos (local and S3).
- Project uploads now persist only the standard thumbnail; mobile-logo files are no longer stored.
- Removed project_mobile_logo disk/config and adjusted storage setup/scripts to use project_thumb only.
- Tests updated to validate dynamic mobile-logo generation and to mock/clean only the thumbnail storage.

# 12.3.1

- On-demand photo thumbnails generated at runtime for local and S3 requests, with placeholder fallbacks.
- Thumbnails are no longer stored; uploads save only resized originals.
- Admin tools and media counters now exclude thumbnails; totals reflect photos, audio, and video only.
- Dedicated thumbnail storage removed and related config simplified.
- Tests updated to stop creating thumbnail files and to validate runtime thumbnail generation

# 12.3.0

- Bulk media deletion endpoint and per-project media counters.
- Two-stage deletion UI with separate media and entries progress/counters.
- Confirmation text now warns about deleting associated media.
- Separate configurable chunk sizes for entries and media; clearer chunked-deletion success messaging.
- Centralized validation and lock-based error responses to prevent concurrent deletion conflicts.
- Frontend scripts and deletion flows modularized for clarity and maintainability.
- Extensive new and updated tests for media deletion, counters, chunking, storage drivers and concurrency.

# 12.2.7

- Updated logo and wrapper classes for consistency, applied circular borders, fixed sizing, and improved loader effects.
- Renamed CSS classes, unified logo wrappers, and reorganized project layouts for better maintainability.
- Fixed targeting for image loading and fade-in effects.
- Cleaned up invalid HTML attributes to enhance markup quality.

# 12.2.6

- Refactored file handling to use root paths and unified local file moving across all storage drivers.
- Removed S3 temp file support, shifting all temporary media storage and access to local disk only.
- Cleaned up unused code, including the image tools admin controller and various debug logs.
- Updated tests to align with new file path logic and removed obsolete S3-related test cases.

# 12.2.5

- Fixed regression bugs introduced in the previous release.
- Enhanced zip archive saving to ensure correct directory permissions.

# 12.2.4

- Added a utility to recursively set directory permissions, improving compatibility with newer Laravel versions.
- Introduced a service for saving audio and video files, supporting both local and S3 storage.
- Improved avatar generation to ensure proper directory creation, permissions, and image encoding.
- Updated media file handling to use the new audio/video saving service.
- Enhanced photo saving to ensure correct directory permissions.
- Cleaned up configuration and deployment files by removing unused comments and outdated writable directory entries.

# 12.2.3

- Fixed tampered created_at when performing bulk edit via csv
  uploads -> https://github.com/epicollect5/epicollect5-development-plan/issues/210
- Fixed API rate limiting tests by properly clearing rate limiter caches

# 12.2.2

- Fixed wrong permissions of folders project_thumb and project_mobile_logo (700 instead of 755)

# 12.2.1

- New photo not synced placeholder when a photo is not synced yet

# 12.2.0

- Added S3 for media storage
- Added tests for S3 storage

# 12.1.2

- Refactored validation classes
- Improved tests for validation classes

# 12.1.1

- Fixed regression bugs introduced in the previous release.
- Fix audio and video not downloading via the
  browser https://github.com/epicollect5/epicollect5-development-plan/issues/200
- Improved tests

# 12.1.0

- Fixed regression bugs introduced in the previous release.
- Fixed too many titles bug https://github.com/epicollect5/epicollect5-development-plan/issues/199
- Added config to restrict auth to specific email domains.
- All auth methods now enforce domain whitelisting.
- Admin page shows system email and allowed domains (read-only).
- Improved errors and redirects for disallowed domains.

# 12.0.1

- Fixed regression bugs introduced in the previous release.
- Archive downloads now correctly filters entries based on user role i.e. collectors can only download their own
  entries.

# 12.0.0

- Upgraded to Laravel 12
- Enhanced image processing with configurable quality settings.
- Bug Fixes: branch entry deletion bug, min max data editor validation bug
- Ensured consistent type casting of environment variables in configuration files.
- Adjusted rate limiting to permit higher request volumes during testing.
- Improved test reliability by clearing rate limiter caches and using dynamic test data.
- Updated image-related tests to match new image processing methods.
- Introduced delays in tests to prevent race conditions.
- Removed unused variables and enhanced exception handling in tests.

# 11.1.23

- Improved integer and decimal min and max tests
- Removed Data Editor front end validation

# 11.1.22

- Show better error when api return 403 forbidden response (Dataviewer 0.1.1)
- Improved download data modal message (Dataviewer 0.1.1)

# 11.1.21

- Improved logging

# 11.1.20

- Introduced configurable delays for API responses in OAuth token issuance, project export, entries export, and media
  download endpoints.
- Added new configuration options to control API response delay durations via environment variables.

# 11.1.19

- Formbuilder: Added a modal dialog to confirm question deletion, including warnings about data loss and links to
  further documentation.
- Formbuilder: Enhanced the screen resolution warning with detailed requirements and a "Learn More" link.
- Updated JSON-LD structured data in the header to use secure HTTPS URLs.

# 11.1.18

- Added compression to tables with json columns (for new installations)

# 11.1.17

- Fixed log defaulting to bedbug instead of error
- Lowered oauth token limit to 10 per hour

# 11.1.16

- Added tests for account deletion

# 11.1.15

- Improved admin page (users, projects, entries) queries

# 11.1.14

- Added tests for from archive CSV/JSON downloads when different timeframes are used

# 11.1.13

- Fixed regression bugs introduced in the previous release.
- Removed sorting from archive CSV/JSON downloads for performance, using only filtering

# 11.1.12

- Fixes regression bugs introduced in the previous release.
- Re-added sorting and filtering for archive CSV/JSON downloads

# 11.1.11

- Fixed BOM for Excel UTF-8 compatibility
- Using fputcsv() directly for CSV archive downloads, no buffer.

# 11.1.10

- Using lazyByIdDesc() for CSV/JSON archive downloads

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
