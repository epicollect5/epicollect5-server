# Application Architecture

This document describes the current server-side architecture of Epicollect5 as implemented in this repository.

It is based on the code layout and runtime wiring in `app/`, `routes/`, `config/epicollect/`, and the migration-defined schema.

## Overview

Epicollect5 Server is a Laravel application with a domain-centered structure built around projects, entries, media, and users.

At a high level:
- `projects` define the form structure, project settings, mappings, and access rules
- `entries` and `branch_entries` store submitted data and geospatial payloads
- `project_structures` stores the canonical JSON definition and derived JSON metadata
- `project_stats` stores cached counters and storage totals
- controllers stay relatively thin and delegate business logic to services
- DTOs are used heavily to move structured project and entry state across layers

The application namespace is `ec5\\`, not Laravel's default `App\\`.

## Delivery Surfaces

The application exposes three main entry points.

### Web routes

Defined in `routes/web.php`.

Purpose:
- browser-rendered pages
- login and account management
- project creation and management
- admin tools and admin dashboards

Guard and middleware:
- loaded by `RouteServiceProvider` with middleware group `web`
- uses the `web` guard and session-backed authentication

### Internal API

Defined in `routes/api_internal.php`.

Purpose:
- endpoints used by the web frontend and dataviewer
- project management operations
- entry listing, deletion, download, bulk upload, mapping, counters

Guard and middleware:
- loaded with middleware group `api_internal`
- still uses the `web` guard so it shares the same session/auth context as the web app

### External API

Defined in `routes/api_external.php`.

Purpose:
- mobile app access
- public and private project download
- entry upload
- documented export endpoints
- OAuth token issuance
- passwordless and third-party login flows

Guard and middleware:
- loaded with middleware group `api_external`
- uses the `api_external` guard

## Architectural Layers

### 1. Routing and middleware

The first layer is route grouping plus middleware in [Kernel.php](/Users/mmenegazzo/Sites/epicollect5-server/app/Http/Kernel.php).

Important middleware responsibilities:
- authentication and guest redirection
- admin and superadmin checks
- project permission checks
- bulk upload gating
- rate limiting
- optional test disk overrides

The project permission middleware family is central to the architecture:
- `ProjectPermissions`
- `ProjectPermissionsApi`
- `ProjectPermissionsRequiredRole`
- `ProjectPermissionsViewerRole`
- `ProjectPermissionsBulkUpload`

These middleware do more than allow or deny access. They build request context and make it available to downstream code.

### 2. Request context

The request-scoped context is exposed through `ec5\Traits\Requests\RequestAttributes`.

Controllers and services using this trait can access:
- `requestedUser()`
- `requestedProject()`
- `requestedProjectRole()`

This is a core design choice in the codebase:
- route middleware resolves the current project and user role
- downstream services rely on request attributes instead of repeatedly re-querying or recomputing access context

This keeps controllers smaller and standardizes permission-aware behavior across the application.

### 3. Controllers

Controllers live under:
- `app/Http/Controllers/Web`
- `app/Http/Controllers/Api`

Controller responsibilities are usually:
- accept request input
- invoke validators
- call services or DTO workflows
- return standardized responses

Representative patterns:
- project endpoints use `ProjectController`
- upload endpoints use `UploadAppController` or `UploadWebController`
- dataviewer and export endpoints use entries view/download controllers

The preferred shape is:
- middleware establishes context
- controller coordinates
- service performs business logic

### 4. Validation layer

Validation is not limited to Laravel form requests. A substantial custom validation layer lives under `app/Http/Validation`.

Main areas:
- `Auth`
- `Entries`
- `Project`
- `Media`
- `Schemas`

These validators enforce domain rules such as:
- project definition validity
- upload payload structure
- query-string filters and export parameters
- input-specific answer validation
- uniqueness and media constraints

This layer is important because many rules depend on project structure JSON, user role, and entry type rather than simple field constraints.

### 5. DTO layer

DTOs in `app/DTO` are a major part of the architecture.

Main DTOs:
- `ProjectDTO`
- `ProjectDefinitionDTO`
- `ProjectExtraDTO`
- `ProjectMappingDTO`
- `ProjectStatsDTO`
- `ProjectRoleDTO`
- `EntryStructureDTO`

