## Release Notes

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

##     
