<?php

namespace Pheye\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    //
    protected $fillable = ['name', 'display_name', 'desc', 'display_order', 'type', 'frequency', 'frequency_interval', 'cycles', 'amount', 'currency', 'role_id'];

    public function role()
    {
        return $this->belongsTo(config('payment.models.role', 'App\Role'));
    }
}
