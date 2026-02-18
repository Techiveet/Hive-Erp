<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\Exportable;

class UsersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected $query;
    private $rowNumber = 0; // ✅ Counter for sequential IDs (1, 2, 3...)

    /**
     * Accepts the Query Builder from the UserExportController.
     */
    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * Returns the pre-filtered query.
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * Headings for the Excel sheet.
     */
    public function headings(): array
    {
        return [
            '#',
            'Name',
            'Email',
            'Role',
            'Status',
            'Joined Date',
        ];
    }

    /**
     * Map each row data.
     * We ignore the DB ID and use the counter for sequential order.
     */
    public function map($user): array
    {
        $this->rowNumber++; // Increment sequential counter

        return [
            $this->rowNumber, // ✅ Sequential display (1, 2, 3...)
            $user->name,
            $user->email,
            $user->roles->first()?->name ?? 'Member',
            $user->is_active ? 'Active' : 'Inactive',
            $user->created_at->format('Y-m-d H:i'), // Standardized format
        ];
    }
}
