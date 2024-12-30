<?php

namespace Wsmallnews\Express\Adapters;

use Wsmallnews\Express\Contracts\AdapterInterface;

// use Wsmallnews\Express\Exceptions\delivery\{
//     service\KdniaoService,
//     model\ExpressPackage,
// };

class KdniaoAdapter implements AdapterInterface
{
    public $status = [
        '0' => 'noinfo',
        '10' => 'waiting',
        '1' => 'collect',
        '2' => 'transport',
        '201' => 'transport',               // 到达派件城市
        '202' => 'delivery',                // 派件中
        '204' => 'transport',               // 到达转运中心
        '205' => 'transport',               // 到达派件网点
        '206' => 'transport',               // 寄件网点发件
        '211' => 'delivery',                // 已放入快递柜或驿站
        '3' => 'signfor',
        '301' => 'signfor',                 // 正常签收
        '302' => 'signfor',                 // 轨迹异常后最终签收
        '304' => 'signfor',                 // 代收签收
        '311' => 'signfor',                 // 快递柜或驿站签收
        '4' => 'difficulty',                    // 问题件
        '401' => 'invalid',                 // 发货无信息
        '402' => 'timeout',                 // 超时未签收
        '403' => 'timeout',                 // 超时未更新
        '404' => 'refuse',                  // 拒收(退件)
        '405' => 'difficulty',              // 派件异常
        '406' => 'difficulty',              // 退货签收
        '407' => 'difficulty',              // 退货未签收
        '412' => 'difficulty',              // 快递柜或驿站超时未取
        '413' => 'difficulty',              // 单号已拦截
        '414' => 'difficulty',              // 破损
        '415' => 'difficulty',              // 客户取消发货
        '416' => 'difficulty',              // 无法联系
        '417' => 'difficulty',              // 配送延迟
        '418' => 'difficulty',              // 快件取出
        '419' => 'difficulty',              // 重新派送
        '420' => 'difficulty',              // 收货地址不详细
        '421' => 'difficulty',              // 收件人电话错误
        '422' => 'difficulty',              // 错分件
        '423' => 'difficulty',              // 超区件
        '5' => 'sendon',                        // 转寄
        '6' => 'customs_clearance',             // 清关
        '601' => 'waiting_customs_clearance',   // 待清关
        '602' => 'ing_customs_clearance',   // 清关中
        '603' => 'customs_clearanced',      // 已清关
        '604' => 'customs_clearance_err',    // 清关异常
    ];

    /**
     * 配置数组
     */
    public array $config = [];

    /**
     * 微信物流快递服务
     *
     * @var WechatDeliveryService
     */
    public $service = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;