Responsibilities:
- hold structured project or entry state
- normalize JSON stored in the database
- support create, import, clone, and hydrate workflows
- provide a stable interface to services and validators

The project stack is especially DTO-driven:
- project rows and structure rows are loaded
- `Project::findBySlug()` returns a joined record bundle
- `ProjectDTO::initAllDTOs()` hydrates project details, definition, extra, mapping, and stats into one domain object

This lets higher layers work with a rich in-memory project object instead of raw JSON blobs and loosely typed arrays.

### 6. Service layer

Business logic is concentrated in `app/Services`.

Main service groups:
- `Services/Project`
- `Services/Entries`
- `Services/Media`
- `Services/Mapping`
- `Services/System`
- `Services/User`

Examples:
- `ProjectService` stores projects, manages roles, and supports cloning workflows
- `EntriesUploadService` validates upload requests, checks permissions and versioning, builds `EntryStructureDTO`, and dispatches persistence/media actions
- `CreateEntryService` writes entries in a transaction and updates counters
- `EntriesViewService` sanitizes and validates query parameters for downloads and dataviewer requests
- `MediaService` and related media saver/mover services handle local or S3 media storage concerns

The general rule in this codebase is:
- services own the business transaction
- controllers should not contain multi-step domain logic

### 7. Models and traits

Eloquent models live under `app/Models`, grouped by domain:
- `Project`
- `Entries`
- `User`
- `OAuth`
- `System`
- `Counters`

Notable design traits:
- models mix Eloquent usage with query-builder-heavy methods
- shared persistence behavior is extracted into traits, especially under `app/Traits/Eloquent`
- counters are modeled explicitly through `EntryCounter` and `BranchEntryCounter`

This codebase does not follow a pure active-record style. Many read and write paths use:
- Eloquent models for identity and simple persistence
- query builder for performance-sensitive or highly custom queries
- traits to share entry-specific DB operations

## Core Domain Model

### Projects

A project is split across multiple storage concerns:

- `projects`: relational metadata such as slug, access, visibility, owner, status
- `project_structures`: JSON definition, derived extra structure, and mapping data
- `project_stats`: cached counters and storage totals
- `project_roles`: per-user membership and project role

This split is central to the application:
- relational columns support search, permissions, and admin views
- JSON structures support dynamic form building and schema evolution

### Entries

The data collection model uses:
- `entries` for top-level form submissions
- `branch_entries` for branch form submissions tied to an owning entry

Each stored entry can include:
- `entry_data` JSON
- `geo_json_data` JSON
- title and timestamps
- ownership references and form references

The architecture treats entry writes as a coordinated workflow:
- validate payload against the project definition
- hydrate `EntryStructureDTO`
- insert or update entry data
- update counters
- move media files when needed

### Users and providers

Authentication data is split between:
- `users`
- `users_providers`
- passwordless tables
- Passport OAuth tables

This supports:
- local login
- passwordless login
- Google login
- Apple login
- OAuth client integrations

## Request Lifecycle

Typical request flow:

1. A route is matched in `web`, `api_internal`, or `api_external`.
2. Middleware authenticates the caller and resolves project permissions.
3. Middleware stores `requestedUser`, `requestedProject`, and `requestedProjectRole` on the request.
4. The controller reads request data and invokes validators.
5. DTOs are hydrated or built from payload plus project context.
6. A service performs the business action, usually inside a DB transaction when writes are involved.
7. Models, traits, and query builder calls persist or fetch data.
8. A response macro returns standardized API JSON, file output, or streamed media.

## Authentication and Authorization

The application uses multiple authentication strategies.

### Session-backed web auth

Used by:
- browser pages
- internal API consumed by the web frontend

### Custom JWT auth

Implemented via `JwtAuthServiceProvider` and custom classes under `app/Libraries/Auth/Jwt`.

Used for:
- external API flows that need JWT-style auth behavior

### Passport OAuth

Used for:
- OAuth clients and token issuance
- project-linked API applications

`AuthServiceProvider` configures token expiry through Passport.

### Social and passwordless auth

Supported flows include:
- Google
- Apple
- passwordless web
- passwordless API
- local auth for staff/admin scenarios

