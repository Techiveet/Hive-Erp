<?php

namespace Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ResolvesExportBranding;
use Modules\Tenancy\Models\Tenant;
use Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Modules\Tenancy\Mail\TenantStatusChanged;
use Modules\Tenancy\Mail\AdminStatusChanged;
use Modules\Tenancy\Mail\AdminCredentialsUpdated;
use Modules\Tenancy\Mail\TenantCreated; // 🚀 Using our new Mailable
use Barryvdh\DomPDF\Facade\Pdf; // 🚀 Required for PDF generation

use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class TenantController extends Controller implements HasMiddleware
{
    use ResolvesExportBranding;

    public static function middleware(): array
    {
        return [
            // 🚀 Added 'exportPdf' to the view permissions
            new Middleware('permission:view_tenants|manage_tenants,sanctum', only: ['index', 'show', 'exportPdf']),
            new Middleware('permission:provision_tenants|manage_tenants,sanctum', only: ['store']),
            new Middleware('permission:edit_tenants|manage_tenants,sanctum', only: ['update']),
            new Middleware('permission:suspend_tenants|manage_tenants,sanctum', only: ['toggleStatus', 'toggleAdminStatus']),
            new Middleware('permission:delete_tenants|manage_tenants,sanctum', only: ['destroy']),
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
                  ->orWhereRaw('LOWER(data->>\'admin_email\') LIKE ?', ["%{$search}%"]);
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['name', 'plan', 'is_active', 'admin_email', 'admin_active'])) {
            $query->orderByRaw("data->>'$sortBy' $sortDir");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $tenants = $query->paginate($request->input('pageSize', 10));

        $formatted = $tenants->getCollection()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name ?? ucfirst($tenant->id),
                'plan' => $tenant->plan ?? 'Standard',
                'domain' => $tenant->domains->first()->domain ?? $tenant->id . '.localhost',
                'is_active' => $tenant->is_active ?? true,
                'admin_email' => $tenant->admin_email,
                'admin_active' => $tenant->admin_active ?? true,
                'created_at' => $tenant->created_at,
            ];
        });

        return response()->json([
            'data' => $formatted,
            'meta' => [
                'total' => $tenants->total(),
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
            ]
        ], 200);
    }

    // 🚀 THE NEW PDF EXPORT METHOD
    public function exportPdf(Request $request)
    {
        $query = Tenant::with('domains');

        // Apply the same search/sort so the PDF matches the user's view
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(data->>\'name\') LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(data->>\'plan\') LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(data->>\'admin_email\') LIKE ?', ["%{$search}%"]);
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['name', 'plan', 'is_active', 'admin_email', 'admin_active'])) {
            $query->orderByRaw("data->>'$sortBy' $sortDir");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        // Get all matching records (no pagination for reports)
        $tenants = $query->get();

        $branding = $this->getExportBranding(true);

        $pdf = Pdf::loadView('tenancy::exports.tenants-pdf', [
            'title'   => 'Tenant Nodes Directory',
            'data'    => $tenants,
            'logoUrl' => $branding['logo_url'],
            'branding' => $branding,
        ]);

        // Log the export action to the audit log
        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->event('exported')
            ->log("Exported Tenant Nodes Directory to PDF.");

        return $pdf->download('network-nodes-' . now()->format('Ymd_His') . '.pdf');
    }

