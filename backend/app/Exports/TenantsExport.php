<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TenantsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private Builder $query, private array $dictionary = []) {}

    private function t($key, $default) {
        return $this->dictionary[$key] ?? $default;
    }

    public function query() { return $this->query; }

    public function headings(): array
    {
        return [
            $this->t('tenants.col_id', 'Node ID'),
            $this->t('tenants.col_org', 'Organization Name'),
            $this->t('tenants.col_plan', 'Capacity Plan'),
            $this->t('tenants.col_domain', 'Routing Domain'),
            $this->t('tenants.col_status', 'Node Status'),
            $this->t('tenants.view_contact', 'Super Admin Contact'),
            'Admin Status',
            $this->t('tenants.col_provisioned', 'Provisioned Date'),
        ];
    }

    public function map($tenant): array
    {
        $domain = $tenant->domains->first()?->domain ?? "{$tenant->id}.localhost";

        $status = ($tenant->is_active ?? true) ? $this->t('global.online', 'ONLINE') : $this->t('global.suspended', 'SUSPENDED');
        $adminStatus = ($tenant->admin_active ?? true) ? $this->t('global.active', 'ACTIVE') : $this->t('global.suspended', 'LOCKED');

        return [
            strtoupper((string)$tenant->id),
            (string)($tenant->name ?? ucfirst($tenant->id)),
            strtoupper((string)($tenant->plan ?? 'business')),
            (string)$domain,
            strtoupper($status),
            (string)($tenant->admin_email ?? $this->t('tenants.no_email', 'Not Set')),
            strtoupper($adminStatus),
            $tenant->created_at ? $tenant->created_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EA580C']
                ]
            ],
        ];
    }
}
