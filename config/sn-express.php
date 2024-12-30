<?php

// config for Wsmallnews/Express
return [

    'default' => env('SN_EXPRESS_DEFAULT', 'kdniao'),


    'expresses' => [
        'kdniao' => [
            'driver' => 'kdniao',
            'request_type' => 'vip',            // free: 免费版, vip: 付费版/增值版

            // 快递鸟 id & app_key
            'ebusiness_id' => '',
            'app_key' => '',

            // 电子面单账号；电子面单账号对照表，文档地址：https://www.yuque.com/kdnjishuzhichi/dfcrg1/hrfw43
            'customer_name' => '',
            'customer_pwd' => '',
            'month_code' => '',
            'send_site' => '',
            'send_staff' => '',

            // 签约快递公司信息
            'express_code' => '',
            'express_name' => '',

            'pay_type' => '',       // 结算方式  运费支付方式：1：现付；2：到付；3：月结；4：第三方付顺丰跨越；4：回单付京运达
            'exp_type' => '',       // 快递业务类型，文档地址：https://www.yuque.com/kdnjishuzhichi/dfcrg1/hgx758hom5p6wz0l
        ],

        'wechat' => [
            'driver' => 'wechat',
        ],
        'manual' => [
            'driver' => 'manual'
        ],
        'thinkapi' => [
            'driver' => 'thinkapi',
            'app_code' => '123456789'
        ],

    ]
];
