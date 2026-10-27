<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Scelta di una persona per un evento: push sì/no, email no/subito/riepilogo.
 * Un valore vuoto vuol dire "come predefinito".
 *
 * @property int $id
 * @property int $user_id
 * @property string $event
 * @property bool|null $push
 * @property string|null $email
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'event', 'push', 'email'];

    protected function casts(): array
    {
        return ['push' => 'boolean'];
    }
}
