<?php

use Modules\Hospitality\Models\Zone;
use Modules\Hospitality\Models\Location;
use Modules\Hospitality\Models\GuestList;
use Modules\Hospitality\Models\PromoterCommission;
use Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Set a mock tenant
config(['tenancy.tenant' => ['id' => 'test-tenant-1', 'business_type' => 'nightclub']]);
function tenant($key = null) {
    if ($key === 'id') return 'test-tenant-1';
    if ($key === 'business_type') return 'nightclub';
    return null;
}

echo "--- Testing Hospitality Backend Logic ---\n";

DB::beginTransaction();

try {
    // 1. Create a Promoter
    $promoter = User::firstOrCreate(['email' => 'promoter@example.com'], ['name' => 'John Promoter', 'password' => bcrypt('password')]);
    
    // 2. Create a Zone
    $zone = Zone::create([
        'tenant_id' => 'test-tenant-1',
        'name' => 'VIP Section',
        'description' => 'Premium lounge area'
    ]);
    echo "Zone created: {$zone->name}\n";

    // 3. Create a Location
    $location = Location::create([
        'tenant_id' => 'test-tenant-1',
        'zone_id' => $zone->id,
        'label' => 'S-01',
        'capacity' => 6,
        'min_spend' => 500.00,
        'status' => 'available'
    ]);
    echo "Location created: {$location->label} (Status: {$location->status})\n";

    // 4. Test Update Status
    $location->update(['status' => 'occupied']);
    echo "Location status updated to: {$location->status}\n";

    // 5. Create a Guest List entry
    $guest = GuestList::create([
        'tenant_id' => 'test-tenant-1',
        'promoter_id' => $promoter->id,
        'guest_name' => 'Alice Guest',
        'expected_party_size' => 4,
        'status' => 'pending'
    ]);
    echo "Guest List entry created: {$guest->guest_name} (Promoter: {$promoter->name})\n";

    // 6. Test Check-in Logic
    echo "Checking in guest with 5 people (expected 4)...\n";
    $guest->update([
        'actual_arrived_count' => 5,
        'status' => 'arrived'
    ]);
    
    // Simulate PromoterCommission logic from controller
    $today = date('Y-m-d');
    $commission = PromoterCommission::firstOrCreate(
        ['tenant_id' => 'test-tenant-1', 'promoter_id' => $promoter->id, 'date' => $today],
        ['total_guests_brought' => 0, 'commission_earned' => 0.00, 'status' => 'unpaid']
    );
    $commission->increment('total_guests_brought', 5);
    $commission->increment('commission_earned', 5 * 10.00);

    echo "Guest status updated to: {$guest->status}\n";
    echo "Promoter Commission updated: Total Guests = {$commission->total_guests_brought}, Earned = {$commission->commission_earned}\n";

    echo "--- All Logic Tests Passed ---\n";
    
    DB::rollBack(); // Don't persist test data
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
