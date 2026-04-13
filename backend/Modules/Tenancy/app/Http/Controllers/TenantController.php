<?php

namespace Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Modules\Core\Support\ResolvesExportBranding;
use Modules\Identity\Models\User;
use Modules\Subscription\Support\TenantModuleCatalog;
use Modules\Subscription\Support\TenantSubscriptionService;
use Modules\Tenancy\Mail\AdminCredentialsUpdated;
use Modules\Tenancy\Mail\AdminStatusChanged;
use Modules\Tenancy\Mail\TenantStatusChanged;
use Modules\Tenancy\Models\Domain;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantDomainService;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;
use Modules\Tenancy\Support\TenantProvisioningService;

class TenantController extends Controller implements HasMiddleware
{
    use ResolvesExportBranding;

    public function __construct(
        protected TenantProvisioningService $tenantProvisioningService,
        protected TenantSubscriptionService $subscriptions,
        protected TenantDomainService $tenantDomains,
        protected TenantLandingTemplateCatalog $landingTemplates,
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_tenants|manage_tenants,sanctum', only: ['index', 'show', 'exportPdf']),
            new Middleware('permission:provision_tenants|manage_tenants,sanctum', only: ['store']),
            new Middleware('permission:edit_tenants|manage_tenants,sanctum', only: ['update', 'storeDomain', 'updateDomain', 'verifyDomain', 'makePrimaryDomain']),
            new Middleware('permission:suspend_tenants|manage_tenants,sanctum', only: ['toggleStatus', 'toggleAdminStatus']),
            new Middleware('permission:delete_tenants|manage_tenants,sanctum', only: ['destroy', 'destroyDomain']),
        ];
    }

    public function index(Request $request)
    {
        $query = Tenant::with('domains');

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(data->>\'name\') LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(data->>\'plan\') LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(data->>\'admin_email\') LIKE ?', ["%{$search}%"])
                    ->orWhereHas('domains', fn ($domainQuery) => $domainQuery->whereRaw('LOWER(domain) LIKE ?', ["%{$search}%"]));
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['name', 'plan', 'is_active', 'admin_email', 'admin_active'], true)) {
            $query->orderByRaw("data->>'$sortBy' $sortDir");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $tenants = $query->paginate($request->input('pageSize', 10));
        $formatted = $tenants->getCollection()->map(fn (Tenant $tenant) => $this->formatTenant($tenant));

        return response()->json([
            'data' => $formatted,
            'meta' => [
                'total' => $tenants->total(),
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
            ],
        ], 200);
    }

    public function exportPdf(Request $request)
    {
        $query = Tenant::with('domains');

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(data->>\'name\') LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(data->>\'plan\') LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(data->>\'admin_email\') LIKE ?', ["%{$search}%"])
                    ->orWhereHas('domains', fn ($domainQuery) => $domainQuery->whereRaw('LOWER(domain) LIKE ?', ["%{$search}%"]));
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['name', 'plan', 'is_active', 'admin_email', 'admin_active'], true)) {
            $query->orderByRaw("data->>'$sortBy' $sortDir");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $tenants = $query->get();
        $branding = $this->getExportBranding(true);

        $pdf = Pdf::loadView('tenancy::exports.tenants-pdf', [
            'title' => 'Tenant Nodes Directory',
            'data' => $tenants,
            'logoUrl' => $branding['logo_url'],
            'branding' => $branding,
        ]);

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->event('exported')
            ->log('Exported Tenant Nodes Directory to PDF.');

        return $pdf->download('network-nodes-' . now()->format('Ymd_His') . '.pdf');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'id' => ['required', 'string', 'alpha_dash', 'max:20', Rule::unique('tenants', 'id')],
            'name' => 'required|string|max:255',
            'plan' => ['required', 'string', Rule::in(array_keys(TenantModuleCatalog::planPricing()))],
            'business_type' => ['required', 'string', Rule::in($this->landingTemplates->businessTypeKeys())],
            'landing_page_template' => ['nullable', 'array'],
            'domain' => ['required', 'string', Rule::unique('domains', 'domain')],
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8',
        ], TenantModuleCatalog::validationRules()));

        try {
            $tenant = $this->tenantProvisioningService->provision($validated, auth()->user()?->email);

            $resolvedSubscriptions = $this->subscriptions->currentForTenant($tenant)['module_subscriptions'];

            activity('Tenant Management')
                ->causedBy(auth()->user() ?? null)
                ->performedOn($tenant)
                ->event('created')
                ->withProperties([
                    'node_id' => $tenant->id,
                    'module_subscriptions' => $resolvedSubscriptions['enabled_modules'],
                ])
                ->log("Provisioned new Tenant Node [{$tenant->id}] with plan [{$tenant->plan}] and {$resolvedSubscriptions['module_count']} subscribed modules.");

            return response()->json([
                'message' => 'Tenant Node provisioned successfully.',
                'data' => $this->formatTenant($tenant->load('domains')),
            ], 201);
        } catch (\Exception $e) {
            if (isset($tenant) && $tenant->exists) {
                $dbName = 'tenant' . $tenant->id;

                try {
                    DB::statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$dbName]);
                    DB::statement("DROP DATABASE IF EXISTS \"$dbName\"");
                } catch (\Exception $ex) {
                }

                $tenant->deleteQuietly();
            }

            $attemptedId = $validated['id'] ?? $request->id;

            activity('Tenant Management')
                ->causedBy(auth()->user() ?? null)
                ->event('failed')
                ->withProperties(['attempted_node_id' => $attemptedId])
                ->log("Failed to provision Tenant Node [{$attemptedId}]. Reason: " . $e->getMessage());

            return response()->json([
                'message' => 'Failure during node provisioning.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);

        return response()->json(['data' => $this->formatTenant($tenant)], 200);
    }

    public function storeDomain(Request $request, string $id)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', Rule::unique('domains', 'domain')],
        ]);

        $domain = $this->tenantDomains->createCustomDomain($tenant, $validated['domain']);
        $tenant->load('domains');

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties([
                'node_id' => $tenant->id,
                'domain' => $domain->domain,
                'action' => 'domain_added',
            ])
            ->log("Attached custom domain [{$domain->domain}] to Tenant Node [{$tenant->id}].");

        return response()->json([
            'message' => 'Custom domain added. Complete DNS verification before making it primary.',
            'data' => $this->tenantDomains->domainPayload($domain),
            'tenant' => $this->formatTenant($tenant),
        ], 201);
    }

    public function updateDomain(Request $request, string $id, int $domainId)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $domain = $this->findTenantDomain($tenant, $domainId);

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', Rule::unique('domains', 'domain')->ignore($domain->id)],
        ]);

        $updatedDomain = $this->tenantDomains->updateCustomDomain($tenant, $domain, $validated['domain']);
        $tenant->load('domains');

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties([
                'node_id' => $tenant->id,
                'domain' => $updatedDomain->domain,
                'action' => 'domain_updated',
            ])
            ->log("Updated custom domain [{$updatedDomain->domain}] for Tenant Node [{$tenant->id}].");

        return response()->json([
            'message' => 'Custom domain updated. Re-run DNS verification after the new records propagate.',
            'data' => $this->tenantDomains->domainPayload($updatedDomain),
            'tenant' => $this->formatTenant($tenant),
        ], 200);
    }

    public function verifyDomain(string $id, int $domainId)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $domain = $this->findTenantDomain($tenant, $domainId);
        $result = $this->tenantDomains->verifyDomain($tenant, $domain);
        $tenant->load('domains');

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties([
                'node_id' => $tenant->id,
                'domain' => $domain->domain,
                'verified' => $result['verified'],
                'action' => 'domain_verified',
            ])
            ->log(
                $result['verified']
                    ? "Verified custom domain [{$domain->domain}] for Tenant Node [{$tenant->id}]."
                    : "DNS verification check failed for custom domain [{$domain->domain}] on Tenant Node [{$tenant->id}]."
            );

        return response()->json([
            'message' => $result['verified']
                ? 'Domain verified. You can now make it the primary tenant address.'
                : 'Verification token not found yet. Confirm the TXT record and try again.',
            'data' => $this->tenantDomains->domainPayload($result['domain']),
            'tenant' => $this->formatTenant($tenant),
        ], 200);
    }

    public function makePrimaryDomain(string $id, int $domainId)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $domain = $this->findTenantDomain($tenant, $domainId);
        $primaryDomain = $this->tenantDomains->makePrimary($tenant, $domain);
        $tenant->load('domains');

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties([
                'node_id' => $tenant->id,
                'domain' => $primaryDomain->domain,
                'action' => 'domain_primary',
            ])
            ->log("Promoted domain [{$primaryDomain->domain}] to primary for Tenant Node [{$tenant->id}].");

        return response()->json([
            'message' => 'Primary tenant domain updated.',
            'data' => $this->tenantDomains->domainPayload($primaryDomain),
            'tenant' => $this->formatTenant($tenant),
        ], 200);
    }

    public function destroyDomain(string $id, int $domainId)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $domain = $this->findTenantDomain($tenant, $domainId);
        $domainName = $domain->domain;

        $this->tenantDomains->deleteDomain($tenant, $domain);
        $tenant->load('domains');

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties([
                'node_id' => $tenant->id,
                'domain' => $domainName,
                'action' => 'domain_deleted',
            ])
            ->log("Removed custom domain [{$domainName}] from Tenant Node [{$tenant->id}].");

        return response()->json([
            'message' => 'Custom domain removed.',
            'tenant' => $this->formatTenant($tenant),
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate(array_merge([
            'name' => 'sometimes|string|max:255',
            'plan' => ['sometimes', 'string', Rule::in(array_keys(TenantModuleCatalog::planPricing()))],
            'business_type' => ['sometimes', 'string', Rule::in($this->landingTemplates->businessTypeKeys())],
            'landing_page_template' => ['nullable', 'array'],
            'admin_name' => 'nullable|string|max:255',
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ], TenantModuleCatalog::validationRules()));

        $tenant->fill(Arr::only($validated, ['name', 'plan']));

        if (array_key_exists('business_type', $validated)) {
            $tenant->business_type = $this->landingTemplates->normalizeBusinessType($validated['business_type']);

            if (!array_key_exists('landing_page_template', $validated)) {
                $tenant->landing_page_template = $this->landingTemplates->defaultTemplate($validated['business_type']);
            }
        }

        if (array_key_exists('landing_page_template', $validated)) {
            $tenant->landing_page_template = $this->landingTemplates->normalizeTemplate(
                $validated['landing_page_template'],
                $validated['business_type'] ?? $tenant->business_type
            );
        }

        if ($tenant->isDirty()) {
            $tenant->save();
        }

        if (array_key_exists('module_subscriptions', $validated)) {
            $this->subscriptions->updateModules(
                $tenant,
                $validated['module_subscriptions'],
                auth()->user()?->email
            );
        } else {
            $this->subscriptions->ensureForTenant($tenant, null, auth()->user()?->email);
        }

        if ($request->filled('admin_email') || $request->filled('admin_name') || $request->filled('admin_password')) {
            $oldEmail = $tenant->admin_email;
            $newEmail = strtolower($request->admin_email ?? $oldEmail);
            $adminUpdated = false;

            if ($oldEmail) {
                $tenant->run(function () use ($request, $oldEmail, $newEmail, &$adminUpdated) {
                    $admin = User::where('email', $oldEmail)->first();

                    if (!$admin) {
                        return;
                    }

                    $changes = [];

                    if ($request->filled('admin_name') && $admin->name !== $request->admin_name) {
                        $changes['Operator Name'] = $request->admin_name;
                        $admin->name = $request->admin_name;
                    }

                    if ($request->filled('admin_email') && $admin->email !== $newEmail) {
                        $changes['Access Email'] = $newEmail;
                        $admin->email = $newEmail;
                    }

                    if ($request->filled('admin_password')) {
                        $changes['Encryption Key'] = '******** (Updated)';
                        $admin->password = Hash::make($request->admin_password);
                    }

                    if (!empty($changes)) {
                        $admin->save();
                        $adminUpdated = true;

                        try {
                            Mail::to($admin->email)->send(new AdminCredentialsUpdated($admin, $changes));
                        } catch (\Exception $ex) {
                        }
                    }
                });

                if ($adminUpdated && $oldEmail !== $newEmail) {
                    $tenant->admin_email = $newEmail;
                    $tenant->save();
                }
            }
        }

        $resolvedSubscriptionState = $this->subscriptions->currentForTenant($tenant->refresh());
        $resolvedSubscriptions = $resolvedSubscriptionState['module_subscriptions'];

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties([
                'node_id' => $tenant->id,
                'module_subscriptions' => $resolvedSubscriptions['enabled_modules'],
            ])
            ->log("Reconfigured Tenant Node [{$tenant->id}].");

        return response()->json([
            'message' => 'Node configuration updated.',
            'data' => $this->formatTenant($tenant->load('domains')),
        ], 200);
    }

    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        try {
            $dbName = $tenant->database()->getName() ?? 'tenant' . $id;

            if (config('database.default') === 'pgsql') {
                try {
                    DB::statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$dbName]);
                    DB::statement("DROP DATABASE IF EXISTS \"$dbName\"");
                } catch (\Exception $ex) {
                }
            }

            $tenant->deleteQuietly();
            $tenant->domains()->delete();

            activity('Tenant Management')
                ->causedBy(auth()->user())
                ->performedOn($tenant)
                ->event('deleted')
                ->withProperties(['node_id' => $id])
                ->log("Permanently purged Tenant Node [{$id}] from the network.");

            return response()->json(['message' => "Node [{$id}] has been permanently purged from the network."], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to purge node.', 'error' => $e->getMessage()], 500);
        }
    }

    public function toggleStatus(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $newState = !($tenant->is_active ?? true);

        $tenant->is_active = $newState;
        $tenant->save();

        if (!empty($tenant->admin_email)) {
            try {
                Mail::to($tenant->admin_email)->send(new TenantStatusChanged($tenant->name, $newState, $tenant->id));
            } catch (\Exception $e) {
            }
        }

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties(['node_id' => $tenant->id])
            ->log('Node [' . $tenant->id . '] network status changed to: ' . ($newState ? 'Online' : 'Suspended'));

        return response()->json([
            'message' => 'Node has been ' . ($newState ? 'activated' : 'suspended') . '.',
            'is_active' => $newState,
        ], 200);
    }

    public function toggleAdminStatus(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        if (empty($tenant->admin_email)) {
            return response()->json(['message' => 'No primary admin email registered for this node.'], 404);
        }

        $adminNewState = false;

        $tenant->run(function () use ($tenant, &$adminNewState) {
            $admin = User::where('email', $tenant->admin_email)->firstOrFail();
            $adminNewState = !$admin->is_active;
            $admin->update(['is_active' => $adminNewState]);

            try {
                Mail::to($admin->email)->send(new AdminStatusChanged((object) [
                    'name' => $admin->name,
                    'email' => $admin->email,
                ], $adminNewState, $tenant->name));
            } catch (\Exception $e) {
            }
        });

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties(['node_id' => $tenant->id])
            ->log('Node [' . $tenant->id . '] Super Admin access changed to: ' . ($adminNewState ? 'Active' : 'Suspended'));

        return response()->json([
            'message' => 'Tenant Super Admin has been ' . ($adminNewState ? 'activated' : 'suspended') . '.',
            'admin_active' => $adminNewState,
        ], 200);
    }

    protected function formatTenant(Tenant $tenant): array
    {
        $tenant->loadMissing('domains');
        $primaryDomain = $tenant->primaryDomain();
        $fallbackDomain = $tenant->fallbackDomain();
        $expectedFallbackDomain = $this->tenantDomains->expectedFallbackDomain($tenant);

        $currentSubscription = $this->subscriptions->currentForTenant($tenant);
        $subscriptions = $currentSubscription['module_subscriptions'];
        $businessType = $this->landingTemplates->normalizeBusinessType($tenant->business_type ?? null);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name ?? ucfirst($tenant->id),
            'plan' => $tenant->plan ?? 'Standard',
            'business_type' => $businessType,
            'business_type_meta' => $this->landingTemplates->businessTypeMeta($businessType),
            'landing_page_template' => $this->landingTemplates->normalizeTemplate(
                $tenant->landing_page_template ?? null,
                $businessType
            ),
            'domain' => $primaryDomain?->domain ?? $expectedFallbackDomain,
            'primary_domain' => $primaryDomain?->domain ?? $expectedFallbackDomain,
            'fallback_domain' => $fallbackDomain?->domain ?? $expectedFallbackDomain,
            'domains' => $this->tenantDomains->domainsPayload($tenant),
            'is_active' => $tenant->is_active ?? true,
            'admin_email' => $tenant->admin_email,
            'admin_active' => $tenant->admin_active ?? true,
            'created_at' => $tenant->created_at,
            'subscription' => collect($currentSubscription)->except('module_subscriptions')->all(),
            'module_subscriptions' => $subscriptions,
            'subscribed_modules' => $subscriptions['selected_modules'],
            'subscribed_modules_count' => $subscriptions['module_count'],
        ];
    }

    protected function findTenantDomain(Tenant $tenant, int $domainId): Domain
    {
        return $tenant->domains()->findOrFail($domainId);
    }
}
