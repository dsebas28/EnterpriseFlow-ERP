<?php

namespace App\Jobs;

use App\Jobs\Concerns\TenantAware;
use App\Models\Company;
use App\Models\ReportExport;
use App\Notifications\ReportExportFinished;
use App\Reports\Export\ReportWriter;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds a report file outside the HTTP request and records its progress
 * on the ReportExport row (pending → processing → completed | failed).
 */
class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TenantAware;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly string $exportId)
    {
        $this->captureTenant();
        $this->onQueue('reports');
    }

    public function handle(ReportRegistry $registry, ReportWriter $writer, TenantContext $tenant): void
    {
        $export = ReportExport::findOrFail($this->exportId);
        $export->forceFill(['status' => 'processing'])->save();

        $company = $tenant->companyOrFail();
        $report = $registry->findOrFail($export->report);
        $filters = ReportFilters::fromArray($export->filters, now($company->timezone)->toDateString());

        $result = $writer->write($report, $filters, $export->format, $company);

        $path = "companies/{$company->id}/exports/{$export->report}-{$export->id}.{$export->format}";
        $stream = fopen($result['path'], 'r');
        Storage::disk(ReportExport::DISK)->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($result['path']);

        $export->forceFill([
            'status' => 'completed',
            'file_path' => $path,
            'rows_count' => $result['rows'],
            'completed_at' => now(),
        ])->save();

        Log::channel('queue')->info('report.exported', ['export_id' => $export->id, 'report' => $export->report, 'rows' => $result['rows']]);

        $this->notifyRequester($export, $report->title(), $company);
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('queue')->error('report.export_failed', ['export_id' => $this->exportId, 'error' => $exception->getMessage()]);

        $this->inTenant(function (Company $company) use ($exception): void {
            $export = ReportExport::find($this->exportId);

            if ($export === null) {
                return;
            }

            $export->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 250)])->save();

            $title = app(ReportRegistry::class)->find($export->report)?->title() ?? $export->report;
            $this->notifyRequester($export, $title, $company);
        });
    }

    /**
     * Personal notification (the export belongs to whoever requested it).
     */
    private function notifyRequester(ReportExport $export, string $reportTitle, Company $company): void
    {
        rescue(function () use ($export, $reportTitle, $company): void {
            $user = $export->user;

            if ($user?->isActive()) {
                $user->notify(new ReportExportFinished($company, $export, $reportTitle));
            }
        }, report: true);
    }
}
