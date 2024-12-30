<?php

declare(strict_types=1);

namespace addons\estore\package\delivery\express;

use addons\estore\package\order\{
    OrderRocket
};
use addons\estore\package\delivery\{
    contract\CalcInterface,
    exception\DeliveryException,
    model\Delivery as DeliveryModel,
    model\Express as DeliveryExpressModel
};


/**
 * 在线物流
 */
class ExpressCalc implements CalcInterface
{

    /**
     * 配送信息
     */
    private $deliveryInfos = [
        // 'delivery_id' => [
        //     'delivery' => null,             // 当前运费模板
        //     'final_express' => null,        // 当前收货地址匹配的运费规则
        //     'buy_infos' => [],              // 当前运费模板中的所有商品
        //     'current_delivery_amount' => 0, // 当前运费模板真实的运费金额
        // ]
    ];

    private $delivery_amount = '0';

    private array $products = [];

    private $rocket = null;

    public function __construct()
    {
    }


    /**
     * 设置 products
     *
     * @param array $products
     * @return self
     */
    public function setProducts($products): self
    {
        $this->products = $products;

        return $this;
    }


    /**
     * 设置 rocket
     *
     * @param OrderRocket $rocket
     * @return self
     */
    public function setRocket(OrderRocket $rocket): self
    {
        $this->rocket = $rocket;

        return $this;
    }


    /**
     * 计算所有快递物流的商品的配送费，并分配到每个商品上
     *
     * @return string
     */
    public function calc()
    {
        $userAddress = $this->rocket->getRadar('user_address');

        if (!$userAddress) {
            return $this->delivery_amount;
        }

        // 按照 delivery 分组，并处理固定运费
        $products = $this->deliveryInit($this->products, $userAddress);

        // 接下来，计算运费，平均运费
        $this->calcEqualDelivery();

        // 将平均好的运费，重新分配到 pruducts
        $this->products = $this->reCalcProductDelivery($products);

        // 返回累计运费
        return $this->delivery_amount;
    }


    /**
     * 获取计算过运费的 products 列表
     *
     * @return array
     */
    public function getDeliveryProducts(): array
    {
        return $this->products;
    }



    private function deliveryInit($products, $userAddress)
    {
        foreach ($products as &$buyInfo) {
            if ($buyInfo['delivery_type'] != 'express') {
                // 这里只处理快递物流
                continue;
            }

            $product = $buyInfo['product'];

            $delivery_id = $product['delivery_id'];     // 这是商品支持的配送方式 ids 集合
            if ($delivery_id) {
                // 选的运费模板，按照模板id进行分组
                $finalExpress = $this->getFinalExpresses($delivery_id, $userAddress);

                $this->deliveryInfos[$delivery_id]['buy_infos'][] = $buyInfo;
                $this->deliveryInfos[$delivery_id]['final_express'] = $finalExpress;
            } else {
                // 固定费用
                $buyInfo['delivery_amount'] = $product['delivery_fee'];

                // 累计总运费
                $this->delivery_amount = bcadd($this->delivery_amount, $buyInfo['delivery_amount'], 2);
            }
        }

        return $products;
    }




    /**
     * 获取最终匹配的 express 规则
     *
     * @param string $delivery_id
     * @param object $userAddress
     * @return object
     */
    private function getFinalExpresses($delivery_id, $userAddress)
    {
        $delivery = $this->getDelivery($delivery_id);
        $expresses = $delivery['expresses'];

        $this->deliveryInfos[$delivery_id]['delivery'] = $delivery;     // 保存当前运费模板

        if (isset($this->deliveryInfos[$delivery_id]['final_express'])) {
            $finalExpress = $this->deliveryInfos[$delivery_id]['final_express'];
        } else {
            // 匹配到的最终规则
            $finalExpress = $this->pairExpress($expresses, $userAddress);
        }

        return $finalExpress;
    }


    /**
     * 当前配送的 express 模板
     *
     * @param string $delivery_id
     * @return object
     */
    private function getDelivery($delivery_id)
    {
        return DeliveryModel::show()->with(['expresses'])->where('type', 'express')->where('id', $delivery_id)->findOrFail();
    }



    /**
     * 匹配配送规则
     *
     * @param \Illuminate\Support\Collection $expresses
     * @param object $userAddress
     * @return object
     */
    private function pairExpress($expresses, $userAddress)
    {
        $finalExpress = null;
        foreach ($expresses as $key => $express) {
            if (strpos($express->district_ids, strval($userAddress->district_id)) !== false) {
                $finalExpress = $express;
                break;
            }

            if (strpos($express->city_ids, strval($userAddress->city_id)) !== false) {
                $finalExpress = $express;
                break;
            }

            if (strpos($express->province_ids, strval($userAddress->province_id)) !== false) {
                $finalExpress = $express;
                break;
            }
        }

        if (empty($finalExpress)) {
            throw new DeliveryException('当前地区不在配送范围');
        }

        return $finalExpress;
    }