        $this->service = new KdniaoService($config);
    }

    /**
     * 获取当前驱动名
     */
    public function getType(): string
    {
        return 'kdniao';
    }

    /**
     * 查询物流轨迹
     *
     * @param  array  $params
     */
    public function query($params): array
    {
        $baseInfo = $params['base_info'] ?? [];
        $receiver = $params['receiver'] ?? [];

        $kdniaoParams = [
            'ShipperCode' => $params['express_code'],
            'LogisticCode' => $params['express_no'],
        ];

        if ($params['express_code'] == 'SF') {
            // 收件人手机号后四位
            $kdniaoParams['CustomerName'] = (isset($receiver['mobile']) && $receiver['mobile']) ? substr($receiver['mobile'], 7) : '';
        } elseif ($params['express_code'] == 'CNCY') {
            // 菜鸟橙运 分配的货主编号
            $kdniaoParams['CustomerName'] = $this->config['customer_name'];
        }

        // 调用接口获取运单信息
        $result = $this->service->search($kdniaoParams);

        $traces = $result['Traces'] ?? [];
        $status = $result['State'];

        // 格式化结果
        $tracesData = $this->formatTraces([
            'status' => $status,
            'traces' => $traces,
        ]);

        $tracesData['express_code'] = $params['express_code'];
        $tracesData['express_no'] = $params['express_no'];

        return $tracesData;
    }

    /**
     * 发货
     *
     * @param  array  $params
     */
    public function send($params): array
    {
        $baseInfo = $params['base_info'];

        if ($this->config['request_type'] !== 'vip') {
            throw new EStoreException('仅快递鸟标准版接口支持电子面单功能！');
        }

        // 唯一订单号
        $table_unique_id = $baseInfo['table_unique_id'] ?? (time() . mt_rand(1000, 9999));
        $params['unique_id'] = 'kdniaopackage:' . $table_unique_id . ':' . md5(time() . mt_rand(1000, 9999));     // 根据订单id 生成唯一id

        // 运单基础信息
        $kdniaoParams = [
            // 下面五个参数对应快递鸟的五个参数  https://www.yuque.com/kdnjishuzhichi/dfcrg1/hrfw43
            'CustomerName' => $this->config['customer_name'],
            'CustomerPwd' => $this->config['customer_pwd'],
            'MonthCode' => $this->config['month_code'],
            'SendSite' => $this->config['send_site'],
            'SendStaff' => $this->config['send_staff'],

            'ShipperCode' => $this->config['express']['code'] ?? '',
            'OrderCode' => $params['unique_id'],   // 唯一 id

            'PayType' => $this->config['pay_type'],

            'ExpType' => $this->config['exp_type'],
            'IsReturnPrintTemplate' => 0,   //返回打印面单模板
            'TemplateSize' => '130',        // 一联单
            'Volume' => 0,
            'Remark' => $params['remark'] ?? ($baseInfo['remark'] ?? '贵重物品，小心轻放'),  // 备注
        ];

        // 接收人信息
        $kdniaoParams['Receiver'] = $this->formatReceiver($params['receiver'] ?? []);

        // 发货人信息
        $kdniaoParams['Sender'] = $this->formatSender($params['sender'] ?? []);

        // 包裹信息
        $cargoInfo = $this->formatCargo($params['cargo'] ?? []);
        $kdniaoParams['Quantity'] = $cargoInfo['quantity'];
        $kdniaoParams['Weight'] = $cargoInfo['weight'];
        $kdniaoParams['Volume'] = $cargoInfo['volume'];
        $kdniaoParams['Commodity'] = $cargoInfo['commodity'];

        // 调用接口生成运单
        $result = $this->service->create($kdniaoParams);

        return $this->formatSendResponse($params, $result);
    }

    /**
     * 取消发货
     *
     * @param  \think\Model|array  $package
     * @param  array  $params
     */
    public function cancel($package, $params): bool
    {
        $baseInfo = $params['base_info'] ?? [];

        $ext = $package->ext;

        // 组装参数
        $kdniaoParams['OrderCode'] = $ext['order_code'] ?? '';
        $kdniaoParams['ShipperCode'] = $ext['shipper_code'] ?? '';
        $kdniaoParams['ExpNo'] = $ext['logistic_code'] ?? '';
        $kdniaoParams['CustomerName'] = $this->config['customer_name'];
        $kdniaoParams['CustomerPwd'] = $this->config['customer_pwd'];
        $kdniaoParams['MonthCode'] = $this->config['month_code'];

        // 支持取消的快递列表：优速快递(UC), 百世快递(没了，只有百世快运), 承诺达(也没有), 德邦快递(DBL), 京东快递(JD), 韵达速递(YD), 跨越速运(KYSY), 丰云配(FYP), 圆通快递(YTO)
        // 顺丰取消接口暂不生效，用户侧需先通过线下方式取消
        // @sn todo 暂时不校验，只管调接口
        // if (in_array($params['ShipperCode'], ['UC', 'DBL', 'JD', 'YD', 'KYSY', 'FYP', 'YTO'])) {
        // }

        // 调用接口取消运单
        $result = $this->service->cancel($kdniaoParams);
        \think\Log::write('kdniao_delivery_cancel_info: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

        return true;
    }

    /**
     * 修改物流发货
     *
     * @param  \think\Model|array  $package
     * @param  array  $params
     */
    public function change($package, $params): array
    {
        throw new DeliveryException('该发货单已被推送第三方平台，请取消后重新发货');
        // @sn todo 后续这里可以先调用 cancel, 然后在调用 send

        return [];
    }

    /**
     * 物流轨迹订阅
     *
     * @param  array  $params
     */
    public function subscribe($params): bool
    {
        $baseInfo = $params['base_info'] ?? [];
        $receiver = $params['receiver'] ?? [];

        $kdniaoParams = [
            'ShipperCode' => $params['express_code'],
            'LogisticCode' => $params['express_no'],
        ];

        if ($params['express_code'] == 'SF') {
            // 收件人手机号后四位
            $kdniaoParams['CustomerName'] = (isset($receiver['mobile']) && $receiver['mobile']) ? substr($receiver['mobile'], 7) : '';
        } elseif ($params['express_code'] == 'CNCY') {
            // 菜鸟橙运 分配的货主编号
            $kdniaoParams['CustomerName'] = $this->config['customer_name'];
        }

        // 调用接口获取运单信息
        $result = $this->service->subscribe($kdniaoParams);
        \think\Log::write('kdniao_delivery_subscribe_info: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

        return true;
    }

    /**
     * 轨迹推送通知信息处理
     *
     * @param  array  $message
     */
    public function notifyTraces($message): array
    {
        \think\Log::write('kdniao_delivery_notify_info: ' . json_encode($message, JSON_UNESCAPED_UNICODE));

        $data = json_decode(html_entity_decode($message['RequestData']), true);
        $expressData = $data['Data'];

        $tracesDatas = [];
        foreach ($expressData as $key => $express) {
            if (! $express['Success']) {
                \think\Log::error('kdniao_delivery_push_error:' . json_encode($express));

                // 失败了
                if (isset($express['Reason']) && (strpos($express['Reason'], '三天无轨迹') !== false || strpos($express['Reason'], '七天内无轨迹变化') !== false)) {
                    // 需要重新订阅
                    \think\Log::error('kdniao_delivery_need_resubscribe:' . json_encode($express));

                    // @sn todo 重新订阅
                    // $this->subscribe([
                    //     'express_code' => $express['ShipperCode'],
                    //     'express_no' => $express['LogisticCode']
                    // ]);
                }

                continue;
            }

            $traces = $express['Traces'] ?? [];
            $status = $express['StateEx'];

            // 格式化结果
            $currentTraces = $this->formatTraces([
                'status' => $status,
                'traces' => $traces,
            ]);
            $currentTraces['express_code'] = $express['ShipperCode'];
            $currentTraces['express_no'] = $express['LogisticCode'];
            if (isset($express['OrderCode']) && $express['OrderCode']) {        // kdniao 接口文档不保证一定有 OrderCode
                $currentTraces['extra'] = [
                    // 包裹额外信息，更加精确查询包裹时用，和 发货时候 formatSendResponse 方法组装的 ext 字段相对应
                    'order_code' => $express['OrderCode'],
                ];
            }

            $tracesDatas[] = $currentTraces;
        }

        return $tracesDatas;
    }

    /**
     * 快递鸟物流推送结果处理
     *
     * @param  bool  $success
     * @param  string  $reason
     * @return mixed
     */
    public function getNotifyResponse($data = [])
    {
        $result = [
            'EBusinessID' => $this->config['ebusiness_id'],
            'UpdateTime' => date('Y-m-d H:i:s'),
            'Success' => true,
            'Reason' => '',
        ];

        return $result;
    }

    /**
     * 格式化发货人信息
     *
     * @param  array  $paramsSender
     * @return array
     */
    private function formatSender($paramsSender)
    {
        $sender = [
            'Name' => $paramsSender['consignee'] ?? '',
            'Mobile' => $paramsSender['mobile'] ?? '',
            'ProvinceName' => $paramsSender['province'] ?? '',
            'CityName' => $paramsSender['city'] ?? '',
            'ExpAreaName' => $paramsSender['area'] ?? '',
            'Address' => $paramsSender['address'] ?? '',
        ];

        return $sender;
    }

    /**
     * 格式化接收人信息
     *
     * @param  array  $paramsReceiver
     * @return array
     */
    private function formatReceiver($paramsReceiver)
    {
        // 接收人信息
        $receiver = [
            'Name' => $paramsReceiver['consignee'] ?? '',
            'Mobile' => $paramsReceiver['mobile'] ?? '',
            'ProvinceName' => $paramsReceiver['province'] ?? '',
            'CityName' => $paramsReceiver['city'] ?? '',
            'ExpAreaName' => $paramsReceiver['area'] ?? '',
            'Address' => $paramsReceiver['address'] ?? '',
        ];

        return $receiver;
    }

    /**
     * 格式化包裹信息
     *
     * @param  array  $params
     * @return array
     */
    private function formatCargo($params)
    {
        $baseInfo = $params['base_info'];
        $cargo = $params['cargo'] ?? [];
        $cargos = $cargo['cargos'] ?? [];

        // 包裹长宽高，重量
        $package_weight = $params['package_weight'] ?? ($cargo['weight'] ?? array_sum(array_column($cargos, 'relate_weight')));
        $package_length = $params['package_length'] ?? ($cargo['package_length'] ?? 0);        // 优先使用发货时传来的，其次是 包裹中的信息
        $package_width = $params['package_width'] ?? ($cargo['package_width'] ?? 0);          // 优先使用发货时传来的，其次是 包裹中的信息
        $package_height = $params['package_height'] ?? ($cargo['package_height'] ?? 0);        // 优先使用发货时传来的，其次是 包裹中的信息

        $quantity = 1;      // 这个是包裹数量， 不是商品数量
        $weight = $package_weight;
        $volume = round(($package_length * $package_width * $package_height) * 0.000001, 2);            // 长宽高单位 厘米， 快递鸟 体积单位是 m2

        $commodity = [];
        foreach ($cargos as $car) {
            $detail = [
                'GoodsName' => $car['cargo_title'],
                'Goodsquantity' => $car['cargo_num'],
                'GoodsWeight' => $car['cargo_weight'],
                'GoodsPrice' => $car['cargo_price'],
            ];

            $commodity[] = $detail;
        }

        return compact('quantity', 'weight', 'volume', 'commodity');
    }

    /**
     * 格式化微信下单接口响应值
     */
    private function formatSendResponse($params, $result)
    {
        $response = [
            'express_name' => $this->config['express_name'],
            'express_code' => $this->config['express_code'],
            'express_no' => $result['Order']['LogisticCode'],
            'ext' => [
                'order_code' => $result['Order']['OrderCode'],              // 下单时自定义的订单号：$order['order_sn'] . '_' . time();
                'shipper_code' => $result['Order']['ShipperCode'],          // 快递公司编号;
                'logistic_code' => $result['Order']['LogisticCode'],        // 快递单号;
            ],
            'original_result' => $result,
        ];

        return $response;
    }

    /**
     * 处理物流轨迹返回结果
     *
     * @param  array  $data
     * @return array
     */
    protected function formatTraces($data)
    {
        // 状态对照表
        $statusList = (new ExpressPackage)->statusList();

        $traces = [];
        foreach ($data['traces'] as $trace) {
            $currentStatus = $this->status[($trace['Action'] ?? '')] ?? 'noinfo';
            $traces[] = [
                'content' => $trace['AcceptStation'],
                'change_date' => date('Y-m-d H:i:s', strtotime(substr($trace['AcceptTime'], 0, 19))),    // 快递鸟时间格式可能是 2020-08-03 16:58:272 或者 2014/06/25 01:41:06
                'status' => $currentStatus,
                'status_text' => $statusList[$currentStatus] ?? '',
            ];
        }

        $status = $this->status[$data['status']] ?? 'noinfo';
        $status_text = $statusList[$status] ?? '';

        return compact('status', 'status_text', 'traces');
    }
}
