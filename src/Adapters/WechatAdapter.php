<?php

namespace Wsmallnews\Express\Adapters;

use Wsmallnews\Express\Contracts\AdapterInterface;
use Wsmallnews\Express\Exceptions\ExpressException;

// use addons\estore\package\delivery\{
//     service\WechatDeliveryService,
//     model\ExpressPackage,
// };

class WechatAdapter implements AdapterInterface
{

    public $status = [
        '0' => 'noinfo',
        '100001' => 'collect',
        '100002' => 'difficulty',
        '100003' => 'collect',
        '200001' => 'transport',
        '300002' => 'delivery',
        '300003' => 'signfor',
        '300004' => 'fail',
        '400001' => 'difficulty',
        '400002' => 'difficulty',
    ];


    /**
     * 配置数组
     *
     * @var array
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

        $this->service = new WechatDeliveryService();
    }



    /**
     * 获取当前驱动名
     *
     * @return string
     */
    public function getType(): string
    {
        return 'wechat';
    }



    /**
     * 查询物流轨迹
     *
     * @param array $params
     * @return array
     */
    public function query($params): array
    {
        $baseInfo = $params['base_info'] ?? [];
        $openid = $baseInfo['openid'] ?? '';

        $wechatParams['delivery_id'] = $params['express_code'];
        $wechatParams['waybill_id'] = $params['express_no'];
        $wechatParams['openid'] = $openid;

        // 调用接口获取运单信息
        $result = $this->service->getTraces($wechatParams);

        $delivery_id = $result['delivery_id'];      // SF
        $waybill_id = $result['waybill_id'];
        $path_item_num = $result['path_item_num'];
        $actions = $result['path_item_list'];

        // 格式化物流轨迹
        $tracesData = $this->formatTraces($actions, 'snake');
        $tracesData['express_code'] = $params['express_code'];
        $tracesData['express_no'] = $params['express_no'];

        return $tracesData;
    }



    /**
     * 发货
     *
     * @param array $params
     * @return array
     */
    public function send($params): array
    {
        $baseInfo = $params['base_info'];
        $openid = $baseInfo['openid'] ?? '';
        $table_unique_id = $baseInfo['table_unique_id'] ?? (time() . mt_rand(1000, 9999));

        $params['unique_id'] = 'wechatpackage:' . $table_unique_id . ':' . md5(time() . mt_rand(1000, 9999));     // 根据订单id 生成唯一id

        // @sn todo 开启测试单
        $params['delivery_id'] = 'TEST';
        $params['biz_id'] = 'test_biz_id';
        $params['unique_id'] = time() . mt_rand(10, 99);

        // 配送相关参数
        $wechatParams = [
            'order_id' => $params['unique_id'],                           // 测试订单发放两个包裹时候是否会提示 重复【会提示重复】
            'delivery_id' => $params['delivery_id'] ?? '',
            'biz_id' => $params['biz_id'] ?? '',
            'custom_remark' => $params['remark'] ?? ($baseInfo['remark'] ?? '贵重物品，小心轻放'),  // 备注
            'service' => [
                'service_type' => $params['service_type'] ?? '',
                'service_name' => $params['service_name'] ?? ''
            ],
            'expect_time' => strtotime($params['expect_time']) ?? 0,                 // 如果是下顺丰散单，则必传此字段: 顺丰必须传。 预期的上门揽件时间，0表示已事先约定取件时间；否则请传预期揽件时间戳
            'take_mode' => 0,
        ];
        if ($openid) {
            // 有openid，走 微信小程序的逻辑，会发服务通知
            $wechatParams['openid'] = $openid;
            $wechatParams['add_source'] = 0;
        } else {
            // 走 app 或者 h5 的逻辑，不会发送服务通知
            $wechatParams['add_source'] = 2;
            $wechatParams['wx_appid'] = '';                   // App 或 H5 的appid，add_source=2时必填，需和开通了物流助手的小程序绑定同一open账号
        }

        // 发货人信息
        $wechatParams['sender'] = $this->formatSender($params['sender'] ?? []);

        // 接收人信息
        $wechatParams['receiver'] = $this->formatReceiver($params['receiver'] ?? []);

        // 包裹信息
        $wechatParams['cargo'] = $this->formatCargo($params['cargo'] ?? []);

        // 获取商品信息
        $wechatParams['shop'] = $this->formatShop($params);

        // 保价信息
        $insured = [
            'use_insured' => 0
        ];
        $wechatParams['insured'] = $insured;

        // 调用接口生成运单
        $result = $this->service->expressCreate($wechatParams);

        return $this->formatSendResponse($params, $result);
    }