    /**
     * 运费聚合计算，然后加权平均分配到商品上
     *
     * @return void
     */
    private function calcEqualDelivery()
    {
        // 计算应付总运费，商品中同一种运费模板聚合进行计算运费，然后再按照运费模板规则，将运费加权平分到每个商品
        foreach ($this->deliveryInfos as $key => &$deliveryInfo) {
            $finalExpress = $deliveryInfo['final_express'];
            $buy_num = 0;
            $weight = 0;
            foreach ($deliveryInfo['buy_infos'] as $k => $deliveryBuyInfo) {
                $buy_num += $deliveryBuyInfo['product_num'];
                $weight = bcadd((string)$weight, $deliveryBuyInfo['weight'], 2);
            }
            // 聚合原始运费
            $current_delivery_amount = $this->calcAmount($finalExpress, [
                'buy_num' => $buy_num,
                'weight' => $weight
            ]);

            $deliveryInfo['current_delivery_amount'] = $current_delivery_amount;        // 记录当前运费模板下商品的运费

            // 累计总运费
            $this->delivery_amount = bcadd($this->delivery_amount, $current_delivery_amount, 2);

            // 将运费加权平分到当前运费模板中的每个商品
            if ($current_delivery_amount) {
                $deliveryInfo['buy_infos'] = $this->equalAmount($current_delivery_amount, $deliveryInfo['buy_infos'], [
                    'buy_num' => $buy_num,
                    'weight' => $weight,
                    'final_express' => $finalExpress
                ]);
            }
        }
    }



    /**
     * 根据匹配的规则计算费用
     *
     * @param object $finalExpress
     * @param array $data
     * @return string
     */
    private function calcAmount($finalExpress, $data)
    {
        // 初始费用
        $delivery_amount = (string)$finalExpress->first_price;

        if ($finalExpress['type'] == 'number') {
            // 按件计算
            if ($finalExpress->additional_num && $finalExpress->additional_price) {
                // 首件之后剩余件数
                $surplus_num = bcsub((string)$data['buy_num'], (string)$finalExpress->first_num);

                // 多出的计量
                $additional_mul = ceil(($surplus_num / $finalExpress->additional_num));
                if ($additional_mul > 0) {
                    $additional_delivery_amount = bcmul((string)$additional_mul, (string)$finalExpress->additional_price, 2);
                    $delivery_amount = bcadd((string)$delivery_amount, (string)$additional_delivery_amount, 2);
                }
            }
        } else {
            // 按重量计算
            if ($finalExpress->additional_num && $finalExpress->additional_price) {
                // 首重之后剩余重量
                $surplus_num = bcsub((string)$data['weight'], (string)$finalExpress->first_num, 3);

                // 多出的计量
                $additional_mul = ceil(($surplus_num / $finalExpress->additional_num));
                if ($additional_mul > 0) {
                    $additional_delivery_amount = bcmul((string)$additional_mul, $finalExpress->additional_price, 2);
                    $delivery_amount = bcadd($delivery_amount, $additional_delivery_amount, 2);
                }
            }
        }

        return $delivery_amount;
    }




    /**
     * 一个运费模板下面的运费，加权平均到商品 （最后一个商品，分摊剩余的所有配送费）
     *
     * @param string $delivery_amount
     * @param array $deliveryBuyInfos
     * @param array $data
     * @return void
     */
    private function equalAmount($delivery_amount, $deliveryBuyInfos, $data)
    {
        $buy_num = $data['buy_num'];
        $weight = $data['weight'];
        $finalExpress = $data['final_express'];

        $current_num = 0;
        $equal_delivery_amount = '0';
        foreach ($deliveryBuyInfos as &$deliveryBuyInfo) {
            $current_num++;
            if ($current_num == count($deliveryBuyInfos)) {
                // 本次分配的最后一个商品
                $current_delivery_amount = bcsub($delivery_amount, $equal_delivery_amount, 2);
            } else {
                $scale = 0;                             // 重量或者数量计算比例
                if ($finalExpress['type'] == 'number') {
                    // 按件
                    if ($buy_num) {          // 字符串 0.00 是 true, 这里转下类型在判断
                        $scale = bcdiv((string)$deliveryBuyInfo['product_num'], (string)$buy_num, 6);
                    }

                    $current_delivery_amount = bcmul((string)$delivery_amount, (string)$scale, 2);
                } else {
                    // 按重量
                    if (floatval($weight)) {
                        $scale = bcdiv((string)$deliveryBuyInfo['weight'], (string)$weight, 6);
                    }

                    $current_delivery_amount = bcmul((string)$delivery_amount, (string)$scale, 2);
                }
            }

            $equal_delivery_amount = bcadd($equal_delivery_amount, $current_delivery_amount, 2);        // 记录已分配的运费金额

            $deliveryBuyInfo['delivery_amount'] = $current_delivery_amount;         // 每个商品分配到的实际应支付运费
        }

        return $deliveryBuyInfos;
    }



    /**
     * 将运费分配到商品上
     *
     * @param array $products
     * @return array
     */
    private function reCalcProductDelivery($products)
    {
        foreach ($this->deliveryInfos as $delivery_id => $deliveryInfo) {
            foreach ($deliveryInfo['buy_infos'] as $deliveryBuyInfo) {
                foreach ($products as &$buyInfo) {
                    if (
                        $deliveryBuyInfo['product_id'] == $buyInfo['product_id']
                        && $deliveryBuyInfo['product_sku_price_id'] == $buyInfo['product_sku_price_id']
                        && $deliveryBuyInfo['format_attributes_json'] == $buyInfo['format_attributes_json']     // 属性也必须相同
                    ) {
                        $buyInfo['delivery_amount'] = $deliveryBuyInfo['delivery_amount'];
                        $buyInfo['delivery_id'] = $delivery_id;
                        break;
                    }
                }
            }
        }

        return $products;
    }
}
