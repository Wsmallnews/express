<?php

namespace Wsmallnews\Express\Adapters;

use Wsmallnews\Express\Contracts\AdapterInterface;
use Wsmallnews\Express\Exceptions\ExpressException;

class ManualAdapter implements AdapterInterface
{

    /**
     * 配置数组
     *
     * @var array
     */
    public array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }


    /**
     * 获取当前驱动名
     *
     * @return string
     */
    public function getType(): string
    {
        return 'manual';
    }



    /**
     * 查询物流轨迹
     *
     * @param array $params
     * @return array
     */
    public function query($params): array
    {
        throw new ExpressException('@sn todo 手动发货，暂时不支持快递查询');

        $tracesData = [];

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
        $express_name = $params['express_name'] ?? '';
        $express_code = $params['express_code'] ?? '';
        $express_no = $params['express_no'] ?? '';

        if (!$express_name || !$express_code || !$express_no) {
            throw new ExpressException('请填写正确的快递单号');
        }

        return [
            'express_name' => $express_name,
            'express_code' => $express_code,
            'express_no' => $express_no,
            'ext' => []
        ];
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
        // 目前什么也不用做

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
        $express_name = $params['express_name'] ?? '';
        $express_code = $params['express_code'] ?? '';
        $express_no = $params['express_no'] ?? '';

        if (!$express_name || !$express_code || !$express_no) {
            throw new ExpressException('请填写正确的快递单号');
        }

        return [
            'express_name' => $express_name,
            'express_code' => $express_code,
            'express_no' => $express_no,
            'ext' => []
        ];
    }


    /**
     * 物流轨迹订阅
     *
     * @param array $params
     * @return boolean
     */
    public function subscribe($params): bool
    {
        throw new ExpressException('手动发货，不可以订阅物流轨迹');

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
        throw new ExpressException('手动发货，没有物流轨迹通知方法');

        return [];
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
}
