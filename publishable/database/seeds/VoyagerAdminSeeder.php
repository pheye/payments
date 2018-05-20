<?php

use Illuminate\Database\Seeder;

class VoyagerAdminSeeder extends Seeder
{

    /**
     * 填充VoyagerDataTypes
     */
    public function seedDataTypes()
    {
        // 填充DataTypes
        $items = array (
            array (
                'description' => '优惠券功能',
                'display_name_plural' => '优惠券',
                'display_name_singular' => '优惠券',
                'generate_permissions' => 1,
                'icon' => 'voyager-ticket',
                'model_name' => 'Pheye\\Payments\\Models\\Coupon',
                'name' => 'coupons',
                'server_side' => 1,
                'slug' => 'coupons'
            ),

            array (
                'description' => '',
                'display_name_plural' => '退款申请单',
                'display_name_singular' => '退款申请单',
                'generate_permissions' => 1,
                'icon' => '',
                'model_name' => 'Pheye\\Payments\\Models\\Refund',
                'name' => 'refunds',
                'server_side' => 1,
                'slug' => 'refunds'
            ),
            array (
                'description' => '',
                'display_name_plural' => '价格计划',
                'display_name_singular' => '价格计划',
                'generate_permissions' => 1,
                'icon' => '',
                'model_name' => 'Pheye\\Payments\\Models\\Plan',
                'name' => 'plans',
                'server_side' => 0,
                'slug' => 'plans',
            ),

            array (
                'description' => '',
                'display_name_plural' => '网关配置',
                'display_name_singular' => '网关配置',
                'generate_permissions' => 1,
                'icon' => '',
                'model_name' => 'Pheye\\Payments\\Models\\GatewayConfig',
                'name' => 'gateway_configs',
                'server_side' => 0,
                'slug' => 'gateway-configs',
            ),

            array (
                'description' => '',
                'display_name_plural' => 'Payments',
                'display_name_singular' => 'Payment',
                'generate_permissions' => 1,
                'icon' => '',
                'model_name' => 'Pheye\\Payments\\Models\\Payment',
                'name' => 'payments',
                'server_side' => 1,
                'slug' => 'payments',
            ),

            array (
                'description' => '',
                'display_name_plural' => 'Subscriptions',
                'display_name_singular' => 'Subscription',
                'generate_permissions' => 1,
                'icon' => '',
                'model_name' => 'Pheye\\Payments\\Models\\Subscription',
                'name' => 'subscriptions',
                'server_side' => 0,
                'slug' => 'subscriptions',
            ),
        );
        foreach ($items as $key => $item) {
            \DB::table('data_types')->where('name', $item['name'])->delete();
        }
        \DB::table('data_types')->insert($items);

    }

