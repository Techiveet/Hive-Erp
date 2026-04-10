<?php

namespace Modules\Identity\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RolesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithCustomChunkSize
{
    use Exportable;

    private int $rowCount = 0;

    public function __construct(private Builder $query, private array $dictionary = []) {}

    private function t($key, $default) { return $this->dictionary[$key] ?? $default; }

    public function query() { return $this->query; }

    public function headings(): array
    {
        return [
            '#',
            $this->t('roles.col_designation', 'Clearance Designation'),
            $this->t('roles.col_capabilities', 'Active Capabilities (Permissions)'),
            $this->t('roles.col_established', 'Established Date'),
        ];
    }

    public function map($role): array
    {
        $this->rowCount++;
        $permissions = $role->permissions->pluck('name')->implode(', ');

        if ($role->name === 'Super Admin') {
            $permissions = $this->t('roles.god_mode', 'ALL PROTOCOLS (GOD MODE)');
        } elseif (empty($permissions)) {
            $permissions = $this->t('roles.no_access', 'No Access');
        }

        return [
            $this->rowCount,
            $role->name,
            $permissions,
            $role->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [ 1 => ['font' => ['bold' => true]] ];
    }

    public function chunkSize(): int
    {
        return 2000;
    }
}
