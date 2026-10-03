<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
    ];

    /**
     * Fetch a setting value with a fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Set a setting value, creating it when needed.
     */
    public static function set(string $key, mixed $value, string $group = 'general', ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'group' => $group, 'description' => $description]
        );
    }

    /**
     * Read a setting as a yes/no.
     *
     * The column is a string and the Settings screen writes a checkbox as '0' or
     * '1', so a flag cannot be read as `Setting::get($key)` and compared with `if`:
     * the string '0' is falsy in PHP but 'false' and 'no' - which somebody typing
     * into a text field would produce - are both truthy. Everything that is meant
     * to be a switch goes through here so the one list of strings that count as
     * on is the only one.
     */
    public static function flag(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        return static::isTruthy((string) $value, $default);
    }

    /**
     * Whether one already-read value counts as on.
     *
     * The same rule as flag(), for a caller that already has the row in hand -
     * the Settings screen renders from the settings it loaded rather than asking
     * the database again for every switch it draws.
     *
     * Anything unrecognised falls back to $default rather than to false, so a
     * corrupt value is a visible, explicable thing rather than a silent off.
     */
    public static function isTruthy(?string $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        return match (mb_strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off', '' => false,
            default => $default,
        };
    }

    /**
     * Read a setting as a trimmed line of text.
     *
     * Blank means "not set" throughout the PDF renderer, which falls back to a
     * built-in default rather than printing an empty letterhead.
     */
    public static function text(string $key, ?string $default = null): string
    {
        $value = trim((string) static::get($key, ''));

        return $value === '' ? (string) $default : $value;
    }
}