    /**
     * 填充Data Rows
     */
    public function seedDataRows()
    {
        // Data Rows的对应配置
        $items = [
            'coupons' => [
                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Id',
                    'edit' => 0,
                    'field' => 'id',
                    'read' => 0,
                    'required' => 1,
                    'type' => 'PRI',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '名称',
                    'edit' => 1,
                    'field' => 'name',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '折扣码',
                    'edit' => 1,
                    'field' => 'code',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '{"default":"0","options":{"0":"百分比","1":"固定金额"}}',
                    'display_name' => '类型',
                    'edit' => 1,
                    'field' => 'type',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'radio_btn',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '{"description":"如果类型是固定金额，该值为固定金额;如果是百分比，范围应该在10~100，按百分比优惠"}',
                    'display_name' => '折扣',
                    'edit' => 1,
                    'field' => 'discount',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '满减金额',
                    'edit' => 1,
                    'field' => 'total',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '起始日期',
                    'edit' => 1,
                    'field' => 'start',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '结束日期',
                    'edit' => 1,
                    'field' => 'end',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '总数',
                    'edit' => 1,
                    'field' => 'uses',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '每个客户可使用数量',
                    'edit' => 1,
                    'field' => 'customer_uses',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '已使用',
                    'edit' => 0,
                    'field' => 'used',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '{"default":"0","options":{"0":"禁用","1":"启用"}}',
                    'display_name' => '状态',
                    'edit' => 1,
                    'field' => 'status',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'radio_btn',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '创建时间',
                    'edit' => 0,
                    'field' => 'created_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '更新时间',
                    'edit' => 0,
                    'field' => 'updated_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),
                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '{"relationship":{"key":"id","label":"email","method":"user"}}',
                    'display_name' => '用户',
                    'edit' => 1,
                    'field' => 'user_id',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'select_dropdown',
                ),
            ],
            'refunds' => [
                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '编号',
                    'edit' => 0,
                    'field' => 'id',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'PRI',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '支付定单',
                    'edit' => 0,
                    'field' => 'payment_id',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '金额',
                    'edit' => 1,
                    'field' => 'amount',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '{"default":"created","options":{"created":"Created","pending":"Pending","accepted":"accepted","rejected":"rejected"}}',
                    'display_name' => '状态',
                    'edit' => 0,
                    'field' => 'status',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'select_dropdown',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '备注',
                    'edit' => 1,
                    'field' => 'note',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'text_area',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '创建时间',
                    'edit' => 0,
                    'field' => 'created_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '更新时间',
                    'edit' => 0,
                    'field' => 'updated_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '支付网关退款编号',
                    'edit' => 0,
                    'field' => 'remote_number',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'text',
                ),
            ],
            'plans' => [
                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Id',
                    'edit' => 0,
                    'field' => 'id',
                    'read' => 0,
                    'required' => 1,
                    'type' => 'PRI',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '{"relationship":{"key":"id","label":"display_name","method":"role"}}',
                    'display_name' => '角色',
                    'edit' => 1,
                    'field' => 'role_id',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'select_dropdown',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '唯一标识',
                    'edit' => 1,
                    'field' => 'name',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '显示名称',
                    'edit' => 1,
                    'field' => 'display_name',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '描述',
                    'edit' => 1,
                    'field' => 'desc',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text_area',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '显示顺序',
                    'edit' => 1,
                    'field' => 'display_order',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '{"default":"REGULAR"}',
                    'display_name' => '类型',
                    'edit' => 1,
                    'field' => 'type',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '计费周期类型',
                    'edit' => 1,
                    'field' => 'frequency',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '计费周期',
                    'edit' => 1,
                    'field' => 'frequency_interval',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '扣费次数',
                    'edit' => 1,
                    'field' => 'cycles',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '{"step":0.01}',
                    'display_name' => '价格',
                    'edit' => 1,
                    'field' => 'amount',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '货币类型',
                    'edit' => 1,
                    'field' => 'currency',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Paypal Id',
                    'edit' => 1,
                    'field' => 'paypal_id',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '创建时间',
                    'edit' => 0,
                    'field' => 'created_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Updated At',
                    'edit' => 0,
                    'field' => 'updated_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '建立费用',
                    'edit' => 1,
                    'field' => 'setup_fee',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'number',
                ),

