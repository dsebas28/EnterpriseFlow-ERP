<?php

use App\Enums\WebhookEventStatus;
use App\Models\ReportExport;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

it('schedules the maintenance tasks, each guarded against double runs', function () {
    $events = collect(app(Schedule::class)->events());

    $scheduled = fn (string $command): ?Event => $events->first(fn (Event $event) => str_contains((string) $event->command, $command));

    foreach (['invoices:mark-overdue', 'sanctum:prune-expired', 'model:prune', 'queue:prune-failed', 'auth:clear-resets'] as $command) {
        expect($scheduled($command))->not->toBeNull("{$command} is not scheduled")
            ->and($scheduled($command)->onOneServer)->toBeTrue();
    }

    expect($scheduled('invoices:mark-overdue')->expression)->toBe('0 * * * *')
        ->and($scheduled('invoices:mark-overdue')->withoutOverlapping)->toBeTrue();
});

it('prunes finished webhook events past retention but keeps failed ones', function () {
    $make = function (WebhookEventStatus $status, int $daysAgo, string $id): void {
        $event = new WebhookEvent;
        $event->forceFill([
            'provider' => 'payments',
            'external_id' => $id,
            'type' => 'payment.succeeded',
            'payload' => [],
            'status' => $status,
            'received_at' => now()->subDays($daysAgo),
        ])->save();
    };

    $make(WebhookEventStatus::Processed, 91, 'old-processed');
    $make(WebhookEventStatus::Ignored, 91, 'old-ignored');
    $make(WebhookEventStatus::Failed, 91, 'old-failed');
    $make(WebhookEventStatus::Processed, 10, 'recent');

    $this->artisan('model:prune', ['--model' => [WebhookEvent::class]])->assertSuccessful();

    expect(WebhookEvent::pluck('external_id')->sort()->values()->all())->toBe(['old-failed', 'recent']);
});

it('prunes old report exports across all companies, deleting their files', function () {
    Storage::fake(ReportExport::DISK);
    [$user, $companyA] = companyWithMember();
    [, $companyB] = companyWithMember(User::factory()->create());

    $make = function ($company, int $daysAgo, string $path) use ($user): ReportExport {
        Storage::disk(ReportExport::DISK)->put($path, 'data');

        return tenant()->run($company, function () use ($user, $daysAgo, $path) {
            $export = new ReportExport;
            $export->forceFill([
                'user_id' => $user->id,
                'report' => 'sales-by-period',
                'format' => 'csv',
                'filters' => [],
                'status' => 'completed',
                'file_path' => $path,
            ])->save();
            $export->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

            return $export;
        });
    };

    $make($companyA, 8, 'a/old.csv');
    $make($companyB, 8, 'b/old.csv');
    $recent = $make($companyA, 1, 'a/recent.csv');

    // Runs without a tenant, like the scheduler.
    $this->artisan('model:prune', ['--model' => [ReportExport::class]])->assertSuccessful();

    Storage::disk(ReportExport::DISK)->assertMissing(['a/old.csv', 'b/old.csv']);
    Storage::disk(ReportExport::DISK)->assertExists('a/recent.csv');
    expect(tenant()->run($companyA, fn () => ReportExport::pluck('id')->all()))->toBe([$recent->id])
        ->and(tenant()->run($companyB, fn () => ReportExport::count()))->toBe(0);
});
