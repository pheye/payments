<?php

namespace Pheye\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CustomizedInvoice extends Model
{
    protected $fillable = ['user_id', 'company_name', 'address', 'contact_info', 'website', 'tax_no'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 存储限制，一个月只能存储1次,每月1号重置
     * 条件1：非本年
     * 条件2：<本月
     * 符合1个就可存储
     *
     * @return boolean
     */
    public function canSave()
    {
        $old = Carbon::parse($this->updated_at);
        $now = Carbon::now();
        return ($old->month < $now->month) || ($old->year != $now->year);
    }
}