    /**
     * 取消发货
     *
     * @param \think\Model|array $package
     * @param array $params
     * @return boolean
     */
    public function cancel($package, $params): bool
    {
        $baseInfo = $params['base_info'] ?? [];
        $openid = $baseInfo['openid'] ?? '';

        $ext = $package->ext;
        // 组装参数
        $params['unique_id'] = $ext['unique_id'];
        $params['delivery_id'] = $ext['delivery_id'];
        $params['biz_id'] = $ext['biz_id'];

        // 配送相关参数
        $wechatParams = [
            'delivery_id' => $package['express_code'] ?? '',    // 快递公司编号
            'waybill_id' => $package['express_no'] ?? '',       // 运单号
            'order_id' => $params['unique_id'],                 // 微信运单 订单 id
        ];

        if ($openid) {
            // 有openid，走 微信小程序的逻辑，会发服务通知
            $wechatParams['openid'] = $openid;
        }

        // 调用接口取消运单
        $result = $this->service->expressCancel($wechatParams);
        \think\Log::write('wechat_delivery_cancel_info: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

        return true;
    }



    /**
     * 修改物流发货
     *
     * @param \think\Model|array $package
     * @param array $params
     * @return array
     */
    public function change($package, $params): array
    {
        throw new ExpressException('该发货单已被推送第三方平台，请取消后重新发货');

        // @sn todo 后续这里可以先调用 cancel, 然后在调用 send

        return [];
    }



    /**
     * 物流轨迹订阅
     *
     * @param array $params
     * @return boolean
     */
    public function subscribe($params): bool
    {
        throw new ExpressException('微信发货，无需订阅物流轨迹');

        return true;
    }


    /**
     * 轨迹推送通知信息处理
     *
     * @param array $message
     * @return array
     */
    public function notifyTraces($message): array
    {
        \think\Log::write('wechat_delivery_notify_info: ' . json_encode($message, JSON_UNESCAPED_UNICODE));

        $delivery_id = $message['DeliveryID'] ?? '';      // SF
        $waybill_id = $message['WayBillId'] ?? '';
        $order_id = $message['OrderId'] ?? '';
        $version = $message['Version'] ?? 0;
        $count = $message['Count'] ?? 0;
        $actions = $message['Actions'] ?? [];

        $tracesData = $this->formatTraces($actions, 'snake');
        $tracesData['express_code'] = $delivery_id;
        $tracesData['express_no'] = $waybill_id;
        $tracesData['extra'] = [
            // 包裹额外信息，更加精确查询包裹时用，和 发货时候 formatSendResponse 方法组装的 ext 字段相对应
            'unique_id' => $order_id
        ];

        return [$tracesData];       // 默认都返回多物流单模式
    }


    /**
     * 物流推送结果处理
     *
     * @param boolean $success
     * @param string $reason
     * @return mixed
     */
    public function getNotifyResponse($data = [])
    {
        $result = [];

        return $result;
    }


    /**
     * 格式化发货人信息
     *
     * @param array $paramsSender
     * @return array
     */
    private function formatSender($paramsSender)
    {
        $sender = [
            'name' => $paramsSender['consignee'] ?? '',
            'mobile' => $paramsSender['mobile'] ?? '',
            'province' => $paramsSender['province'] ?? '',
            'city' => $paramsSender['city'] ?? '',
            'area' => $paramsSender['area'] ?? '',
            'address' => $paramsSender['address'] ?? '',
        ];

        return $sender;
    }


    /**
     * 格式化接收人信息
     *
     * @param array $paramsReceiver
     * @return array
     */
    private function formatReceiver($paramsReceiver)
    {
        // 接收人信息
        $receiver = [
            'name' => $paramsReceiver['consignee'] ?? '',
            'mobile' => $paramsReceiver['mobile'] ?? '',
            'province' => $paramsReceiver['province'] ?? '',
            'city' => $paramsReceiver['city'] ?? '',
            'area' => $paramsReceiver['area'] ?? '',
            'address' => $paramsReceiver['address'] ?? '',
        ];
        return $receiver;
    }



    /**
     * 格式化接收人信息
     *
     * @param array $paramsCargo
     * @return array
     */
    private function formatCargo($paramsCargo)
    {
        $cargos = $paramsCargo['cargos'] ?? [];       // 货物列表

        // 包裹信息
        $cargo = [
            'count' => 1,
            'weight' => $params['package_weight'] ?? ($paramsCargo['weight'] ?? array_sum(array_column($cargos, 'relate_weight'))),
            'space_x' => $params['package_length'] ?? ($paramsCargo['package_length'] ?? 0),        // 优先使用发货时传来的，其次是 包裹中的信息
            'space_y' => $params['package_width'] ?? ($paramsCargo['package_width'] ?? 0),          // 优先使用发货时传来的，其次是 包裹中的信息
            'space_z' => $params['package_height'] ?? ($paramsCargo['package_height'] ?? 0),        // 优先使用发货时传来的，其次是 包裹中的信息
        ];

        foreach ($cargos as $car) {
            $detail = [
                'name' => $car['cargo_title'],
                'count' => $car['cargo_num'],
            ];

            $cargoDetailList[] = $detail;
        }
        $cargo['detail_list'] = $cargoDetailList ?? [];

        return $cargo;
    }



    /**
     * 格式化商品
     *
     * @param array $params
     * @return array
     */
    private function formatShop($params)
    {
        $baseInfo = $params['base_info'];
        $cargo = $params['cargo'] ?? [];
        $cargos = $cargo['cargos'] ?? [];

        // 商品信息
        $shop['wxa_path'] = $baseInfo['url_path'] ?? '';
        $shop['goods_count'] = array_sum(array_column($cargos, 'cargo_num'));
        foreach ($cargos as $car) {
            $detail = [
                'goods_name' => $car['cargo_title'],
                'goods_img_url' => cdnurl($car['cargo_image'], true),
                'goods_desc' => $car['cargo_num'] . '件 * ' . $car['cargo_weight'] . 'KG'
            ];

            $detailList[] = $detail;
        }
        $shop['detail_list'] = $detailList ?? [];

        return $shop;
    }




    /**
     * 格式化微信下单接口响应值
     */
    private function formatSendResponse($params, $result)
    {
        $deliveries = $this->service->getAllDelivery();
        $deliveries = $deliveries['data'];
        $deliveries = array_column($deliveries, null, 'delivery_id');
        $currentDelivery = $deliveries[$params['delivery_id']] ?? [];
        $delivery_name = $currentDelivery['delivery_name'] ?? '';

        \think\Log::write('wechat_delivery_back_info: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

        $response = [
            'express_name' => $delivery_name,
            'express_code' => $params['delivery_id'] ?? '',
            'express_no' => $result['waybill_id'] ?? '',
            'ext' => [
                'unique_id' => $params['unique_id'],        // 就是结果返回的 $result['order_id'];
                'delivery_id' => $params['delivery_id'],
                'biz_id' => $params['biz_id'],
            ],
            'original_result' => $result
        ];

        return $response;
    }


    /**
     * 处理物流轨迹返回结果
     *
     * @param array $data
     * @return array
     */
    protected function formatTraces($actions, $field_type = 'studly')
    {
        $action_type_field = $field_type == 'snake' ? 'action_type' : 'ActionType';
        $action_msg_field = $field_type == 'snake' ? 'action_msg' : 'ActionMsg';
        $action_time_field = $field_type == 'snake' ? 'action_time' : 'ActionTime';

        // 当物流轨迹只有一条时，数据结构是 对象； 多条时，数据结构是 数组对象
        $actions = isset($actions[$action_type_field]) ? [$actions] : $actions;
        $actions = array_reverse($actions);

        // 状态对照表
        $statusList = (new ExpressPackage)->statusList();

        $traces = [];
        foreach ($actions as $key => $action) {
            $currentStatus = $this->status[$action[$action_type_field]] ?? 'noinfo';
            $trace = [
                'content' => $action[$action_msg_field],
                'status' => $currentStatus,
                'status_text' => $statusList[$currentStatus] ?? '',
                'change_date' => date('Y-m-d H:i:s', (int)$action[$action_time_field]),
            ];

            $traces[] = $trace;
        }

        $lastTrace = end($traces);  // 最新轨迹
        $status = $lastTrace['status'] ?? 'noinfo';       // 最新的状态
        $status_text = $lastTrace['status_text'] ?? '';       // 最新的状态中文

        return compact('status', 'status_text', 'traces');
    }
}