<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PermissionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
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
            'Capability Code',
            'Human-Readable Description',
            'Security Scope',
            'Created At',
        ];
    }

    public function map($permission): array
    {
        // Convert "edit_users" to "Edit Users"
        $description = 'Allows operator to ' . ucwords(str_replace('_', ' ', $permission->name));

        return [
            $permission->id,
            $permission->name,
            $description,
            $permission->guard_name === 'tenant' ? 'Tenant Node' : 'Central Command',
            $permission->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