public function store(Request $request)
    {
        $request->validate([
            'id' => ['required', 'string', 'alpha_dash', 'max:20', Rule::unique('tenants', 'id')],
            'name' => 'required|string|max:255',
            'plan' => 'required|string|in:startup,business,enterprise,overlord',
            'domain' => ['required', 'string', Rule::unique('domains', 'domain')],
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8',
        ]);

        try {
            // 1. Create Without Events
            $tenant = Tenant::withoutEvents(function () use ($request) {
                $t = new Tenant();
                $t->id = strtolower($request->id);
                $t->name = $request->name;
                $t->plan = $request->plan;
                $t->is_active = true;
                $t->admin_email = strtolower($request->admin_email);
                $t->save();
                return $t;
            });

            // 2. Register the routing domain
            $tenant->domains()->create(['domain' => strtolower($request->domain)]);

            // 3. Smart Database Check & Creation
            $dbManager = $tenant->database()->manager();
            $dbName = $tenant->database()->getName();

            if (! $dbManager->databaseExists($dbName)) {
                dispatch_sync(new CreateDatabase($tenant));
            }

            // 🚀 4. FORCE THE MIGRATIONS WITH EXPLICIT PATHS
            \Illuminate\Support\Facades\Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--path' => [
                    'database/migrations/tenant',
                    'Modules/Identity/database/migrations/tenant',
                    'Modules/Core/database/migrations/tenant',
                ]
            ]);

            // 5. Run the internal seeding
            $tenant->run(function () use ($request, $tenant) {
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

                // 🚀 THE BULLETPROOF FIX: updateOrCreate prevents Unique Violations
                // if a previous provisioning attempt left a ghost database behind.
                $admin = User::updateOrCreate(
                    ['email' => strtolower($request->admin_email)], // Search by this
                    [
                        'name' => $request->admin_name,
                        'password' => Hash::make($request->admin_password),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ] // Update or create with these details
                );

                $admin->guard_name = 'tenant';

                $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'tenant']);
                $admin->assignRole($role);

                try {
                    $token = \Illuminate\Support\Facades\Password::broker()->createToken($admin);
                    // 🚀 Using the Custom Mailable we created
                    Mail::to($admin->email)->send(new \Modules\Tenancy\Mail\TenantCreated($tenant, $admin, $request->admin_password, $token));
                } catch (\Exception $mailEx) {
                    \Illuminate\Support\Facades\Log::warning("Welcome email failed: " . $mailEx->getMessage());
                }
            });

            // 6. Log the activity
            activity('Tenant Management')
                ->causedBy(auth()->user() ?? null)
                ->performedOn($tenant) // 🚀 Tells React a node was created
                ->event('created')
                ->withProperties(['node_id' => $tenant->id])
                ->log("Provisioned new Tenant Node [{$tenant->id}] with plan [{$tenant->plan}].");

            return response()->json(['message' => 'Tenant Node provisioned successfully.', 'data' => $tenant->load('domains')], 201);

        } catch (\Exception $e) {
            // Rollback if anything fails
            if (isset($tenant) && $tenant->exists) {
                $dbName = 'tenant' . $tenant->id;
                try {
                    DB::statement("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?", [$dbName]);
                    DB::statement("DROP DATABASE IF EXISTS \"$dbName\"");
                } catch (\Exception $ex) {}

                $tenant->deleteQuietly();
            }

            activity('Tenant Management')->causedBy(auth()->user() ?? null)->event('failed')
                ->withProperties(['attempted_node_id' => $request->id])
                ->log("Failed to provision Tenant Node [{$request->id}]. Reason: " . $e->getMessage());

            return response()->json(['message' => 'Failure during node provisioning.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        return response()->json(['data' => $tenant], 200);
    }

    public function update(Request $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'plan' => 'sometimes|string|in:startup,business,enterprise,overlord',
            'admin_name' => 'nullable|string|max:255',
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ]);

        $tenant->update($request->only(['name', 'plan']));

        if ($request->filled('admin_email') || $request->filled('admin_name') || $request->filled('admin_password')) {
            $oldEmail = $tenant->admin_email;
            $newEmail = strtolower($request->admin_email ?? $oldEmail);
            $adminUpdated = false;

            if ($oldEmail) {
                $tenant->run(function () use ($request, $oldEmail, $newEmail, &$adminUpdated) {
                    $admin = User::where('email', $oldEmail)->first();

                    if ($admin) {
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
                            try { Mail::to($admin->email)->send(new AdminCredentialsUpdated($admin, $changes)); } catch (\Exception $ex) {}
                        }
                    }
                });

                if ($adminUpdated && $oldEmail !== $newEmail) {
                    $tenant->admin_email = $newEmail;
                    $tenant->save();
                }
            }
        }

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties(['node_id' => $tenant->id])
            ->log("Reconfigured Tenant Node [{$tenant->id}].");

        return response()->json(['message' => 'Node configuration updated.', 'data' => $tenant], 200);
    }

    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        try {
            $dbName = $tenant->database()->getName() ?? 'tenant' . $id;

            if (config('database.default') === 'pgsql') {
                try {
                    DB::statement("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?", [$dbName]);
                    DB::statement("DROP DATABASE IF EXISTS \"$dbName\"");
                } catch (\Exception $ex) {}
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
            try { Mail::to($tenant->admin_email)->send(new TenantStatusChanged($tenant->name, $newState, $tenant->id)); } catch (\Exception $e) {}
        }

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties(['node_id' => $tenant->id])
            ->log("Node [{$tenant->id}] network status changed to: " . ($newState ? 'Online' : 'Suspended'));

        return response()->json(['message' => "Node has been " . ($newState ? 'activated' : 'suspended') . ".", 'is_active' => $newState], 200);
    }

    public function toggleAdminStatus(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        if (empty($tenant->admin_email)) return response()->json(['message' => 'No primary admin email registered for this node.'], 404);

        $adminNewState = false;

        $tenant->run(function () use ($tenant, &$adminNewState) {
            $admin = User::where('email', $tenant->admin_email)->firstOrFail();
            $adminNewState = !$admin->is_active;
            $admin->update(['is_active' => $adminNewState]);

            try { Mail::to($admin->email)->send(new AdminStatusChanged((object)['name' => $admin->name, 'email' => $admin->email], $adminNewState, $tenant->name)); } catch (\Exception $e) {}
        });

        activity('Tenant Management')
            ->causedBy(auth()->user())
            ->performedOn($tenant)
            ->event('updated')
            ->withProperties(['node_id' => $tenant->id])
            ->log("Node [{$tenant->id}] Super Admin access changed to: " . ($adminNewState ? 'Active' : 'Suspended'));

        return response()->json(['message' => "Tenant Super Admin has been " . ($adminNewState ? 'activated' : 'suspended') . ".", 'admin_active' => $adminNewState], 200);
    }
}
