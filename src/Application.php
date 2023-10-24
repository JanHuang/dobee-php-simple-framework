<?php
declare(strict_types=1);
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2019
 *
 * @see      https://www.github.com/fastdlabs
 * @see      https://fastdlabs.com
 */

namespace FastD;


use RuntimeException;
use FastD\Config\Config;
use FastD\Container\Container;
use FastD\Runtime\Runtime;
use Monolog\Logger;

/**
 * Class Application.
 */
final class Application
{
    const VERSION = 'v5.0.0(newborn)';

    protected string $name;

    protected string $path;

    /**
     * Application constructor.
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param Container $container
     * @param Runtime $runtime
     */
    public function bootstrap(Container $container, Runtime $runtime): void
    {
        $config = load($this->path . '/config/app.php');

        date_default_timezone_set($config['timezone'] ?? 'PRC');
        $this->name = $config['name'] ?? 'fastd';

        $container->add('config', new Config($config));
        // 初始化异常处理
        $this->handleException($runtime);
        // 初始化日志处理
        $this->handleLogger($runtime);

        foreach ($config['services'] as $service) {
            $container->register(new $service);
        }
    }

    /**
     * @param Runtime $runtime
     */
    public function handleException(Runtime $runtime): void
    {
        set_exception_handler([$runtime, 'handleException']);

        set_error_handler(function ($code, $message) {
            throw new RuntimeException($message, $code);
        }, E_ERROR);
    }

    /**
     * @param Runtime $runtime
     */
    public function handleLogger(Runtime $runtime): void
    {
        $monolog = new Logger($this->name);
        $config = config()->get('logger');
        $defaultLogPath = app()->getPath() . '/runtime/log/' . date('Ymd') . '/' . app()->getName() . '.log';
        foreach ($config as $log) {
            [$handle, $path, $level] = $log;
            if (!empty($path)) {
                if ($path[0] == '/') {
                    $logPath = $path;
                } else {
                    $logPath = app()->getPath() . '/runtime/log/' . date('Ymd') . '/' . $path;
                }
            }
            $handler = new $handle($logPath??$defaultLogPath, $level);
            $monolog->pushHandler($handler);
        }
        container()->add('logger', $monolog);
    }
}
