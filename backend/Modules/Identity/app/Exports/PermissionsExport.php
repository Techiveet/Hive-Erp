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

class PermissionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithCustomChunkSize
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
            $this->t('permissions.col_code', 'Capability Code'),
            $this->t('permissions.col_desc', 'Human-Readable Description'),
            $this->t('permissions.col_scope', 'Security Scope'),
        ];
    }

    public function map($permission): array
    {
        $this->rowCount++;

        $descContext = $this->t('permissions.allows_operator', 'Allows operator to');
        $description = $descContext . ' ' . ucwords(str_replace('_', ' ', $permission->name));
        $scope = $permission->guard_name === 'tenant' ? $this->t('permissions.tenant_node', 'Tenant Node') : $this->t('permissions.central', 'Central Command');

        return [
            $this->rowCount,
            $permission->name,
            $description,
            $scope,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function chunkSize(): int
    {
        return 2000;
    }
}
