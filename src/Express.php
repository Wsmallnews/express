<?php

namespace Wsmallnews\Express;

use Wsmallnews\Express\Adapters\KdniaoAdapter;
use Wsmallnews\Express\Adapters\ManualAdapter;
use Wsmallnews\Express\Adapters\WechatAdapter;
use Wsmallnews\Express\Exceptions\ExpressException;

class Express
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * expresses 快递驱动列表
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * 注册的自定义 驱动列表
     *
     * @var array
     */
    protected $customCreators = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 获取一个 driver 实例
     *
     * @param  string|null  $name
     * @return Sender
     */
    public function driver($name = null)
    {
        return $this->expresser($name);
    }

    /**
     * 获取一个 driver 实例
     *
     * @param  string|null  $name
     * @return Sender
     */
    public function expresser($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] = $this->get($name);
    }

    /**
     * 尝试从缓存中获取 driver 实例
     *
     * @param  string  $name
     * @return Sender
     */
    protected function get($name)
    {
        return $this->drivers[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve driver
     *
     * @param  string  $name
     * @param  array|null  $config
     * @return Sender
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, $config = null)
    {
        $config ??= $this->getConfig($name);

        if (empty($config['driver'])) {
            throw new ExpressException("快递驱动 [{$name}] 为空.");
        }

        $name = $config['driver'];

        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($name) . 'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new ExpressException("驱动 [{$name}] 不支持.");
        }

        return $this->{$driverMethod}($config);
    }

    /**
     * Call a custom driver creator.
     *
     * @return Sender
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($config);
    }

    /**
     * 创建一个 wechat 发货实例
     *
     * @return Sender
     */
    public function createWechatDriver(array $config)
    {
        $adapter = new WechatAdapter($config);

        return new Sender($adapter);
    }

    /**
     * 创建一个 wechat 发货实例
     *
     * @return Sender
     */
    public function createKdniaoDriver(array $config)
    {
        $adapter = new KdniaoAdapter($config);

        return new Sender($adapter);
    }

    /**
     * 创建一个 手动发货实例
     *
     * @return Sender
     */
    public function createManualDriver(array $config)
    {
        $adapter = new ManualAdapter($config);

        return new Sender($adapter);
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['sn-express.default'];
    }

    /**
     * Get the filesystem connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["sn-express.disks.{$name}"] ?: [];
    }
}