Authorization is heavily project-role-based. The effective role on a project determines whether a user can:
- view a private project
- upload entries
- edit project configuration
- bulk upload
- manage project members

## Storage Architecture

### Relational storage

MySQL stores:
- users
- projects and roles
- entries and branch entries
- OAuth data
- cached project statistics

### JSON-in-relational pattern

The application stores dynamic structures in JSON columns:
- `project_definition`
- `project_extra`
- `project_mapping`
- `entry_data`
- `geo_json_data`
- stats count payloads

This is a key architectural compromise:
- relational columns handle stable lookup and permission concerns
- JSON handles user-defined project structure and dynamic answer payloads

### Media storage

Media can be stored on:
- local filesystem
- S3

The storage mode is environment-driven. `AppServiceProvider` includes a safety check to prevent non-production environments from pointing at the production S3 media bucket.

Media handling responsibilities are split across dedicated services:
- temp upload handling
- file moving
- photo/audio/video processing
- media counting
- stream/download responses

## Response Architecture

API responses are standardized through response macros under `app/Providers/Macros/Response`.

Common macros include:
- `apiData`
- `apiErrorCode`
- `apiSuccessCode`
- file and stream response helpers for JSON, CSV, TXT, thumbnails, and media

This gives the application a consistent API surface even though the controllers are spread across web and API namespaces.

## Configuration as a Source of Truth

The `config/epicollect/` directory is a major architectural component.

These configs centralize:
- table names
- limits and quotas
- error codes
- strings and enums
- permissions
- mapping defaults
- media-related behavior

Important implication:
- domain constants should come from config, not hardcoded strings

This is especially visible in services and validators that refer to:
- `config('epicollect.tables.*')`
- `config('epicollect.codes.*')`
- `config('epicollect.strings.*')`
- `config('epicollect.limits.*')`

## Cross-Cutting Patterns

### Transactions for write workflows

Project creation, entry creation, and role updates use DB transactions to keep multi-table state consistent.

### Trait-heavy reuse

Shared logic is often placed in traits rather than deep inheritance hierarchies.

Common areas:
- request context access
- Eloquent entry operations
- middleware helpers
- upload and response helpers

### Legacy-compatible evolution

The codebase carries forward legacy behaviors in several places:
- custom route guard handling in `RouteServiceProvider`
- response formatting compatibility
- mixed Eloquent and raw query patterns
- migration history that evolves JSON structures in place

This means changes should preserve behavior for existing clients, especially:
- mobile app payloads
- documented export endpoints
- dataviewer internal API contracts

## Operational and Admin Concerns

The application includes built-in admin capabilities:
- system stats
- project and user admin views
- storage inspection
- maintenance utilities

System-wide aggregation is handled by services under `app/Services/System`.

## Extending the System

When adding new functionality, follow the existing architecture:

- add routes in the correct surface: `web`, `api_internal`, or `api_external`
- enforce access through middleware first
- use validators for request and domain validation
- build or reuse DTOs for structured project or entry state
- put business logic in a service
- keep controllers as orchestration layers
- use config values instead of hardcoded domain constants
- use response macros for API output consistency

For project-aware features, prefer the established request-context pattern:
- resolve project and role in middleware
- consume `requestedProject()` and `requestedProjectRole()` downstream

## Directory Reference

Main directories and their architectural role:

- `app/DTO`: structured domain state for projects and entries
- `app/Http/Controllers`: delivery adapters for web and API surfaces
- `app/Http/Middleware`: authentication, permissions, and request gating
- `app/Http/Validation`: domain validation layer
- `app/Libraries`: low-level auth, generator, and utility code
- `app/Models`: persistence models and query logic
- `app/Services`: business workflows and transactions
- `app/Traits`: shared logic reused across layers
- `config/epicollect`: domain configuration and constants
- `routes`: route maps for the three application surfaces
- `database/migrations`: relational schema evolution

## Summary

Epicollect5 Server is best understood as a project-centric data collection platform built on:
- middleware-resolved request context
- DTO-driven project state
- service-layer business logic
- mixed relational and JSON persistence
- strict project-role authorization
- multiple delivery surfaces sharing the same domain core

That combination is what allows the same backend to support:
- the public site
- authenticated project management
- dataviewer/internal APIs
- mobile collection clients
- media handling
- OAuth and third-party integrations
