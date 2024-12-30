<?php

declare(strict_types=1);

namespace addons\estore\package\delivery\express;

use addons\estore\package\delivery\model\ExpressPackage as ExpressPackageModel;
use addons\estore\package\delivery\model\ExpressPackageLog as ExpressPackageLogModel;
use addons\estore\package\support\traits\HasQuery;
use addons\estore\package\support\traits\HasScopeType;
use addons\estore\package\support\traits\HasTableType;
use think\Collection;

class PackageManager
{
    use HasQuery;
    use HasScopeType;
    use HasTableType;

    // 包裹信息
    public $express_name = null;

    public $express_code = null;

    public $express_no = null;

    public $model = null;

    public function __construct()
    {
        $this->model = function () {
            // 默认加上 scopeType 条件
            $query = (new ExpressPackageModel)
                ->scopeInfo($this->getScopeType(), $this->getStoreId());

            if ($this->getTableType()) {
                // 如果设置了 tableType 则约束
                $query->tableType($this->getTableType());
            }

            if ($this->getTableId()) {
                $query->tableId($this->getTableId());
            }

            return $query;
        };
    }

    /**
     * 获取指定 id 的包裹
     *
     * @param  int  $id
     * @return \think\Model
     */
    public function getPackage($id)
    {
        return $this->getQuery()->where('id', $id)->find();
    }

    /**
     * 获取 tableInfo 相关的所有包裹
     *
     * @param  array  $tableInfo
     * @return array|Collection
     */
    public function getPackageByTableInfo($tableInfo)
    {
        return $this->getQuery()->tableInfo($tableInfo['table_type'], $tableInfo['table_id'])->select();
    }

    /**
     * 根据快递信息 获取包裹
     *
     * @param  string  $express_code
     * @param  string  $express_no
     * @param  array  $extra
     * @return \think\Model
     */
    public function getPackageByExpressInfo($express_code, $express_no, $extra)
    {
        $query = ExpressPackageModel::where('express_code', $express_code)
            ->where('express_no', $express_no);

        foreach ($extra as $field => $value) {
            $query = $query->where('ext$.' . $field, $value);
        }

        return $query->find();
    }

    /**
     * 设置快递信息
     *
     * @return self
     */
    public function setExpressInfo(array $info = [])
    {
        $this->express_name = $info['express_name'];
        $this->express_code = $info['express_code'];
        $this->express_no = $info['express_no'];

        return $this;
    }

    /**
     * 添加包裹信息
     *
     * @param  array  $options
     * @return \think\Model
     */
    public function save($options = [])
    {
        $expressPackage = new ExpressPackageModel;
        $expressPackage->user_id = $options['user_id'] ?? 0;
        $expressPackage->scope_type = $this->getScopeType();
        $expressPackage->store_id = $this->getStoreId();
        $expressPackage->table_type = $this->getTableType();
        $expressPackage->table_id = $this->getTableId();

        $expressPackage->express_name = $this->express_name;
        $expressPackage->express_code = $this->express_code;
        $expressPackage->express_no = $this->express_no;

        $expressPackage->driver = $options['driver'];
        $expressPackage->ext = $options['ext'] ?? null;
        $expressPackage->save();

        return $expressPackage;
    }

    /**
     * 编辑包裹信息
     *
     * @param  array  $options
     * @return \think\Model
     */
    public function update($expressPackage, $options = [])
    {
        $ext = $expressPackage->ext;
        if (isset($options['ext']) && is_array($options['ext'])) {
            $ext = $expressPackage->ext ? array_merge($expressPackage->ext, $options['ext']) : $options['ext'];
        }

        $expressPackage->user_id = $options['user_id'] ?? $expressPackage->user_id;

        $expressPackage->express_name = $this->express_name;
        $expressPackage->express_code = $this->express_code;
        $expressPackage->express_no = $this->express_no;

        $expressPackage->driver = $options['driver'] ?? $expressPackage->driver;
        $expressPackage->ext = $ext;
        $expressPackage->save();

        return $expressPackage;
    }

    /**
     * 更新包裹轨迹
     *
     * @param  array|\think\Model  $expressPackage
     * @param  array  $tracesData
     * @return \think\Model
     */
    public function updateStatusTraces($expressPackage, $tracesData)
    {
        $status = $tracesData['status'];
        $traces = $tracesData['traces'];

        $expressPackage->status = $status;
        $expressPackage->save();

        $this->syncTraces($expressPackage, $traces);

        return $expressPackage;
    }

    /**
     * 更新物流信息
     *
     * @param  array|\think\Model  $expressPackage
     * @param  array  $traces
     * @return void
     */
    protected function syncTraces($expressPackage, $traces)
    {
        // 查询现有轨迹记录
        $logs = ExpressPackageLogModel::where('express_package_id', $expressPackage->id)->select();

        $log_count = count($logs);
        if ($log_count > 0) {
            // 移除已经存在的记录
            array_splice($traces, 0, $log_count);
        }

        // 增加包裹记录
        foreach ($traces as $k => $trace) {
            $log = new ExpressPackageLogModel;
            $log->express_package_id = $expressPackage->id;
            $log->content = $trace['content'];
            $log->change_date = $trace['change_date'];
            $log->status = $trace['status'];
            $log->save();
        }
    }
}
