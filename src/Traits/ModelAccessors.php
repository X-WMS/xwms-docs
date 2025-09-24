<?php

namespace XWMS\Package\Traits;

use Illuminate\Support\Str;

trait ModelAccessors
{
    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'get')) {
            $attribute = Str::snake(Str::after($method, 'get'));

            // Bestaat het veld in $fillable of $attributes?
            if (
                (property_exists($this, 'fillable') && in_array($attribute, $this->fillable)) ||
                array_key_exists($attribute, $this->attributes)
            ) {
                return $this->attributes[$attribute] ?? null;
            }
        }

        return parent::__call($method, $parameters);
    }
}
