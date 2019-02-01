<?php

namespace Pheye\Payments\Http\Controllers;

use Illuminate\Http\Request;
use Pheye\Payments\Models\Coupon;

class CouponController extends Controller
{
    /**
     * 查询
     */
    public function index(Request $request)
    {
        if (!$request->has('filter')) {
            throw new \Exception("no filter parameter");
        }

        $filter = $request->filter;
        if (!isset($filter['code'])) {
            throw new \Exception('code filter is required');
        }
        return Coupon::where('code', $filter['code'])->get();
    }
}
