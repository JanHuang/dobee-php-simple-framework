<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2017
 *
 * @see      https://www.github.com/janhuang
 * @see      https://fastdlabs.com
 */

namespace FastD\Process;

use FastD\Swoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Process.
 */
class ProcessManager extends Command
{
    /**
     * @var string
     */
    protected $pidPath;

    /**
     * php bin/console process {name} {args} {options}.
     */
    protected function configure()
    {
        $this->setName('process');
        $this->addArgument('process', InputArgument::OPTIONAL, 'process name');
        $this->addArgument('action', InputArgument::OPTIONAL, 'process action, <comment>status|start|stop|reload</comment>', 'status');
        $this->addOption('pid', '-p', InputOption::VALUE_OPTIONAL, 'set process pid path.');
        $this->addOption('name', null, InputOption::VALUE_OPTIONAL, 'set process name.', null);
        $this->addOption('daemon', '-d', InputOption::VALUE_NONE, 'set process daemonize.');
        $this->setDescription('Create new processor.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $this->targetDirectory($input);

        $processName = $input->getArgument('process');

        if ($input->hasParameterOption(['--list', '-l']) || empty($processName)) {
            return $this->showProcesses($input, $output);
        }

        $processes = config()->get('processes', []);

        if (!isset($processes[$processName])) {
            throw new \RuntimeException(sprintf('Process %s cannot found', $processName));
        }

        $config = $processes[$processName];
        if (!class_exists($config['process'])) {
            throw new \RuntimeException(sprintf('Process class "%s" is not found.', $processName));
        }
        $name = $input->getOption('name');
        if (empty($name)) {
            $name = $processName;
        }
        $process = $config['process'];
        $options = $config['options'];
        $process = new $process($name);
        if (!($process instanceof Process)) {
            throw new \RuntimeException('Process must be instance of \FastD\Swoole\Process');
        }
        if ($input->hasParameterOption(['--daemon', '-d'])) {
            $process->daemon();
        }

        $file = $path.'/'.$processName.'.pid';

        switch ($input->getArgument('action')) {
            case 'start':
                $pid = $process->start();
                file_put_contents($file, $pid);
                $output->writeln(sprintf('process <info>%s</info> pid: <info>%s</info>', $process->getName(), $pid));
                $output->writeln(sprintf('pid: <info>%s</info>', $file));

                $process->wait(function ($ret) use ($name) {
                    return $this->finish($name, $ret['pid'], $ret['code'], $ret['signal']);
                });

                break;
            case 'status':
            default:
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     *
     * @return string
     */
    protected function targetDirectory(InputInterface $input)
    {
        $pid = $input->getParameterOption(['--path', '-p']);

        if (empty($pid)) {
            $path = app()->getPath().'/runtime/process';
        } else {
            $path = dirname($pid);
        }
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        return $this->pidPath = $path;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function showProcesses(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['Process', 'Pid', 'Status', 'Start At', 'Runtime']);
        $rows = [];
        foreach (config()->get('processes', []) as $name => $processor) {
            $rows[] = $this->getProcessInfo($name);
        }
        $table->setRows($rows);
        $table->render();

        return 0;
    }

    /**
     * @param $name
     *
     * @return array
     */
    protected function getProcessInfo($name)
    {
        $pidFile = $this->pidPath.'/'.$name.'.pid';
        $pid = file_exists($pidFile) ? (int) file_get_contents($pidFile) : '';
        $isRunning = process_kill($pid, 0);

        return [
            $name,
            $isRunning ? $pid : '',
            $isRunning ? 'running' : 'stopped',
            $isRunning ? date('Y-m-d H:i:s', filemtime($pidFile)) : '',
            $isRunning ? time() - filemtime($pidFile) : '',
        ];
    }

    /**
     * @param $name
     * @param $pid
     * @param int $code
     * @param int $signal
     */
    protected function finish($name, $pid, $code = 0, $signal = 0)
    {
        output()->writeln(sprintf('process: %s. pid: %s exit. code: %s. signal: %s', $name, $pid, $code, $signal));
    }
}