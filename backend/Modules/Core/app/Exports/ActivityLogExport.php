<?php

namespace Modules\Core\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActivityLogExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $query, private array $dictionary = []) {}

    private function t($key, $default) {
        return $this->dictionary[$key] ?? $default;
    }

    public function query() { return $this->query; }

    public function headings(): array
    {
        return [
            $this->t('audit.col_time', 'Timestamp (UTC)'),
            $this->t('audit.col_action', 'Action Event'),
            $this->t('audit.col_module', 'Module'),
            $this->t('audit.col_desc', 'Activity Description'),
            $this->t('audit.col_operator', 'Operator'),
            $this->t('audit.col_node', 'Node Origin'),
        ];
    }

    public function map($log): array
    {
        $rawEvent = strtolower($log->event ?? 'sys');
        $translatedEvent = in_array($rawEvent, ['created', 'updated', 'deleted', 'viewed', 'exported', 'copied', 'printed'])
            ? $this->t("global.{$rawEvent}", $rawEvent) : $rawEvent;

        return [
            $log->id,
            $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A',
            strtoupper($translatedEvent),
            $log->log_name ?? 'N/A',
            $log->description ?? 'N/A',
            $log->causer ? $log->causer->name : 'System Process',
            strtoupper($log->tenant_id ?? $this->t('audit.node_central', 'CENTRAL')),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [ 1 => ['font' => ['bold' => true, 'size' => 11]] ];
    }
}
