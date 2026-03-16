<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    private int $rowCount = 0;

    public function __construct(private Builder $query, private array $dictionary = []) {}

    private function t($key, $default) {
        return $this->dictionary[$key] ?? $default;
    }

    public function query()
    {
        return $this->query;
    }

    public function map($user): array
    {
        $this->rowCount++;
        return [
            $this->rowCount,
            $user->name,
            $user->email,
            $user->roles->first()?->name ?? 'User',
            $user->is_active ? $this->t('global.active', 'Active') : $this->t('global.locked', 'Locked'),
            $user->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            $this->t('users.col_operator', 'Name'),
            $this->t('users.email_address', 'Email Address'),
            $this->t('users.col_clearance', 'Role'),
            $this->t('users.col_status', 'Status'),
            $this->t('users.col_provisioned', 'Date Provisioned'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [ 1 => ['font' => ['bold' => true]] ];
    }
}
