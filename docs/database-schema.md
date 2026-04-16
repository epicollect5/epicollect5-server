# Database Schema

This document describes the current application schema derived from the migration history in `database/migrations`.

Scope:
- It reflects the latest migrated structure, not historical intermediate states.
- It is based on the migrations in this repository as of 2026-04-16.
- JSON shape migrations are mentioned only where they clarify persisted columns.

## Current Tables

### `users`

Purpose: application users and server-level roles.

Columns:
- `id`: `INT`, primary key, auto increment
- `name`: `VARCHAR(100)`, not null
- `last_name`: `VARCHAR(100)`, not null
- `email`: `VARCHAR(255)`, not null
- `password`: `VARCHAR(255)`, not null
- `avatar`: `VARCHAR(200)`, not null
- `remember_token`: `VARCHAR(200)`, not null
- `server_role`: `ENUM('basic','admin','superadmin')`, default `basic`
- `created_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `updated_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `state`: `ENUM('active','disabled','unverified','archived')`, default `unverified`
- `api_token`: `VARCHAR(255)`, default empty string

Indexes:
- Primary key on `id`
- Unique index `email` on `email`

Notes:
- The old `provider` column was dropped when `users_providers` was introduced.

### `users_providers`

Purpose: external or local authentication providers linked to users.

Columns:
- `id`: `INT`, primary key, auto increment
- `user_id`: `INT`, not null
- `email`: `VARCHAR(255)`, not null
- `provider`: `VARCHAR(200)`, not null
- `created_at`: `TIMESTAMP`, default current timestamp
- `updated_at`: `TIMESTAMP`, default current timestamp, auto-updated by DB

Indexes:
- Primary key on `id`
- Unique composite index on `email`, `provider`

Foreign keys:
- `user_id -> users.id` with `ON DELETE CASCADE`

### `users_passwordless_web`

Purpose: passwordless web login tokens.

Columns:
- `id`: `INT`, primary key, auto increment
- `email`: `VARCHAR(255)`, not null
- `token`: `VARCHAR(500)`, not null
- `created_at`: `TIMESTAMP`, default current timestamp
- `expires_at`: `TIMESTAMP`, default current timestamp
- `attempts`: `TINYINT`, default `3`

Indexes:
- Primary key on `id`
- Unique index `email` on `email`

### `users_passwordless_api`

Purpose: passwordless API login codes.

Columns:
- `id`: `INT`, primary key, auto increment
- `email`: `VARCHAR(255)`, not null
- `code`: `VARCHAR(500)`, not null
- `created_at`: `TIMESTAMP`, default current timestamp
- `expires_at`: `TIMESTAMP`, default current timestamp
- `attempts`: `TINYINT`, default `3`

Indexes:
- Primary key on `id`
- Unique index `email` on `email`

### `projects`

Purpose: project metadata, ownership reference, visibility, and access controls.

Columns:
- `id`: `INT`, primary key, auto increment
- `name`: `VARCHAR(50)`, not null
- `slug`: `VARCHAR(50)`, not null
- `ref`: `VARCHAR(100)`, not null
- `description`: `TEXT`, not null
- `small_description`: `TEXT`, not null
- `access`: `ENUM('public','private')`, default `public`
- `visibility`: `ENUM('listed','hidden')`, default `listed`
- `category`: `VARCHAR(100)`, default `general`
- `created_by`: `INT`, indexed, no current foreign key
- `created_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `updated_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `status`: `ENUM('active','trashed','locked','archived')`, default `active`
- `can_bulk_upload`: `ENUM('nobody','members','everybody')`, default `nobody`
- `app_link_visibility`: `ENUM('shown','hidden')`, default `hidden`

Indexes:
- Primary key on `id`
- Unique index `slug_UNIQUE` on `slug`
- Non-unique index `fk_projects_user_id` on `created_by`

Notes:
- `logo_url` was dropped in 2026.
- The original foreign key from `created_by` to `users.id` was removed in 2023 and not restored.

### `project_roles`

Purpose: per-project membership and role assignment.

Columns:
- `id`: `INT`, primary key, auto increment
- `project_id`: `INT`, indexed
- `user_id`: `INT`, indexed
- `role`: `ENUM('creator','manager','curator','collector','viewer')`, default `collector`

Indexes:
- Primary key on `id`
- Index `fk_project_roles_project_id` on `project_id`
- Index `fk_project_roles_user_id` on `user_id`

Foreign keys:
- `project_id -> projects.id` with `ON DELETE CASCADE`
- `user_id -> users.id` with `ON DELETE CASCADE`

### `project_structures`

Purpose: stored project definition JSON, derived project extra JSON, and mapping JSON.

Columns:
- `id`: `INT`, primary key, auto increment
- `project_id`: `INT`, indexed
- `project_definition`: `JSON`, nullable
- `project_extra`: `JSON`, nullable
- `project_mapping`: `JSON`, nullable
- `updated_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`

