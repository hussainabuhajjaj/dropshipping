<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key', 'value'
    ];

    public static function setSetting($data)
    {

        foreach ($data as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        return true;
    }

    public function key($type)
    {
        return $this->where('key', $type)->first();
    }

    public function valueOf($type , $default = null)
    {
        return (isset($this->key($type)->value)) ? $this->key($type)->value : $default;
    }
}
