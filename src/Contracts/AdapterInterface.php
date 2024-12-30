<?php

namespace Wsmallnews\Express\Contracts;

/**
 * express sender interface
 */
interface AdapterInterface
{
    /**
     * 获取当前驱动名
     *
     * @return string
     */
    // public function getType(): string;

    // /**
    //  * 查询物流轨迹
    //  *
    //  * @param array $params
    //  * @return array
    //  */
    // public function query($params);

    // /**
    //  * 发货
    //  *
    //  * @param array $params
    //  * @return array
    //  */
    // public function send($params): array;

    // /**
    //  * 取消发货
    //  *
    //  * @param \think\Model|array $package
    //  * @param array $params
    //  * @return boolean
    //  */
    // public function cancel($package, $params): bool;

    // /**
    //  * 修改物流发货
    //  *
    //  * @param \think\Model|array $package
    //  * @param array $params
    //  * @return array
    //  */
    // public function change($package, $params): array;

    // /**
    //  * 物流轨迹订阅
    //  *
    //  * @param array $params
    //  * @return boolean
    //  */
    // public function subscribe($params): bool;

    // /**
    //  * 轨迹推送通知信息处理
    //  *
    //  * @param array $message
    //  * @return array
    //  */
    // public function notifyTraces($message): array;

    // /**
    //  * 轨迹推送通知返回结果
    //  *
    //  * @param array $data
    //  * @return array
    //  */
    // public function getNotifyResponse($data);
}
