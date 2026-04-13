# Hive Deployment Playbook

## Demo or Sales Environment

Use the seeded showcase data when you want the product to feel complete on day one.

```bash
docker compose up -d db redis meilisearch mailpit rembg backend frontend
docker compose run --rm backend php artisan migrate:fresh --seed --no-interaction
```

This seeds premium showcase tenants across multiple business types, including editable landing-page templates in the admin workspace.

## Production Bootstrap

For a real production rollout, use the production stack and bootstrap only the central platform admin.

```bash
cp .env.prod-example .env
bash scripts/deploy-prod.sh --root-domain yourdomain.com --server-ip YOUR_SERVER_IP
docker compose -f docker-compose.prod.yml exec backend php artisan hive:bootstrap-production --email=owner@yourdomain.com --name="Platform Owner"
```

`hive:bootstrap-production` intentionally skips demo tenants and test users.

## Client-Owned Server With Specific Modules

When a client wants their own server with only the modules they bought, deploy the same production stack and provision a dedicated tenant from the server:

```bash
docker compose -f docker-compose.prod.yml exec backend php artisan hive:provision-tenant acme "Acme Trading" \
  --plan=enterprise \
  --business-type=retail \
  --domain=acme.yourdomain.com \
  --admin-name="Acme Admin" \
  --admin-email=admin@acme.yourdomain.com \
  --admin-password='ChangeMe123!' \
  --module=inventory_control \
  --module=invoice_billing \
  --module=advanced_analytics
```

You can repeat `--module` for each catalog module and repeat `--custom-module` with `name[:category[:description]]` for client-specific capabilities.

If you omit `--module`, Hive provisions the tenant with the default module set for the selected plan.
