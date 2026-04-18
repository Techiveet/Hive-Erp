# Customer-Managed Tenant Data Design

Date: 2026-04-18

## Summary

Hive will support two production deployment tiers from one codebase:

- `shared`: Hive hosts the application, tenant database, and tenant object storage on Hive-managed infrastructure.
- `customer_managed`: Hive hosts the application, modules, subscriptions, billing, and control plane on Hive-managed infrastructure, while the customer hosts only the tenant database and tenant object storage on their own infrastructure.

The `customer_managed` tier is the premium enterprise mode for customers who want ownership of their operational data without taking over the full application stack. In this mode, Hive continues to run the full product centrally, but each premium tenant uses a private-network-only PostgreSQL database and a private-network-only MinIO deployment located on the customer's infrastructure.

## Goals

- Keep one product and one codebase for both shared-cloud and customer-managed-data tenants.
- Make MinIO-backed S3-compatible storage the default path in local development.
- Add a first-class tenant infrastructure profile that determines where a tenant's database and object storage live.
- Support secure runtime switching to customer-hosted PostgreSQL and MinIO for `customer_managed` tenants.
- Keep central control of plans, module subscriptions, tenant registry, health, and support workflows.
- Fail closed when customer-managed infrastructure is unavailable.
- Keep the shared-cloud path working without regressions.

## Non-Goals

- This design does not turn `customer_managed` tenants into fully customer-hosted Hive deployments.
- This design does not allow public internet database access for premium tenants.
- This design does not let central administrators permanently bypass tenant boundaries or directly browse customer infrastructure outside the normal audited application flow.
- This design does not introduce a separate gateway/agent product in the first implementation.

## Product Modes

### Shared

- Tenant metadata is stored centrally.
- Tenant application data is stored in Hive-managed PostgreSQL using the existing multi-tenant tenancy flow.
- Tenant files and generated media are stored in Hive-managed object storage.
- This remains the default mode for standard SaaS customers.

### Customer Managed

- Tenant metadata, deployment profile, plan, and module subscriptions are stored centrally.
- Tenant application data is stored in customer-hosted PostgreSQL.
- Tenant object storage is stored in customer-hosted MinIO.
- Hive application services remain centrally hosted and connect to customer infrastructure through private networking only.

## Architecture

Hive will operate as a central application plane with per-tenant data infrastructure selection.

### Central Application Plane

The central application plane continues to host:

- backend application runtime
- queue workers
- websockets/reverb
- module code and routing
- central tenant registry
- subscription and module entitlement logic
- billing and licensing workflows
- support-session workflows
- health monitoring and operational dashboards

### Tenant Data Plane

For `customer_managed` tenants, the tenant data plane contains:

- customer-hosted PostgreSQL for tenant business data
- customer-hosted MinIO for tenant object storage
- customer-managed private network path to Hive

Hive never uses public internet endpoints for customer-managed PostgreSQL or MinIO. Connectivity must be established through a private network such as WireGuard, Tailscale, or a site-to-site VPN.

## Core Design Decisions

### One Codebase, Two Infrastructure Profiles

The application will not fork into separate enterprise and SaaS backends. Instead, each tenant will have an infrastructure mode:

- `shared`
- `customer_managed`

Runtime bootstrapping, provisioning, health checks, and admin UX will branch on this infrastructure mode.

### Central Owns Control, Customer Owns Data

For `customer_managed` tenants:

- Hive owns module availability, plans, entitlements, lifecycle, support policy, and the application runtime.
- The customer owns the PostgreSQL database contents and MinIO object storage contents for their tenant.

### Private Networking Only

Premium customer-managed tenants are valid only when Hive can reach customer PostgreSQL and MinIO through an approved private network path. Public endpoints, even with allowlisting, are out of scope for the premium secure tier.

## Tenant Infrastructure Profile

Each tenant will gain a first-class infrastructure profile stored centrally.

### Stored Non-Secret Metadata

The central system will store:

- tenant infrastructure mode
- database driver
- database host
- database port
- database name
- database username
- MinIO endpoint
- MinIO bucket
- MinIO region
- MinIO path-style requirement
- network mode, always `private_only` for `customer_managed`
- health status
- connectivity validation timestamps

### Stored Secrets

The central system will also store encrypted secrets for:

- tenant database password
- MinIO access key
- MinIO secret key

The first implementation will use Laravel application encryption for stored credentials. The design keeps the storage layout compatible with a later move to an external secrets manager without changing the tenant runtime contract.

## Database Runtime Design

### Shared Tenants

Shared tenants continue to use the existing tenancy model backed by Stancl Tenancy and Hive-managed tenant databases.

### Customer-Managed Tenants

Customer-managed tenants will not rely on the current "tenant database name derived from tenant id on the local cluster" assumption. Instead, a custom tenant connection resolver will:

- read the tenant infrastructure profile
- build a PostgreSQL connection dynamically at request boot
- enforce `private_only` connectivity expectations
- initialize tenancy against the customer-hosted PostgreSQL database

### Provisioning Behavior

For `shared` tenants:

- keep the current create-database and tenant-migrate flow

For `customer_managed` tenants:

- do not create databases on Hive infrastructure
- validate connectivity to the customer PostgreSQL instance
- run tenant migrations against the customer PostgreSQL database over the private network
- activate the tenant only after connectivity and migrations succeed

## Storage Runtime Design

### Storage Strategy

Hive will treat S3-compatible object storage as the primary persistent storage model for tenant uploads and media.

This applies to:

- uploads
- generated media
- exports
- downloadable assets
- backups stored in object storage

Local disk remains acceptable only for:

