<?php

namespace App\Http\Controllers;

use App\Support\Audit\Audit;
use App\Support\Backup\DatabaseBackup;
use App\Support\Security\ActionLimit;
use App\Support\Update\Updater;
use App\Support\Update\UpdateResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;
use Throwable;

/**
 * Azioni dell'amministratore dalla pagina Sistema. Richiedono la conferma
 * della password (middleware password.confirm).
 */
class SystemActionsController extends Controller
{
    public function update(Updater $updater): RedirectResponse
    {
        @set_time_limit(600);
        ignore_user_abort(true);

        $result = $updater->run('web');
        Audit::log('system.update_'.$result->status, null, ($result->from ?? '—').' → '.($result->to ?? '—'));

        return back()->with($result->status === UpdateResult::FAILED ? 'error' : 'status', match ($result->status) {
            UpdateResult::SUCCESS => __('system.update.done', ['from' => $result->from ?? '—', 'to' => $result->to]),
            UpdateResult::UP_TO_DATE => __('system.update.up_to_date', ['version' => $result->to]),
            UpdateResult::FAILED => __('system.update.failed', ['version' => $result->to, 'message' => $result->message]),
            default => (string) $result->message,
        });
    }

    public function retry(Updater $updater): RedirectResponse
    {
        $updater->allowRetry();
        Audit::log('system.update_retry_armed');

        return back()->with('status', __('system.update.retry_armed', ['version' => manutrack_version()]));
    }

    public function backup(DatabaseBackup $backup): RedirectResponse
    {
        try {
            $file = $backup->create('manuale');
            $backup->prune();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('system.backup.failed', ['message' => $e->getMessage()]));
        }
        Audit::log('system.backup_created', null, basename($file));

        return back()->with('status', __('system.backup.created', ['file' => basename($file), 'size' => Number::fileSize((int) filesize($file))]));
    }

    /**
     * Email di prova all'amministratore, inviata subito (non in coda) per vedere l'errore vero
     * del server di posta. Se arriva nello spam: controllare SPF, DKIM e DMARC del dominio.
     */
    public function mailTest(Request $request): RedirectResponse
    {
        $key = ActionLimit::forUser('mail-test');
        if (! ActionLimit::attempt($key, 3, 60)) {
            return back()->with('error', ActionLimit::message($key));
        }
        $to = (string) $request->user()->email;
        $mailer = (string) config('mail.default');
        try {
            Mail::mailer($mailer)->raw(__('system.mail_test.body', ['app' => config('app.url'), 'time' => now()->format('d/m/Y H:i')]), function (Message $m) use ($to): void {
                $m->to($to)->subject(__('system.mail_test.subject'));
            });
        } catch (Throwable $e) {
            report($e);
            Audit::log('system.mail_test_failed', null, $mailer);

            return back()->with('error', __('system.mail_test.failed', ['message' => $e->getMessage()]));
        }
        Audit::log('system.mail_test_sent', null, $mailer);

        // Con "log" o "array" la mail non parte davvero: si dice chiaramente
        if (in_array($mailer, ['log', 'array'], true)) {
            return back()->with('error', __('system.mail_test.not_real', ['mailer' => $mailer]));
        }

        return back()->with('status', __('system.mail_test.sent', ['email' => $to, 'from' => (string) config('mail.from.address')]));
    }
}
