<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AuditActions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * „Jurnal de activitate” (owner, ADR 013): audit_logs al afacerii curente, cel mai nou primul.
 * Global scope-ul BelongsToBusiness limitează la afacerea curentă.
 */
class AuditLogController extends Controller
{
    public const PER_PAGE = 50;

    public function __invoke(Request $request): View
    {
        $category = $request->string('category')->toString();
        $category = array_key_exists($category, AuditActions::CATEGORIES) ? $category : null;

        $logs = AuditLog::query()
            ->when($category !== null, fn ($query) => $query->whereIn('action', AuditActions::inCategory($category)))
            ->with('actor')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('dashboard.audit.index', [
            'logs' => $logs,
            'categories' => AuditActions::CATEGORIES,
            'category' => $category,
        ]);
    }
}
