<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ProgramStatus;
use App\Enums\StampIcon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ProgramRequest;
use App\Http\Requests\Dashboard\ProgramStatusRequest;
use App\Models\Business;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\ProgramService;
use App\Services\Loyalty\ProgramTemplate;
use App\Services\Loyalty\ProgramTemplates;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function __construct(private ProgramService $programs) {}

    public function index(CurrentBusiness $currentBusiness): View
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('viewAny', [Program::class, $business]);

        return view('dashboard.programs.index', [
            'business' => $business,
            'programs' => $business->programs()
                ->withCount('cards')
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->orderBy('name')
                ->get()
                // Culoarea cardului cade pe cea a brandului: afacerea e deja încărcată.
                ->each(fn (Program $program) => $program->setRelation('business', $business)),
        ]);
    }

    public function create(CurrentBusiness $currentBusiness): View
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('create', [Program::class, $business]);

        $templates = ProgramTemplates::orderedFor($business);

        return view('dashboard.programs.create', [
            'business' => $business,
            'program' => new Program($templates[0]->attributes),
            'templates' => array_map(fn (ProgramTemplate $template) => $template->toArray(), $templates),
            'icons' => StampIcon::cases(),
        ]);
    }

    public function store(ProgramRequest $request, CurrentBusiness $currentBusiness): RedirectResponse
    {
        $business = $this->current($currentBusiness);
        Gate::authorize('create', [Program::class, $business]);

        $publish = $request->validated('intent') === 'publish';
        $this->programs->create($this->actor($request), $business, $request->programData(), $publish, $request->validated('template'));

        return redirect()->route('dashboard.programs.index')
            ->with('status', $publish ? 'Programul a fost publicat. Clienții se pot înscrie.' : 'Programul a fost salvat ca ciornă.');
    }

    public function edit(Program $program, CurrentBusiness $currentBusiness): View|RedirectResponse
    {
        Gate::authorize('update', $program);

        // Un program arhivat nu se mai salvează (ProgramService::update), deci nu deschidem wizard-ul.
        if ($program->status === ProgramStatus::Archived) {
            return redirect()->route('dashboard.programs.index')
                ->withErrors(['loyalty' => 'Programul e arhivat și nu mai poate fi modificat. Duplică-l ca să pornești o versiune nouă.']);
        }

        return view('dashboard.programs.edit', [
            'business' => $this->current($currentBusiness),
            'program' => $program->loadCount('cards'),
            'icons' => StampIcon::cases(),
        ]);
    }

    public function update(ProgramRequest $request, Program $program): RedirectResponse
    {
        Gate::authorize('update', $program);

        $this->programs->update($this->actor($request), $program, $request->programData());

        return redirect()->route('dashboard.programs.index')->with('status', 'Programul a fost salvat.');
    }

    public function status(ProgramStatusRequest $request, Program $program): RedirectResponse
    {
        Gate::authorize('update', $program);

        $status = $request->targetStatus();
        $this->programs->changeStatus($this->actor($request), $program, $status);

        return redirect()->route('dashboard.programs.index')->with('status', match ($status) {
            ProgramStatus::Active => 'Programul e activ.',
            ProgramStatus::Paused => 'Programul e pe pauză: clienții nu mai primesc ștampile până îl reiei.',
            default => 'Programul a fost arhivat.',
        });
    }

    public function duplicate(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('update', $program);

        $copy = $this->programs->duplicate($this->actor($request), $program);

        return redirect()->route('dashboard.programs.edit', $copy)->with('status', 'Am creat o copie ca ciornă. Ajustează-o și publică-o.');
    }

    public function destroy(Request $request, Program $program): RedirectResponse
    {
        Gate::authorize('delete', $program);

        $this->programs->delete($this->actor($request), $program);

        return redirect()->route('dashboard.programs.index')->with('status', 'Ciorna a fost ștearsă.');
    }

    private function current(CurrentBusiness $currentBusiness): Business
    {
        return $currentBusiness->get() ?? abort(403);
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
