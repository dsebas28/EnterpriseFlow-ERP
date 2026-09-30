<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Company;
use App\Models\ReportExport;

class ReportExportFinished extends CompanyNotification
{
    public function __construct(Company $company, ReportExport $export, string $reportTitle)
    {
        $format = strtoupper($export->format);
        $succeeded = $export->status === 'completed';

        parent::__construct(
            $company,
            title: $succeeded ? "Your {$format} export is ready" : "Your {$format} export failed",
            body: $succeeded
                ? "{$reportTitle}: {$export->rows_count} rows."
                : "{$reportTitle} could not be generated. Please try again.",
            url: $succeeded ? route('reports.exports.download', $export) : route('reports.show', $export->report),
            level: $succeeded ? 'success' : 'danger',
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::ReportExport;
    }
}
