<?php

namespace App\Services\Loyalty;

use App\Enums\ProgramStatus;
use App\Exceptions\ProgramRuleViolation;
use App\Models\Business;
use App\Models\Program;
use App\Models\User;
use App\Services\Loyalty\Concerns\RequiresOwner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Programele de fidelizare ale afacerii (SDD §7). Statusul se schimbă doar prin `changeStatus()`,
 * cu tranzițiile din `ProgramStatus::transitions()` (ADR 008); regulile programului se pot edita cât timp nu e arhivat.
 */
class ProgramService
{
    use RequiresOwner;

    /**
     * Câmpurile pe care le poate seta ownerul din wizard. `business_id` și `status` nu sunt printre ele.
     */
    public const FIELDS = [
        'name',
        'stamps_required',
        'reward_title',
        'reward_description',
        'max_stamps_per_visit',
        'cooldown_minutes',
        'daily_limit',
        'stamps_expire_days',
        'reward_expire_days',
        'rollover_extra',
        'color',
        'stamp_icon',
        'terms',
    ];

    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Business $business, array $data, bool $publish = false, ?string $template = null): Program
    {
        $this->authorizeOwner($actor, $business);

        if ($publish) {
            $this->ensureBusinessActive($business);
        }

        return DB::transaction(function () use ($actor, $business, $data, $publish, $template): Program {
            $program = new Program(Arr::only($data, self::FIELDS));
            $program->business_id = $business->id;
            $program->status = $publish ? ProgramStatus::Active : ProgramStatus::Draft;
            $program->save();

            $this->audit->recordForBusiness($actor, $business, 'program.created', $program, [
                'name' => $program->name,
                'status' => $program->status->value,
                'template' => $template,
            ]);

            return $program;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Program $program, array $data): Program
    {
        $this->authorizeOwner($actor, $program->business_id);

        return DB::transaction(function () use ($actor, $program, $data): Program {
            // Lock pe program: verificarea cardurilor și salvarea nu se intercalează cu altă editare.
            $locked = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === ProgramStatus::Archived) {
                throw new ProgramRuleViolation('Programul e arhivat și nu mai poate fi modificat. Duplică-l ca să pornești o versiune nouă.');
            }

            $locked->fill(Arr::only($data, self::FIELDS));

            if ($locked->stamps_required < (int) $locked->getOriginal('stamps_required') && $locked->cards()->exists()) {
                throw new ProgramRuleViolation('Programul are deja carduri emise: nu poți micșora numărul de ștampile. Arhivează-l și creează unul nou.');
            }

            $changes = array_keys($locked->getDirty());
            $locked->save();

            $this->audit->recordForBusiness($actor, $locked->business_id, 'program.updated', $locked, ['changed' => $changes]);

            return $locked;
        });
    }

    public function changeStatus(User $actor, Program $program, ProgramStatus $to): Program
    {
        $this->authorizeOwner($actor, $program->business_id);

        return DB::transaction(function () use ($actor, $program, $to): Program {
            $locked = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canBecome($to)) {
                throw new ProgramRuleViolation($this->transitionMessage($from, $to));
            }

            if ($to === ProgramStatus::Active) {
                $this->ensureBusinessActive($locked->business);
            }

            $locked->status = $to;
            $locked->save();

            $this->audit->recordForBusiness($actor, $locked->business_id, 'program.status_changed', $locked, [
                'from' => $from->value,
                'to' => $to->value,
            ]);

            return $locked;
        });
    }

    /**
     * Drumul pentru o „versiune nouă” (SDD §7): aceleași reguli și aspect, într-o ciornă fără carduri.
     */
    public function duplicate(User $actor, Program $program): Program
    {
        $this->authorizeOwner($actor, $program->business_id);

        return DB::transaction(function () use ($actor, $program): Program {
            $suffix = ' (copie)';

            $copy = new Program(Arr::only($program->getAttributes(), self::FIELDS));
            $copy->name = mb_substr($program->name, 0, 120 - mb_strlen($suffix)).$suffix;
            $copy->business_id = $program->business_id;
            $copy->status = ProgramStatus::Draft;
            $copy->save();

            $this->audit->recordForBusiness($actor, $program->business_id, 'program.duplicated', $copy, ['source_id' => $program->id]);

            return $copy;
        });
    }

    /**
     * Doar o ciornă fără carduri se șterge. Un program folosit se arhivează, ca istoricul să rămână.
     */
    public function delete(User $actor, Program $program): void
    {
        $this->authorizeOwner($actor, $program->business_id);

        try {
            DB::transaction(function () use ($actor, $program): void {
                $locked = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->status !== ProgramStatus::Draft || $locked->cards()->exists()) {
                    throw $this->inUse();
                }

                $this->audit->recordForBusiness($actor, $locked->business_id, 'program.deleted', $locked, ['name' => $locked->name]);
                $locked->delete();
            });
        } catch (QueryException $e) {
            // Un card apărut între verificare și ștergere: FK-ul restrict o oprește.
            if ($e->getCode() === '23503') {
                throw $this->inUse();
            }

            throw $e;
        }
    }

    private function ensureBusinessActive(Business $business): void
    {
        if (! $business->isActive()) {
            throw new ProgramRuleViolation('Afacerea e suspendată: programele nu pot fi publicate.');
        }
    }

    private function transitionMessage(ProgramStatus $from, ProgramStatus $to): string
    {
        return match (true) {
            $from === ProgramStatus::Archived => 'Programul e arhivat. Duplică-l ca să pornești o versiune nouă.',
            $from === $to => 'Programul are deja acest status.',
            $to === ProgramStatus::Draft => 'Un program publicat nu mai poate redeveni ciornă.',
            default => 'Programul nu poate trece din această stare în cea cerută.',
        };
    }

    private function inUse(): ProgramRuleViolation
    {
        return new ProgramRuleViolation('Doar o ciornă fără carduri poate fi ștearsă. Arhivează programul în loc.');
    }
}
