<?php

namespace Wsmallnews\Express\Adapters;

use Wsmallnews\Express\Contracts\AdapterInterface;
use Wsmallnews\Express\Exceptions\ExpressException;

// use addons\estore\package\delivery\{
//     model\ExpressPackage,
// };

class ThinkapiAdapter implements AdapterInterface
{

    public $status = [
        '1' => 'noinfo',
        '2' => 'transport',
        '3' => 'delivery',
        '4' => 'signfor',
        '5' => 'refuse',
        '6' => 'difficulty',
        '7' => 'invalid',
        '8' => 'timeout',
        '9' => 'fail',
        '10' => 'back'
    ];


    protected $uri = 'https://api.topthink.com';


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
        return 'thinkapi';
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
        $receiver = $params['receiver'] ?? [];

        // 收件人手机号后四位
        $mobile = (isset($receiver['mobile']) && $receiver['mobile']) ? substr($receiver['mobile'], 7) : '';

        $requestData = [
            'appCode' => $this->config['app_code'],
            'com' => 'auto',
            'nu' => $params['express_no'],
            'phone' => substr($mobile, 7)
        ];

        $result = $this->request('get', $this->uri . '/express/query', [
            'query' => $requestData,
        ]);

        if (isset($result['code']) && $result['code'] != 0) {
            $msg = $result['data']['msg'] ?? ($result['message'] ?? '');
            throw new ExpressException($msg);
        }

        $data = $result['data'] ?? [];

        $traces = $data['data'] ?? [];
        $status = $data['status'];

        // 格式化结果
        $tracesData = $this->formatTraces([
            'status' => $status,
            'traces' => $traces
        ]);

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
        throw new ExpressException('ThinkApi 发货，不可以订阅物流轨迹');

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
        throw new ExpressException('ThinkApi 发货，没有物流轨迹通知方法');

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



    /**
     * 处理返回结果
     *
     * @param array $data
     * @return array
     */
    protected function formatTraces($data)
    {
        // 状态对照表
        $statusList = (new ExpressPackage)->statusList();

        $traces = [];
        foreach ($data['traces'] as $trace) {
            $status = $trace['status'] ?? $data['status'];      // trace 中可能没有 status,那就用最外面的总得 status

            $currentStatus = $this->status[$status] ?? 'noinfo';
            $traces[] = [
                'content' => $trace['context'],
                'change_date' => $trace['time'],
                'status' => $currentStatus,
                'status_text' => $statusList[$currentStatus] ?? '',
            ];
        }
        $traces = array_reverse($traces);       // 调转顺序，第一条为最开始运输信息，最后一条为最新消息

        $status = $this->status[$data['status']] ?? 'noinfo';
        $status_text = $statusList[$status] ?? '';

        return compact('status', 'status_text', 'traces');
    }




    /**
     * 一般 微信接口请求
     */
    public function request($method = 'post', $url = '', $data = [])
    {
        $formParams = $data['form_params'] ?? [];
        $body = $data['body'] ?? [];
        $query = $data['query'] ?? [];
        $headers = $data['headers'] ?? [];

        $response = \addons\estore\facade\HttpClient::request($method, $url, [
            'form_params' => $formParams,
            'body' => $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '{}',
            'query' => $query,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
        ]);

        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);

        return $result;
    }

}