<?php

namespace Wsmallnews\Express;

use Wsmallnews\Express\Contracts\AdapterInterface;
use Wsmallnews\Express\Exceptions\ExpressException;

// use think\Collection;
// use addons\estore\package\support\{
//     traits\HasScopeType,
//     traits\HasTableType,
// };
// use addons\estore\package\delivery\{
//     exception\DeliveryException,
//     express\contract\AdapterInterface,
//     express\contract\ExpressableInterface,
//     express\PackageManager,
// };

class Sender
{


    /**
     * adapter
     *
     * @var AdapterInterface
     */
    protected $adapter = null;


    /**
     * expresser 要发货的实体
     *
     * @var ExpressableInterface
     */
    protected $expresser = null;


    public function __construct(AdapterInterface $adapter = null)
    {
        $this->adapter = $adapter;
    }


    /**
     * 设置本次要发货的实体
     *
     * @param ExpressableInterface $expresser
     * @return self
     */
    public function setExpresser(ExpressableInterface $expresser)
    {
        $this->expresser = $expresser;

        $baseInfo = $this->expresser->getBaseInfo();
        $scope_type = $baseInfo['scope_type'];
        $store_id = $baseInfo['store_id'];
        $table_type = $baseInfo['table_type'];
        $table_id = $baseInfo['table_id'];

        $this->setScopeInfo($scope_type, $store_id);
        $this->setTableInfo($table_type, $table_id);

        return $this;
    }


    /**
     * 直接查询 通过快递公司和物流单号查询
     *
     * @param array $params
     * @return array
     */
    public function query($params)
    {
        $baseInfo = $this->expresser->getBaseInfo();
        $params['base_info'] = $baseInfo;
        $params['receiver'] = $this->expresser->getReceiver();      // 快递鸟顺丰查询需要发货人或者收货人手机号

        // 调用 adapter 获取物流信息
        $tracesData = $this->adapter->query($params);

        return $tracesData;
    }



    /**
     * 发货
     *
     * @param array $params
     * @return \think\Model
     */
    public function send($params)
    {
        $baseInfo = $this->expresser->getBaseInfo();

        $user_id = $baseInfo['user_id'];
        $params['base_info'] = $baseInfo;
        $params['receiver'] = $this->expresser->getReceiver();
        $params['sender'] = $this->expresser->getSender();
        $params['cargo'] = $this->expresser->getCargo();
        $cargos = $params['cargo']['cargos'] ?? [];         // 包裹商品信息
        $cargo_info = $params['cargo'];                     // 包裹信息，除了商品信息
        unset($cargo_info['cargos']);

        // 调用 adapter 发货
        $result = $this->adapter->send($params);

        $packageManager = (new PackageManager())->setScopeInfo($this->getScopeType(), $this->getStoreId())
            ->setTableInfo($this->getTableType(), $this->getTableId());
        // 添加快递包裹
        $package = $packageManager->setExpressInfo([
            'express_name' => $result['express_name'] ?? '',
            'express_code' => $result['express_code'],
            'express_no' => $result['express_no'] ?? ''
        ])->save([
            'user_id' => $user_id,
            'driver' => $this->adapter->getType(),
            'ext' => array_merge([
                'cargos' => $cargos,
                'cargo_info' => $cargo_info,

                'receiver' => $params['receiver'],
                'sender' => $params['sender'],
            ], ($result['ext'] ?? [])),

        ]);

        return $package;
    }




    /**
     * 取消运单
     *
     * @param \think\Model $package
     * @param array $params
     * @return void
     */
    public function cancel($package, $params)
    {
        $baseInfo = $this->expresser->getBaseInfo();
        $user_id = $baseInfo['user_id'];
        $params['base_info'] = $baseInfo;
        $params['cargo'] = $this->expresser->getCargo();

        // 调用 adapter 发货
        $result = $this->adapter->cancel($package, $params);

        // 删除包裹
        $package->delete();
    }



