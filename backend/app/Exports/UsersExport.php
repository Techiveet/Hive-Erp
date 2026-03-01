<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $query;
    private $rowNumber = 0; // Tracks incremental row index

    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * Define the query to pull data from.
     * We pass the query directly from the controller so it respects all the filters!
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * Map each row of the database to an array for the Excel/CSV file.
     */
    public function map($user): array
    {
        return [
            ++$this->rowNumber, // Dynamically counts 1, 2, 3...
            $user->name,
            $user->email,
            $user->roles->first()?->name ?? 'User',
            $user->is_active ? 'Active' : 'Inactive',
            $user->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Define the column headers for the Excel/CSV file.
     */
    public function headings(): array
    {
        return [
            '#', // The header for our new row counter
            'Name',
            'Email Address',
            'Role',
            'Status',
            'Date Provisioned',
        ];
    }
}