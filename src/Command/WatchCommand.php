<?php
/**
 * WatchCommand.php
 * PHP version 7
 *
 * @package webman-demo
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Command;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Timer;
use Workerman\Worker;

#[AsCommand('server:watch', 'server watch')]
class WatchCommand extends Command
{
    protected string $startFile;

    /**
     * @var array
     */
    protected array $paths = [];

    /**
     * @var array
     */
    protected array $extensions = [];

    /**
     * @var array
     */
    protected array $loadedFiles = [];

    /**
     * @return void
     */
    protected function configure()
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name description');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->startFile = base_path('start.php');
        if (! file_exists($this->startFile)) {
            $output->writeln('<error>Watch start.php not found</error>');
            return self::FAILURE;
        }

        $watch = config('plugin.vzina.attributes.watch');
        $this->paths = $watch['monitor_dir'] ?? [];
        $this->extensions = $watch['monitor_extensions'] ?? [];
        foreach (get_included_files() as $index => $file) {
            $this->loadedFiles[$file] = $index;
            if (strpos($file, 'webman-framework/src/support/App.php')) {
                break;
            }
        }

        $worker = new Worker();
        $worker->name = 'watcher';
        $worker->onWorkerStart = [$this, 'onWorkerStart'];
        $worker->onWorkerStop = [$this, 'onWorkerStop'];

        Worker::$pidFile = $watch['pid_file'] ?? runtime_path('watch-worker.pid');
        Worker::$logFile = $watch['log_file'] ?? runtime_path('logs/watch-worker.log');
        Worker::runAll();

        return self::SUCCESS;
    }

    public function onWorkerStart(): void
    {
        exec('composer dump-autoload -o --no-scripts -d ' . base_path() . ' 2>/dev/null');

        exec($this->getCmd(['start', '-d']));

        Timer::add(1, function () {
            $this->checkAllFilesChange();
        });
    }

    public function onWorkerStop(): void
    {
        exec($this->getCmd(['stop']));
    }

    protected function getCmd(array $params = []): string
    {
        return implode(' ', array_merge([PHP_BINARY, base_path('start.php')], $params));
    }

    protected function checkAllFilesChange(): bool
    {
        foreach ($this->paths as $path) {
            if ($this->checkFilesChange($path)) {
                return true;
            }
        }
        return false;
    }

    protected function checkFilesChange($monitorDir): bool
    {
        static $lastMtime, $tooManyFilesCheck;
        if (! $lastMtime) {
            $lastMtime = time();
        }
        clearstatcache();
        if (! is_dir($monitorDir)) {
            if (! is_file($monitorDir)) {
                return false;
            }
            $iterator = [new SplFileInfo($monitorDir)];
        } else {
            // recursive traversal directory
            $dirIterator = new RecursiveDirectoryIterator($monitorDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS);
            $iterator = new RecursiveIteratorIterator($dirIterator);
        }
        $count = 0;
        foreach ($iterator as $file) {
            $count++;
            /** var SplFileInfo $file */
            if (is_dir($file->getRealPath())) {
                continue;
            }
            // check mtime
            if (in_array($file->getExtension(), $this->extensions, true) && $lastMtime < $file->getMTime()) {
                $lastMtime = $file->getMTime();
                if (DIRECTORY_SEPARATOR === '/' && isset($this->loadedFiles[$file->getRealPath()])) {
                    echo "$file updated but cannot be reloaded because only auto-loaded files support reload.\n";
                    continue;
                }
                $var = 0;
                exec('"' . PHP_BINARY . '" -l ' . $file, $out, $var);
                if ($var) {
                    continue;
                }

                // send restart signal to master process for reload
                echo $file . " updated and restart\n";
                exec($this->getCmd(['restart', '-d']));
                return true;
            }
        }
        if (! $tooManyFilesCheck && $count > 1000) {
            echo "Monitor: There are too many files ($count files) in $monitorDir which makes file monitoring very slow\n";
            $tooManyFilesCheck = 1;
        }
        return false;
    }
}