                array (
                    'add' => 1,
                    'browse' => 0,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => '延迟天数',
                    'edit' => 1,
                    'field' => 'delay_days',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'number',
                )
            ],
            'gateway_configs' => [
                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Id',
                    'edit' => 0,
                    'field' => 'id',
                    'read' => 0,
                    'required' => 1,
                    'type' => 'PRI',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => 'Gateway Name',
                    'edit' => 1,
                    'field' => 'gateway_name',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '',
                    'display_name' => 'Factory Name',
                    'edit' => 1,
                    'field' => 'factory_name',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 1,
                    'browse' => 1,
                    'delete' => 1,
                    'details' => '{"json":true,"description":"Must be strict json string"}',
                    'display_name' => 'Config',
                    'edit' => 1,
                    'field' => 'config',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text_area',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Created At',
                    'edit' => 0,
                    'field' => 'created_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Updated At',
                    'edit' => 0,
                    'field' => 'updated_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),
            ],
            'payments' => [
                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '金额',
                    'edit' => 0,
                    'field' => 'amount',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '买家Email',
                    'edit' => 0,
                    'field' => 'buyer_email',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Client Email',
                    'edit' => 0,
                    'field' => 'client_email',
                    'read' => 0,
                    'required' => 1,
                    'type' => 'checkbox',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '{"relationship":{"key":"id","label":"email","method":"client"}}',
                    'display_name' => 'Client Id',
                    'edit' => 0,
                    'field' => 'client_id',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'select_dropdown',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '创建时间',
                    'edit' => 0,
                    'field' => 'created_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '货币',
                    'edit' => 0,
                    'field' => 'currency',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Description',
                    'edit' => 0,
                    'field' => 'description',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Details',
                    'edit' => 0,
                    'field' => 'details',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text_area',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Id',
                    'edit' => 0,
                    'field' => 'id',
                    'read' => 0,
                    'required' => 1,
                    'type' => 'PRI',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Invoice Id',
                    'edit' => 0,
                    'field' => 'invoice_id',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => '订单号',
                    'edit' => 0,
                    'field' => 'number',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Status',
                    'edit' => 0,
                    'field' => 'status',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'text',
                ),

                array (
                    'add' => 0,
                    'browse' => 1,
                    'delete' => 0,
                    'details' => '{"relationship":{"key":"id","label":"plan","method":"subscription"}}',
                    'display_name' => 'Subscription Id',
                    'edit' => 0,
                    'field' => 'subscription_id',
                    'read' => 1,
                    'required' => 1,
                    'type' => 'select_dropdown',
                ),

                array (
                    'add' => 0,
                    'browse' => 0,
                    'delete' => 0,
                    'details' => '',
                    'display_name' => 'Updated At',
                    'edit' => 0,
                    'field' => 'updated_at',
                    'read' => 1,
                    'required' => 0,
                    'type' => 'timestamp',
                ),
            ]
        ];
        foreach ($items as $key => $rows) {
            $dataTypeId = \DB::table('data_types')->where('name', $key)->value('id');
            foreach ($rows as $key => $row) {
                $rows[$key]['data_type_id'] = $dataTypeId;
            }
            \DB::table('data_rows')->insert($rows);
        }
    }
    /**
     * 填充权限
     */
    public function seedPermissions()
    {
        // 权限移植
        $items = [
            'coupons' => [
                array (
                    'desc' => '市场营销-优惠券模块访问权限',
                    'key' => 'browse_coupons',
                    'order' => 0,
                    'table_name' => 'coupons',
                    'type' => 0,
                ),

                array (
                    'desc' => '读取优惠券权限',
                    'key' => 'read_coupons',
                    'order' => 0,
                    'table_name' => 'coupons',
                    'type' => 0,
                ),

                array (
                    'desc' => '编辑优惠券权限',
                    'key' => 'edit_coupons',
                    'order' => 0,
                    'table_name' => 'coupons',
                    'type' => 0,
                ),

                array (
                    'desc' => '新增优惠券权限',
                    'key' => 'add_coupons',
                    'order' => 0,
                    'table_name' => 'coupons',
                    'type' => 0,
                ),

                array (
                    'desc' => '删除优惠券权限',
                    'key' => 'delete_coupons',
                    'order' => 0,
                    'table_name' => 'coupons',
                    'type' => 0,
                )
            ],
            'refunds' => [
                array (
                    'desc' => '退款申请单模块访问权限',
                    'key' => 'browse_refunds',
                    'order' => 0,
                    'table_name' => 'refunds',
                    'type' => 0,
                ),

                array (
                    'desc' => '读取退款申请单权限',
                    'key' => 'read_refunds',
                    'order' => 0,
                    'table_name' => 'refunds',
                    'type' => 0,
                ),

                array (
                    'desc' => '修改退款申请单权限',
                    'key' => 'edit_refunds',
                    'order' => 0,
                    'table_name' => 'refunds',
                    'type' => 0,
                ),

                array (
                    'desc' => '新增退款申请单权限',
                    'key' => 'add_refunds',
                    'order' => 0,
                    'table_name' => 'refunds',
                    'type' => 0,
                ),

                array (
                    'desc' => '删除退款申请单权限，删除后买家可以再次发起退款申请',
                    'key' => 'delete_refunds',
                    'order' => 0,
                    'table_name' => 'refunds',
                    'type' => 0,
                ),
            ],
            'plans' => [
                array (
                    'desc' => '市场营销-价格计划模块访问权限',
                    'key' => 'browse_plans',
                    'order' => 0,
                    'table_name' => 'plans',
                    'type' => 0,
                ),

                array (
                    'desc' => '读取价格计划权限',
                    'key' => 'read_plans',
                    'order' => 0,
                    'table_name' => 'plans',
                    'type' => 0,
                ),

                array (
                    'desc' => '编辑价格计划权限',
                    'key' => 'edit_plans',
                    'order' => 0,
                    'table_name' => 'plans',
                    'type' => 0,
                ),

                array (
                    'desc' => '新增价格计划权限',
                    'key' => 'add_plans',
                    'order' => 0,
                    'table_name' => 'plans',
                    'type' => 0,
                ),

                array (
                    'desc' => '删除价格计划权限',
                    'key' => 'delete_plans',
                    'order' => 0,
                    'table_name' => 'plans',
                    'type' => 0,
                ),
            ],
            'gateway_configs' => [
                array (
                    'desc' => '支付网关配置访问权限',
                    'key' => 'browse_gateway_configs',
                    'order' => 0,
                    'table_name' => 'gateway_configs',
                    'type' => 0,
                ),

                array (
                    'desc' => '读取网关配置',
                    'key' => 'read_gateway_configs',
                    'order' => 0,
                    'table_name' => 'gateway_configs',
                    'type' => 0,
                ),

                array (
                    'desc' => '编辑网关配置',
                    'key' => 'edit_gateway_configs',
                    'order' => 0,
                    'table_name' => 'gateway_configs',
                    'type' => 0,
                ),

                array (
                    'desc' => '新增网关配置',
                    'key' => 'add_gateway_configs',
                    'order' => 0,
                    'table_name' => 'gateway_configs',
                    'type' => 0,
                ),

                array (
                    'desc' => '删除网关配置',
                    'key' => 'delete_gateway_configs',
                    'order' => 0,
                    'table_name' => 'gateway_configs',
                    'type' => 0,
                ),
            ],
            'payments' => [
                array (
                    'desc' => NULL,
                    'key' => 'browse_payments',
                    'order' => 0,
                    'table_name' => 'payments',
                    'type' => 0,
                ),

                array (
                    'desc' => NULL,
                    'key' => 'read_payments',
                    'order' => 0,
                    'table_name' => 'payments',
                    'type' => 0,
                ),

                array (
                    'desc' => NULL,
                    'key' => 'edit_payments',
                    'order' => 0,
                    'table_name' => 'payments',
                    'type' => 0,
                ),

                array (
                    'desc' => NULL,
                    'key' => 'add_payments',
                    'order' => 0,
                    'table_name' => 'payments',
                    'type' => 0,
                ),

                array (
                    'desc' => NULL,
                    'key' => 'delete_payments',
                    'order' => 0,
                    'table_name' => 'payments',
                    'type' => 0,
                ),
            ]
        ];
        $role = \DB::table('roles')->where('name', 'admin')->first();
        foreach ($items as $key => $rows) {
            \DB::table('permissions')->where('table_name', $key)->delete();
            \DB::table('permissions')->insert($rows);
            $rows = \DB::table('permissions')->where('table_name', $key)->get();
            foreach ($rows as $key => $row) {
                \DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $row->id]);
            }
        }


    }

    /**
     * 填充菜单
     */
    public function seedMenus()
    {
        // menu items移植
        $items = [
            array (
                'color' => '#000000',
                'icon_class' => 'voyager-ticket',
                'menu_id' => 1,
                'order' => 5,
                'parameters' => '',
                'parent_id' => NULL,
                'route' => 'voyager.coupons.index',
                'target' => '_self',
                'title' => '优惠券',
                'url' => '',
            ),

            array (
                'color' => '',
                'icon_class' => 'voyager-hotdog',
                'menu_id' => 1,
                'order' => 6,
                'parameters' => '',
                'parent_id' => NULL,
                'route' => 'voyager.refunds.index',
                'target' => '_self',
                'title' => '退款申请单',
                'url' => '',
            ),
            array (
                'color' => '#000000',
                'icon_class' => 'voyager-receipt',
                'menu_id' => 1,
                'order' => 1,
                'parameters' => '',
                'parent_id' => NULL,
                'route' => 'voyager.plans.index',
                'target' => '_self',
                'title' => '价格计划',
                'url' => '',
            ),
            
            array (
                'color' => '#000000',
                'icon_class' => 'voyager-dollar',
                'menu_id' => 1,
                'order' => 7,
                'parameters' => '',
                'parent_id' => NULL,
                'route' => 'voyager.gateway-configs.index',
                'target' => '_self',
                'title' => '支付网关配置',
                'url' => '',
            ),
            array (
                'color' => '#000000',
                'icon_class' => 'voyager-dollar',
                'menu_id' => 1,
                'order' => 7,
                'parameters' => '',
                'parent_id' => NULL,
                'route' => 'voyager.payments.index',
                'target' => '_self',
                'title' => '订单',
                'url' => '',
            ),
        ];
        foreach ($items as $key => $row) {
            \DB::table('menu_items')->where('route', $row['route'])->delete();
        }
        \DB::table('menu_items')->insert($items);
    }

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $this->seedDataTypes();
        $this->seedDataRows();
        $this->seedPermissions();
        $this->seedMenus();
    }
}
