<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSetting extends Model
{
    use HasFactory;

    protected $table = 'customer_settings';

    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'updated_by',
    ];

    /**
     * Get a setting value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('setting_key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return self::castValue($setting->setting_value, $setting->setting_type);
    }

    /**
     * Set a setting value by key
     */
    public static function setValue(string $key, $value, string $type = 'string', string $description = null, $updatedBy = null)
    {
        return self::updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => (string) $value,
                'setting_type' => $type,
                'description' => $description,
                'updated_by' => $updatedBy,
            ]
        );
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'decimal':
            case 'float':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
            case 'array':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }

    /**
     * Get the user who updated this setting
     */
    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
