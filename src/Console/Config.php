<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @see      https://www.github.com/janhuang
 * @see      https://fastdlabs.com
 */

namespace FastD\Console;

use FastD\Utils\FileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Config.
 */
class Config extends Command
{
    public function configure()
    {
        $this->setName('config');
        $this->addArgument('name', InputArgument::OPTIONAL, 'file name');
        $this->setDescription('Dump application config information.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return mixed
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('name')) {
            $file = app()->getPath().'/config/'.$input->getArgument('name').'.php';
            $file = str_replace('.php.php', '.php', $file);
            $config = load($file);
            $output->write('<comment>'.json_encode($config, JSON_PRETTY_PRINT).'</comment>');

            return 0;
        }
        $table = new Table($output);
        $rows = [];
        $table->setHeaders(array('File', 'Config', 'Owner', 'Modify At'));

        foreach (glob(app()->getPath().'/config/*') as $file) {
            $file = new FileObject($file);
            $config = load($file->getPathname());
            $count = 0;
            if (is_array($config)) {
                $count = count(array_keys($config));
            }
            $rows[] = [
                $file->getFilename(),
                $count.' Keys',
                posix_getpwuid($file->getOwner())['name'],
                date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        $table->setRows($rows);
        $table->render();
    }
}