    /**
     * 更改运单
     *
     * @param \think\Model $package
     * @param array $params
     * @return \think\Model
     */
    public function change($package, $params)
    {
        $baseInfo = $this->expresser->getBaseInfo();

        $user_id = $baseInfo['user_id'];
        $params['base_info'] = $baseInfo;
        $params['receiver'] = $this->expresser->getReceiver();
        $params['sender'] = $this->expresser->getSender();
        $params['cargo'] = $this->expresser->getCargo();

        // 调用 adapter 发货
        $result = $this->adapter->change($package, $params);

        $packageManager = (new PackageManager())->setScopeInfo($this->getScopeType(), $this->getStoreId())
            ->setTableInfo($this->getTableType(), $this->getTableId());

        // 更新包裹快递单号信息
        $package = $packageManager->setExpressInfo([
            'express_name' => $result['express_name'] ?? '',
            'express_code' => $result['express_code'],
            'express_no' => $result['express_no'] ?? ''
        ])->update($package, [
            'ext' => $result['ext'] ?? [],        // 需要存库的 ext 信息
        ]);

        return $package;
    }



    /**
     * 轨迹订阅
     *
     * @param array $params     ['express_code', 'express_no']
     * @return void
     */
    public function subscribe($params)
    {
        $baseInfo = $this->expresser->getBaseInfo();
        $params['base_info'] = $baseInfo;
        $params['receiver'] = $this->expresser->getReceiver();      // 快递鸟顺丰查询需要发货人或者收货人手机号

        // 调用 adapter 订阅物流轨迹
        $result = $this->adapter->subscribe($params);
    }



    /**
     * 查询并更新 物流轨迹
     *
     * @param \think\Model $package
     * @param array $params
     * @return \think\Model
     */
    public function queryUpdateTraces($package, $params = [])
    {
        $baseInfo = $this->expresser->getBaseInfo();
        $params['base_info'] = $baseInfo;
        $params['express_name'] = $package->express_name;
        $params['express_code'] = $package->express_code;
        $params['express_no'] = $package->express_no;

        // 调用 adapter 获取最新物流信息
        $tracesData = $this->adapter->query($params);

        $packageManager = (new PackageManager())->setScopeInfo($this->getScopeType(), $this->getStoreId())
            ->setTableInfo($this->getTableType(), $this->getTableId());

        // 更新包裹快递单号信息
        $package = $packageManager->updateStatusTraces($package, $tracesData);

        return $package;
    }




    /**
     * notify 通知更新物流轨迹
     *
     * @param array $message
     * @return \think\Model
     */
    public function notifyUpdateTraces($message)
    {
        // 这里统一返回多个物流单的数据格式
        $tracesDatas = $this->adapter->notifyTraces($message);

        $packages = [];
        foreach ($tracesDatas as $tracesData) {
            $express_code = $tracesData['express_code'];
            $express_no = $tracesData['express_no'];
            $extra = $tracesData['extra'] ?? [];

            $packageManager = new PackageManager();
            $package = $packageManager->getPackageByExpressInfo($express_code, $express_no, $extra);
            if (!$package) {
                \think\Log::error('package-notfund: ' . json_encode($message));
                continue;
            }

            // 更新包裹快递单号信息
            $package = $packageManager->updateStatusTraces($package, $tracesData);

            $packages[] = $package;
        }

        $result = [
            'packages' => $packages,
            'adapter_result' => $this->adapter->getNotifyResponse([     // 各个适配器自己需要的响应结果
                'traces_datas' => $tracesDatas,
                'packages' => $packages
            ]),
        ];

        return $result;
    }



    // 在线打印电子面单 （打印电子面单使用 微信的）
    // public function printEOrder($params)
    // {
    //     $expressPackage = ExpressPackage::where('id', $params['express_package_id'])->find();

    //     if (!$expressPackage) {
    //         throw new DeliveryException('包裹不存在');
    //     }

    //     $wechatParams['order_id'] = $expressPackage['ext']['unique_id'] ?? '';
    //     $wechatParams['delivery_id'] = $expressPackage['express_code'];
    //     $wechatParams['waybill_id'] = $expressPackage['express_no'];
    //     $wechatParams['print_type'] = 0;
    //     $wechatParams['openid'] = $this->getUserOpenid();
    //     $wechatParams['custom_remark'] = '易碎物品，轻拿轻放';

    //     $data = $this->expressAdapter->getOrder($wechatParams);

    //     print_r($data);exit;
    // }

}
