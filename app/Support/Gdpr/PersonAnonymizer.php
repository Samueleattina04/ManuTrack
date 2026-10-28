<?php

namespace App\Support\Gdpr;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Audit\Audit;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Anonimizzazione di una persona (docs/GDPR.md).
 *
 * - Persona con un account: nome, email, telefono, ruolo e accessi spariscono; resta un
 *   segnaposto "Persona anonimizzata #<id>" con lo stesso id, così ordini, tempi, segnalazioni,
 *   storico e indicatori restano coerenti (chi ha fatto cosa diventa il segnaposto).
 * - Nei testi liberi (descrizioni, note, commenti, registro attività, avvisi, fornitori…)
 *   il nome, l'email e le varianti indicate sono sostituiti dal segnaposto.
 * - Senza account (es. un nome scritto nelle note): si sostituiscono solo i testi.
 *
 * Non tocca: foto e documenti allegati (vanno controllati a mano), copie di sicurezza,
 * file esportati, email già inviate.
 */
final class PersonAnonymizer
{
    /** Testi liberi in cui cercare: tabella => colonne */
    public const TEXT_COLUMNS = [
        'work_orders' => ['title', 'description', 'completion_notes'],
        'work_requests' => ['description', 'triage_note'],
        'work_order_comments' => ['body'],
        'work_order_status_changes' => ['note'],
        'work_order_checklist_items' => ['note'],
        'time_entries' => ['note'],
        'meter_readings' => ['note'],
        'stock_movements' => ['note'],
        'downtime_events' => ['note'],
        'user_notifications' => ['title', 'body'],
        'audit_logs' => ['user_name', 'description', 'changes'],
        'attachments' => ['original_name'],
        'suppliers' => ['contact_name', 'email', 'phone', 'notes'],
        'assets' => ['notes'],
        'maintenance_plans' => ['description'],
        'invitations' => ['name', 'email'],
    ];

    public const MIN_TERM = 3;

    /**
     * Parole da cercare: nome ed email della persona (se c'è) più le varianti indicate.
     *
     * @param  list<string>  $extra
     * @return list<string>
     */
    public static function terms(?User $user, array $extra): array
    {
        $terms = $user ? [$user->name, $user->email, ...array_filter([$user->phone])] : [];
        foreach ($extra as $t) {
            $terms[] = $t;
        }
        $terms = array_values(array_unique(array_filter(array_map(fn ($t) => trim((string) $t), $terms), fn ($t) => mb_strlen($t) >= self::MIN_TERM)));
        // Le più lunghe prima: "Mario Rossi" va sostituito prima di "Rossi"
        usort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $terms;
    }

    /**
     * Dove compaiono le parole: "tabella.colonna" => righe.
     *
     * @param  list<string>  $terms
     * @return array<string, int>
     */
    public function preview(array $terms): array
    {
        $out = [];
        foreach (self::TEXT_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $n = $this->matching($table, $column, $terms)->count();
                if ($n > 0) {
                    $out[$table.'.'.$column] = $n;
                }
            }
        }

