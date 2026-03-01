<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RolesExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $query) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Clearance Designation',
            'Active Capabilities (Permissions)',
            'Total Capabilities',
            'Established Date',
        ];
    }

    public function map($role): array
    {
        $permissions = $role->permissions->pluck('name')->implode(', ');
        
        // Handle Super Admin god mode
        if ($role->name === 'Super Admin') {
            $permissions = 'ALL PROTOCOLS (GOD MODE)';
        } elseif (empty($permissions)) {
            $permissions = 'No Access';
        }

        return [
            $role->id,
            $role->name,
            $permissions,
            $role->name === 'Super Admin' ? 'ALL' : $role->permissions->count(),
            $role->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]], // Bold the first row (headings)
        ];
    }
}