<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Admin\Services\DatabaseBackupService;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Lets a Super Admin download the database. The file holds every customer's
 * details and every staff login, so this is limited to Super Admins, asks for
 * the password again, and records who took each one.
 */
final class BackupController extends Controller
{
    private const string LOG_NAME = 'backup';

    public function index(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $backups = Activity::query()->inLog(self::LOG_NAME)->with('causer')->latest('id')->limit(10)->get();

        return view('admin::admin.backups.index', compact('backups'));
    }

    public function store(Request $request, DatabaseBackupService $service): BinaryFileResponse|RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $request->validate(['password' => ['required', 'current_password']]);

        // A small database takes seconds, but the backup must not be cut off part way.
        set_time_limit(0);

        try {
            $path = $service->create();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => __('The backup could not be created, so nothing was downloaded. Please try again.')]);
        }

        $createdAt = now()->timezone(config('booking.business_timezone'));
        $fileName = Str::slug(config('app.name')).'-database-'.$createdAt->format('Y-m-d-His').'.sql.gz';

        activity(self::LOG_NAME)
            ->causedBy($request->user())
            ->withProperties(['file' => $fileName, 'bytes' => filesize($path), 'ip' => $request->ip()])
            ->log('Database backup downloaded');

        return response()->download($path, $fileName, ['Content-Type' => 'application/gzip'])->deleteFileAfterSend();
    }
}