Indexes:
- Primary key on `id`
- Index `fk_project_structures_project_id` on `project_id`

Foreign keys:
- `project_id -> projects.id` with `ON DELETE CASCADE`

Notes:
- `project_forms_extra` existed temporarily and was dropped in 2017.
- The original migration sets compressed row format for this table.

### `project_stats`

Purpose: per-project counters for entries and storage usage.

Columns:
- `id`: `INT`, primary key, auto increment
- `project_id`: `INT`, indexed
- `total_entries`: `INT`, not null
- `total_files`: `BIGINT`, signed, default `0`
- `total_bytes`: `BIGINT`, signed, default `0`
- `total_bytes_updated_at`: `TIMESTAMP`, nullable
- `video_files`: `BIGINT`, signed, default `0`
- `photo_files`: `BIGINT`, signed, default `0`
- `photo_bytes`: `BIGINT`, signed, default `0`
- `audio_files`: `BIGINT`, signed, default `0`
- `audio_bytes`: `BIGINT`, signed, default `0`
- `video_bytes`: `BIGINT`, signed, default `0`
- `form_counts`: `JSON`, nullable
- `branch_counts`: `JSON`, nullable

Indexes:
- Primary key on `id`
- Index `fk_project_stats_project_id` on `project_id`
- Index `idx_project_stats_total_entries` on `total_entries`
- Composite index `idx_project_stats_project_total_bytes` on `project_id`, `total_bytes`

Foreign keys:
- `project_id -> projects.id` with `ON DELETE CASCADE`

Notes:
- `total_users` was dropped in 2025.
- Storage counters were added in 2025 and later changed from unsigned to signed `BIGINT`.

### `projects_featured`

Purpose: featured-project list.

Columns:
- `id`: `INT`, primary key, auto increment
- `project_id`: `INT`, indexed
- `created_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `updated_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`

Indexes:
- Primary key on `id`
- Index `fk_projects_featured_project_id` on `project_id`

Foreign keys:
- `project_id -> projects.id` with `ON DELETE CASCADE`

### `entries`

Purpose: root form submissions.

Columns:
- `id`: `INT`, primary key, auto increment
- `project_id`: `INT`, indexed, no current foreign key
- `uuid`: `VARCHAR(255)`, not null
- `parent_uuid`: `VARCHAR(200)`, not null
- `form_ref`: `VARCHAR(200)`, default empty string
- `parent_form_ref`: `VARCHAR(200)`, default empty string
- `user_id`: `INT`, nullable
- `platform`: `VARCHAR(255)`, default empty string
- `device_id`: `VARCHAR(255)`, default empty string
- `created_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `uploaded_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `title`: `LONGTEXT`, not null
- `entry_data`: `JSON`, nullable
- `geo_json_data`: `JSON`, nullable
- `child_counts`: `INT`, default `0`
- `branch_counts`: `JSON`, nullable

Indexes:
- Primary key on `id`
- Unique index `uuid` on `uuid`
- Index `fk_entries_project_id` on `project_id`
- Composite index `entries_search` on `project_id`, `form_ref`, `created_at`
- Composite index `idx_entries_project_form_ref_id` on `project_id`, `form_ref`, `id`

Notes:
- The foreign key from `project_id` to `projects.id` was dropped in 2023.
- The original migration sets compressed row format for this table.

### `branch_entries`

Purpose: branch form submissions owned by an entry.

Columns:
- `id`: `INT`, primary key, auto increment
- `project_id`: `INT`, indexed, no current foreign key
- `uuid`: `VARCHAR(255)`, not null
- `owner_entry_id`: `INT`, indexed
- `owner_uuid`: `VARCHAR(200)`, default empty string
- `owner_input_ref`: `VARCHAR(200)`, not null
- `form_ref`: `VARCHAR(200)`, default empty string
- `user_id`: `INT`, nullable
- `platform`: `VARCHAR(255)`, default empty string
- `device_id`: `VARCHAR(255)`, default empty string
- `created_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `uploaded_at`: `TIMESTAMP(3)`, default `CURRENT_TIMESTAMP(3)`
- `title`: `LONGTEXT`, not null
- `entry_data`: `JSON`, nullable
- `geo_json_data`: `JSON`, nullable

Indexes:
- Primary key on `id`
- Unique index `uuid` on `uuid`
- Index `fk_branch_entries_project_id` on `project_id`
- Index `fk_branch_entries_entries_id` on `owner_entry_id`
- Composite index `branch_entries_search` on `project_id`, `owner_input_ref`, `created_at`
- Composite index `branch_entries_optimized_search` on `project_id`, `form_ref`, `owner_input_ref`
- Composite index `idx_branch_entries_project_form_ref_id` on `project_id`, `form_ref`, `id`

Foreign keys:
- `owner_entry_id -> entries.id` with `ON DELETE CASCADE`

Notes:
- The foreign key from `project_id` to `projects.id` was dropped in 2023.
- The original migration sets compressed row format for this table.

