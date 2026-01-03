<?php

namespace XWMS\Package\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class XwmsConnection extends Model
{
    protected $table = 'xwms_connection';

    protected $fillable = [
        'user_id',
        'sub',
    ];

    public function user()
    {
        $userClass = config('xwms.models.User', '\\App\\Models\\User');
        return $this->belongsTo($userClass);
    }

    public static function findBySub(string $sub): ?self
    {
        return static::where('sub', $sub)->first();
    }

    public static function connectUser(object $user, string $sub): self
    {
        $existing = static::where('sub', $sub)->first();
        if ($existing && $existing->user_id !== $user->id) {
            $existing->user_id = $user->id;
            $existing->save();
            return $existing;
        }

        return static::firstOrCreate([
            'sub' => $sub,
        ], [
            'user_id' => $user->id,
        ]);
    }

    public static function getSubForAuthenticatedUser(): ?string
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        return static::where('user_id', $user->id)->value('sub');
    }
}
