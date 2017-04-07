<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace FastD\Console;

use Phinx\Config\Config as MConfig;
use Phinx\Console\Command\Migrate;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SeedRun.
 */
class SeedRun extends Migrate
{
    protected $env = 'default';

    /**
     * @var bool
     */
    protected $booted = false;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function loadManager(InputInterface $input, OutputInterface $output)
    {
        if (null === $this->getManager()) {
            $manager = new Migration($this->getConfig(), $input, $output);
            $this->setManager($manager);
        }
    }

    public function configure()
    {
        parent::configure();
        $path = app()->getPath().'/database/schema';
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
        $this->setName('seed:run');
        $database = config()->get('database');
        $env = [];
        $keys = array_keys($database);
        $default = $keys[0];
        foreach ($database as $name => $config) {
            $env[$name] = [
                'adapter' => 'mysql',
                'host'    => config()->get('database.'.$name.'.host'),
                'name'    => config()->get('database.'.$name.'.name'),
                'user'    => config()->get('database.'.$name.'.user'),
                'pass'    => config()->get('database.'.$name.'.pass'),
                'port'    => config()->get('database.'.$name.'.port'),
                'charset' => config()->get('database.'.$name.'.charset', 'utf8'),
            ];
        }
        $this->setConfig(new MConfig([
            'paths' => [
                'migrations' => $path,
                'seeds'      => $path,
            ],
            'environments' => array_merge([
                'default_database' => $default,
            ], $env),
        ]));
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        if (false === $this->booted) {
            parent::bootstrap($input, $output);
            $this->booted = true;
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $config = $this->getConfig()->getEnvironment($this->env);
        $adapter = $this->getManager()->getEnvironment($this->env)->getAdapter();
        if (!$adapter->hasDatabase($config['name'])) {
            $adapter->createDatabase($config['name']);
        }

        $code = parent::execute($input, $output);

        return $code;
    }
}

/**
 * Class Migration.
 */
class Migration extends Manager
{
    /**
     * Gets an array of the database migrations, indexed by migration name (aka creation time) and sorted in ascending
     * order.
     *
     * @throws \InvalidArgumentException
     *
     * @return AbstractMigration[]
     */
    public function getMigrations()
    {
        if (null === $this->migrations) {
            $phpFiles = $this->getMigrationFiles();

            // filter the files to only get the ones that match our naming scheme
            $fileNames = [];
            /** @var AbstractMigration[] $migrations */
            $migrations = [];

            $version = date('Ymd');
            $versionIncrementSeed = mt_rand(1000, 9999 - count($phpFiles));

            foreach ($phpFiles as $filePath) {
                $class = pathinfo($filePath, PATHINFO_FILENAME);
                $fileNames[$class] = basename($filePath);
                require_once $filePath;
                $migration = new $class($version.(++$versionIncrementSeed), $this->getInput(), $this->getOutput());

                if (!($migration instanceof AbstractMigration)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The class "%s" in file "%s" must extend \Phinx\Migration\AbstractMigration',
                        $class,
                        $filePath
                    ));
                }

                $migrations[$migration->getName()] = $migration;
            }

            ksort($migrations);
            $this->setMigrations($migrations);
        }

        return $this->migrations;
    }
}