### `system_stats`

Purpose: point-in-time aggregate system snapshots.

Columns:
- `id`: `INT`, primary key, auto increment
- `user_stats`: `JSON`, nullable
- `project_stats`: `JSON`, nullable
- `entries_stats`: `JSON`, nullable
- `branch_entries_stats`: `JSON`, nullable
- `created_at`: `TIMESTAMP`, default current timestamp

Indexes:
- Primary key on `id`

### `oauth_clients`

Purpose: Laravel Passport OAuth clients.

Columns:
- `id`: `BIGINT UNSIGNED`, primary key, auto increment
- `user_id`: `BIGINT UNSIGNED`, nullable, indexed
- `name`: `VARCHAR(255)`, not null
- `secret`: `VARCHAR(100)`, nullable
- `provider`: `VARCHAR(255)`, nullable
- `redirect`: `TEXT`, not null
- `personal_access_client`: `BOOLEAN`, not null
- `password_client`: `BOOLEAN`, not null
- `revoked`: `BOOLEAN`, not null
- `created_at`: `TIMESTAMP`, nullable Laravel timestamp
- `updated_at`: `TIMESTAMP`, nullable Laravel timestamp

Indexes:
- Primary key on `id`
- Index on `user_id`

### `oauth_access_tokens`

Purpose: Passport access tokens.

Columns:
- `id`: `VARCHAR(100)`, primary key
- `user_id`: `BIGINT UNSIGNED`, nullable, indexed
- `client_id`: `BIGINT UNSIGNED`, not null
- `name`: `VARCHAR(255)`, nullable
- `scopes`: `TEXT`, nullable
- `revoked`: `BOOLEAN`, not null
- `created_at`: `TIMESTAMP`, nullable Laravel timestamp
- `updated_at`: `TIMESTAMP`, nullable Laravel timestamp
- `expires_at`: `DATETIME`, nullable

Indexes:
- Primary key on `id`
- Index on `user_id`

### `oauth_auth_codes`

Purpose: Passport authorization codes.

Columns:
- `id`: `VARCHAR(100)`, primary key
- `user_id`: `BIGINT UNSIGNED`, indexed
- `client_id`: `BIGINT UNSIGNED`, not null
- `scopes`: `TEXT`, nullable
- `revoked`: `BOOLEAN`, not null
- `expires_at`: `DATETIME`, nullable

Indexes:
- Primary key on `id`
- Index on `user_id`

### `oauth_refresh_tokens`

Purpose: Passport refresh tokens.

Columns:
- `id`: `VARCHAR(100)`, primary key
- `access_token_id`: `VARCHAR(100)`, indexed
- `revoked`: `BOOLEAN`, not null
- `expires_at`: `DATETIME`, nullable

Indexes:
- Primary key on `id`
- Index on `access_token_id`

### `oauth_personal_access_clients`

Purpose: Passport personal access client registry.

Columns:
- `id`: `BIGINT UNSIGNED`, primary key, auto increment
- `client_id`: `BIGINT UNSIGNED`, not null
- `created_at`: `TIMESTAMP`, nullable Laravel timestamp
- `updated_at`: `TIMESTAMP`, nullable Laravel timestamp

Indexes:
- Primary key on `id`

### `oauth_client_projects`

Purpose: links OAuth clients to projects.

Columns:
- `id`: `INT`, primary key, auto increment
- `project_id`: `INT`, indexed
- `client_id`: `INT`, not null
- `created_at`: `TIMESTAMP`, default current timestamp
- `updated_at`: `TIMESTAMP`, default current timestamp

Indexes:
- Primary key on `id`
- Index `fk_oauth_client_projects_project_id` on `project_id`

Foreign keys:
- `project_id -> projects.id` with `ON DELETE CASCADE`

Notes:
- There is no migration-defined foreign key from `client_id` to `oauth_clients.id`.

## Removed Tables

These tables existed historically but are not part of the current schema after later migrations:

- `entries_history`
- `branch_entries_history`
- `entries_archive`
- `branch_entries_archive`
- `storage_stats`
- `users_verify`
- `users_reset_password`

## Current Relationship Summary

Active foreign keys still defined by migrations:

- `project_roles.project_id -> projects.id`
- `project_roles.user_id -> users.id`
- `project_structures.project_id -> projects.id`
- `project_stats.project_id -> projects.id`
- `projects_featured.project_id -> projects.id`
- `branch_entries.owner_entry_id -> entries.id`
- `users_providers.user_id -> users.id`
- `oauth_client_projects.project_id -> projects.id`

Important non-FK references:

- `projects.created_by` is indexed but no longer constrained to `users.id`
- `entries.project_id` is indexed but no longer constrained to `projects.id`
- `branch_entries.project_id` is indexed but no longer constrained to `projects.id`
- `entries.user_id` and `branch_entries.user_id` have no migration-defined foreign keys
- `oauth_*` Passport tables do not define foreign keys in these migrations
