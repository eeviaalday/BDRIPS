<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The audit log, read only.
 *
 * There is no create, update or delete route for this table, and the absence is
 * the feature: a trail an Admin can edit is not a trail. Every write here comes
 * from AuditLogger, at the moment the state changed.
 *
 * The filters combine rather than replace each other, so "everything this
 * Secretary did last week" is one query instead of a sequence of the operator
 * narrowing by hand.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $action = (string) $request->query('action', '');
        $actorId = $request->query('actor');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $query = AuditLog::query()
            ->with('user')
            // Grouped, so the search term cannot escape the date range or the
            // action filter through an unparenthesised OR.
            ->when($search !== '', fn ($logs) => $logs->where(
                fn ($inner) => $inner
                    ->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"))
            ))
            ->action(in_array($action, array_keys(AuditLog::actions()), true) ? $action : null)
            ->when(is_numeric($actorId), fn ($logs) => $logs->where('user_id', (int) $actorId))
            ->when($from !== '', fn ($logs) => $logs->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to !== '', fn ($logs) => $logs->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('id');

        return view('staff.audit.index', [
            'entries' => $query->paginate(25)->withQueryString(),
            'search' => $search,
            'action' => $action,
            'actorId' => $actorId,
            'from' => $from,
            'to' => $to,
            'actions' => AuditLog::actions(),
            'actors' => User::query()
                ->whereHas('auditLogs')
                ->orderBy('name')
                ->get(['id', 'name']),
            'total' => AuditLog::query()->count(),
        ]);
    }
}
