# HIVE Backend

HIVE runs as a modular monolith.

## Architecture

The `app/` directory is now the application shell.
It should only contain framework bootstrapping, infrastructure wrappers, and cross-cutting middleware.
Business capabilities belong inside Laravel modules under `Modules/*`.

### Active modules

- `Core`: dashboard, settings, audit logs, file management, system operations, API docs.
- `Identity`: authentication, users, roles, permissions, profiles, 2FA.
- `Tenancy`: tenant provisioning, tenant lifecycle, tenant administration.

## Rules

- Put new domain logic in the owning module, not in `app/`.
- Treat `app/` shims as compatibility layers while older imports are phased out.
- Shared business helpers should live in `Modules/Core/app/Support` unless a stronger owner exists.
- Route URLs can stay stable, but the controller ownership should point at the module namespace.
- New features should be represented in both the backend module and the matching frontend module under `frontend/modules/*`.

## Module catalog

The canonical backend catalog lives in `config/modular_monolith.php`.

List the current module boundaries with:

```bash
php artisan hive:modules
```

Get the same catalog as JSON with:

```bash
php artisan hive:modules --json
```