- framework cache
- logs
- temporary processing directories
- short-lived intermediate files

### Shared Tenants

Shared tenants will use Hive-managed S3-compatible object storage.

### Customer-Managed Tenants

Customer-managed tenants will use their customer-hosted MinIO deployment. A tenant-aware storage resolver will inject the correct S3-compatible disk configuration at runtime for the active tenant.

The system must not silently fall back from customer-managed MinIO to Hive-managed shared storage.

## Module and Subscription Model

Modules, plan selection, and subscription state remain central for all tenants.

This means:

- module catalog remains centrally defined
- subscription entitlements remain centrally enforced
- central admins can manage which modules a tenant is allowed to use
- the tenant runs the same module set in the same application, regardless of where the tenant data lives

For `customer_managed` tenants, module execution still happens in the central Hive application runtime. Only the tenant data storage locations change.

## Security Model

### Network Security

- customer-managed PostgreSQL and MinIO must not be publicly exposed
- Hive connects only through approved private networking
- tenants without validated private connectivity remain inactive

### Credential Isolation

- each customer-managed tenant receives a dedicated PostgreSQL user
- each customer-managed tenant receives dedicated MinIO credentials
- credentials are scoped to the single tenant
- credentials are encrypted at rest in the central control plane

### Access Model

- central administrators use the Hive application boundary, not raw infrastructure credentials
- support access is temporary and audited
- no permanent broad support bypass is allowed

### Failure Policy

- fail closed on customer-managed DB or MinIO connectivity failures
- do not reroute customer-managed tenant traffic into shared storage or shared database infrastructure

## Health and Failure Handling

Each customer-managed tenant will have infrastructure health tracked centrally.

### Health States

- `healthy`: database and MinIO checks pass
- `degraded`: connectivity exists but one or more checks are failing intermittently
- `offline`: database or MinIO is unavailable and tenant traffic cannot proceed safely

### Operational Behavior

- only the affected tenant is impacted by customer infrastructure failures
- shared-cloud tenants and central administration remain online
- background jobs for the affected tenant retry with bounded backoff
- health status is visible in central operations tooling

## Support Access Design

Support access for customer-managed tenants will be explicit and time-bound.

### Rules

- customer approval is required before elevated support access is granted
- support access is represented as a short-lived scoped session
- all support actions are audited by tenant, actor, and time

### Audit Requirements

Audit records must capture:

- operator identity
- tenant identity
- start and end of support session
- privileged actions taken during the session

## Local Development Design

Local development will use MinIO by default.

### Local Defaults

- Docker Compose includes a local MinIO service
- Laravel local development uses `FILESYSTEM_DISK=s3`
- local bucket creation is automated during setup or startup
- path-style S3 access is enabled for local MinIO

### Local Tenant Testing

Local development must support testing both infrastructure modes:

- `shared` tenant profile using normal local development paths
- `customer_managed` tenant profile pointing at local/private PostgreSQL and local MinIO containers

This keeps development, staging, and enterprise runtime behavior aligned.

## Provisioning and Administration

Tenant provisioning must branch by infrastructure mode.

### Shared Tenant Provisioning

- keep existing flow
- create tenant database on Hive-managed infrastructure
- run tenant migrations
- assign modules and activate

### Customer-Managed Tenant Provisioning

- collect customer PostgreSQL connection metadata
- collect customer MinIO connection metadata
- validate private connectivity
- validate object storage access to the configured bucket
- run tenant migrations against the customer PostgreSQL database
- store encrypted credentials
- activate tenant only after all validation succeeds

### Central Admin Responsibilities

Central administration remains responsible for:

- tenant creation and lifecycle
- plan and module assignment
- health visibility
- support approvals and support session controls

## Rollout Plan

### Phase 1

- make MinIO the default local development storage backend
- keep production behavior unchanged

### Phase 2

- add tenant infrastructure profile storage and encrypted credentials
- add admin-side mode selection for `shared` and `customer_managed`

### Phase 3

- add dynamic PostgreSQL switching for customer-managed tenants
- add dynamic MinIO switching for customer-managed tenants

### Phase 4

- add provisioning validation, health checks, and offline/degraded handling

### Phase 5

- add support-session controls and expanded audit coverage

## Testing Strategy

### Unit Tests

- tenant infrastructure profile resolution
- credential decryption and connection option assembly
- runtime branching between `shared` and `customer_managed`

### Integration Tests

- tenant database switching
- MinIO-backed upload and retrieval flows
- provisioning for both deployment modes
- tenant migration execution against customer-managed PostgreSQL

### Failure Tests

- private network unavailable
- customer PostgreSQL unavailable
- customer MinIO unavailable
- background job retry behavior during tenant infrastructure outages

### Local Smoke Tests

- Docker-based local PostgreSQL
- Docker-based local MinIO
- at least one `shared` smoke tenant
- at least one `customer_managed` smoke tenant

## Consequences

### Benefits

- enterprise customers can own their data without forcing Hive into per-customer full-stack deployments
- Hive keeps one centrally managed application and module platform
- shared-cloud customers continue to use the simple SaaS path
- local development aligns with production storage behavior

### Costs

- runtime tenancy becomes more complex because infrastructure varies by tenant
- secrets management becomes more important
- customer networking must be established correctly before tenant activation
- support and observability need stronger operational tooling

## Final Decision

Hive will implement a premium `customer_managed` tenant mode where:

- Hive centrally hosts the application, modules, subscriptions, and control plane
- the customer hosts only their tenant PostgreSQL database and MinIO object storage
- all customer-managed connectivity is private-network-only
- MinIO-backed S3-compatible storage becomes the default local development storage model
