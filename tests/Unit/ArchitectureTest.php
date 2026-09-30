<?php

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/*
| Architectural rules that code review would otherwise have to remember.
*/

arch('no debugging or unsafe PHP functions')
    ->preset()->php();

arch('no insecure functions (md5, sha1, rand, eval, exec...)')
    ->preset()->security()
    // ReportWriter: tempnam() atomically creates a uniquely named file for
    // the queued export (not a predictable name), then streams it to storage.
    ->ignoring('tempnam');

arch('env() is only read in config files, so config caching works')
    ->expect('env')
    ->toOnlyBeUsedIn('config');

arch('controllers stay thin: no direct SQL')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('the domain layer does not depend on HTTP')
    ->expect(['App\Actions', 'App\Services', 'App\Reports', 'App\Support\Money', 'App\Support\Tenancy', 'App\DTOs'])
    ->not->toUse(['Illuminate\Http\Request', 'App\Http\Controllers', 'Inertia\Inertia']);

arch('actions are final, single-purpose classes')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal();

arch('DTOs are immutable')
    ->expect('App\DTOs')
    ->classes()
    ->toBeReadonly();

arch('domain events fire only after the transaction commits')
    ->expect('App\Events')
    ->classes()
    ->toImplement(ShouldDispatchAfterCommit::class);

arch('jobs are queued')
    ->expect('App\Jobs')
    ->classes()
    ->toImplement(ShouldQueue::class)
    ->ignoring(['App\Jobs\Concerns', 'App\Jobs\Middleware']);

arch('notifications are queued')
    ->expect('App\Notifications')
    ->classes()
    ->toImplement(ShouldQueue::class)
    ->ignoring('App\Notifications\Channels');

arch('models are Eloquent models')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('form requests, resources and policies follow conventions')
    ->expect('App\Http\Requests')->classes()->toExtend(FormRequest::class)
    ->and('App\Http\Resources')->classes()->toHaveSuffix('Resource')
    // MoneyResource is a static array factory on purpose: listings serialize
    // thousands of amounts and do not need a JsonResource per value.
    ->and('App\Http\Resources')->classes()->toExtend(JsonResource::class)->ignoring('App\Http\Resources\MoneyResource')
    ->and('App\Policies')->classes()->toHaveSuffix('Policy');

arch('controllers are suffixed')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller');
