<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @see      https://www.github.com/janhuang
 * @see      http://www.fast-d.cn/
 */

namespace FastD;

use FastD\Console\Config;
use FastD\Console\Controller;
use FastD\Console\Model;
use FastD\Console\Processor;
use FastD\Console\Queue;
use FastD\Console\Routing;
use FastD\Console\Seeder;
use Symfony\Component\Console\Application as Symfony;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AppConsole.
 */
class Console extends Symfony
{
    /**
     * AppConsole constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        parent::__construct($app->getName(), Application::VERSION);

        $this->addCommands([
            new Seeder(),
            new Model(),
            new Controller(),
            new Routing(),
            new Config(),
            new Processor(),
            new Queue(),
        ]);

        $this->registerCommands();
    }

    /**
     * Scan commands.
     */
    public function registerCommands()
    {
        foreach (config()->get('consoles', []) as $console) {
            $this->add(new $console());
        }

        if (false !== ($files = glob(app()->getPath().'/src/Console/*.php', GLOB_NOSORT | GLOB_NOESCAPE))) {
            foreach ($files as $file) {
                $command = '\\Console\\'.pathinfo($file, PATHINFO_FILENAME);
                $this->add(new $command());
            }
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws \Throwable
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        try {
            return parent::doRun($input, $output);
        } catch (\Exception $exception) {
            app()->handleException($exception);
        }

        throw $exception;
    }
}