        return $out;
    }

    /**
     * Anonimizza. $actor null = procedura automatica (conservazione dei dati).
     *
     * @param  list<string>  $extraTerms
     * @return array{pseudonym: string, replaced: array<string, int>}
     */
    public function anonymize(?User $user, array $extraTerms, ?User $actor): array
    {
        if ($actor && ! $actor->hasRole(Access::ADMIN)) {
            throw ValidationException::withMessages(['user' => __('gdpr.anonymize.admin_only')]);
        }
        if ($user) {
            if ($actor && $user->is($actor)) {
                throw ValidationException::withMessages(['user' => __('gdpr.anonymize.not_self')]);
            }
            if ($user->is_active) {
                throw ValidationException::withMessages(['user' => __('gdpr.anonymize.deactivate_first')]);
            }
            if ($user->anonymized_at !== null) {
                throw ValidationException::withMessages(['user' => __('gdpr.anonymize.already')]);
            }
        }
        $terms = self::terms($user, $extraTerms);
        if ($terms === []) {
            throw ValidationException::withMessages(['terms' => __('gdpr.anonymize.no_terms', ['min' => self::MIN_TERM])]);
        }
        $pseudonym = $user ? __('gdpr.anonymize.pseudonym', ['id' => $user->id]) : __('gdpr.anonymize.pseudonym_generic');

        return DB::transaction(function () use ($user, $terms, $pseudonym, $actor) {
            $replaced = [];
            foreach (self::TEXT_COLUMNS as $table => $columns) {
                foreach ($columns as $column) {
                    $n = 0;
                    foreach ($this->matching($table, $column, $terms)->get(['id', $column]) as $row) {
                        $value = (string) $row->{$column};
                        $new = $column === 'changes' ? $this->replaceJson($value, $terms, $pseudonym) : self::replace($value, $terms, $pseudonym);
                        if ($column === 'email' && $new !== $value) {
                            $new = 'anonimo-'.Str::lower(Str::random(8)).'@anonimo.invalid';
                        }
                        if ($new !== $value) {
                            DB::table($table)->where('id', $row->id)->update([$column => $new]);
                            $n++;
                        }
                    }
                    if ($n > 0) {
                        $replaced[$table.'.'.$column] = $n;
                    }
                }
            }

            if ($user) {
                // Righe del registro fatte dalla persona: niente indirizzo IP né browser
                $replaced['audit_logs (ip, browser)'] = AuditLog::query()->where('user_id', $user->id)->update(['user_name' => $pseudonym, 'ip' => null, 'user_agent' => null]);
                $oldEmail = $user->email;
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('push_subscriptions')->where('user_id', $user->id)->delete();
                DB::table('notification_preferences')->where('user_id', $user->id)->delete();
                DB::table('user_notifications')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
                $user->syncRoles([]);
                $user->syncPermissions([]);
                $user->forceFill([
                    'name' => $pseudonym,
                    'email' => 'anonimo-'.$user->id.'@anonimo.invalid',
                    'job_title' => null,
                    'phone' => null,
                    'locale' => null,
                    'password' => bcrypt(Str::random(64)),
                    'remember_token' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                    'email_verified_at' => null,
                    'last_login_at' => null,
                    'is_active' => false,
                    'anonymized_at' => now(),
                ])->save();
            }

            // Nel registro solo il segnaposto e i conteggi, mai i dati tolti
            Audit::log('users.anonymized', $user, $pseudonym, ['sostituzioni' => $replaced, 'parole_cercate' => count($terms)], $actor);

            return ['pseudonym' => $pseudonym, 'replaced' => $replaced];
        });
    }

    /**
     * @param  list<string>  $terms
     */
    public static function replace(string $value, array $terms, string $with): string
    {
        foreach ($terms as $term) {
            $value = (string) preg_replace('/'.preg_quote($term, '/').'/iu', $with, $value);
        }

        return $value;
    }

    /**
     * Sostituzione dentro un JSON (registro attività): sui valori di testo, a qualunque profondità.
     *
     * @param  list<string>  $terms
     */
    private function replaceJson(string $json, array $terms, string $with): string
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return self::replace($json, $terms, $with);
        }
        array_walk_recursive($data, function (&$v) use ($terms, $with) {
            if (is_string($v)) {
                $v = self::replace($v, $terms, $with);
            }
        });
        $new = (string) json_encode($data);

        return $new === json_encode(json_decode($json, true)) ? $json : $new;
    }

    /**
     * Righe in cui la colonna contiene una delle parole (senza distinguere maiuscole; per i
     * JSON anche nella forma con i caratteri accentati codificati).
     *
     * @param  list<string>  $terms
     */
    private function matching(string $table, string $column, array $terms): Builder
    {
        return DB::table($table)->where(function ($q) use ($column, $terms) {
            foreach ($terms as $term) {
                foreach (array_unique([$term, trim((string) json_encode($term), '"')]) as $form) {
                    $q->orWhereRaw('LOWER(CAST('.$column.' AS CHAR)) LIKE ?', ['%'.addcslashes(mb_strtolower($form), '%_\\').'%']);
                }
            }
        });
    }
}
