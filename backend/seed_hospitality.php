<?php

use Modules\Hospitality\Models\Zone;
use Modules\Hospitality\Models\Location;
use Modules\Hospitality\Models\GuestList;
use Modules\Identity\Models\User;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function seedTenant($tenantId, $businessType) {
    echo "Seeding tenant: $tenantId ($businessType)\n";
    
    // Create Zones
    $mainZone = Zone::updateOrCreate(
        ['tenant_id' => $tenantId, 'name' => $businessType === 'nightclub' ? 'Main Floor' : 'Main Dining'],
        ['description' => 'Primary area for guests']
    );
    
    $vipZone = Zone::updateOrCreate(
        ['tenant_id' => $tenantId, 'name' => $businessType === 'nightclub' ? 'VIP Lounge' : 'Terrace'],
        ['description' => 'Premium seating area']
    );

    // Create Locations for Main Zone
    for ($i = 1; $i <= 5; $i++) {
        Location::updateOrCreate(
            ['tenant_id' => $tenantId, 'zone_id' => $mainZone->id, 'label' => ($businessType === 'nightclub' ? 'M-' : 'T-') . str_pad($i, 2, '0', STR_PAD_LEFT)],
            ['capacity' => 4, 'status' => 'available', 'min_spend' => $businessType === 'nightclub' ? 100.00 : null]
        );
    }

    // Create Locations for VIP Zone
    for ($i = 1; $i <= 3; $i++) {
        Location::updateOrCreate(
            ['tenant_id' => $tenantId, 'zone_id' => $vipZone->id, 'label' => ($businessType === 'nightclub' ? 'VIP-' : 'EXT-') . str_pad($i, 2, '0', STR_PAD_LEFT)],
            ['capacity' => 6, 'status' => $i === 1 ? 'reserved' : 'available', 'min_spend' => $businessType === 'nightclub' ? 500.00 : null]
        );
    }

    if ($businessType === 'nightclub') {
        // Seed some guests
        $promoter = User::first();
        if ($promoter) {
            GuestList::updateOrCreate(
                ['tenant_id' => $tenantId, 'guest_name' => 'Michael Corleone'],
                ['promoter_id' => $promoter->id, 'expected_party_size' => 10, 'status' => 'pending']
            );
            GuestList::updateOrCreate(
                ['tenant_id' => $tenantId, 'guest_name' => 'Tony Montana'],
                ['promoter_id' => $promoter->id, 'expected_party_size' => 5, 'status' => 'pending']
            );
        }
    }
}

seedTenant('tesla', 'nightclub');
seedTenant('selam-bistro', 'restaurant');

echo "Seeding completed!\n";
