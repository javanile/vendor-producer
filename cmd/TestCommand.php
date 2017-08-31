<?php
/**
 * Test command for producer.
 *
 * PHP version 5
 *
 * @category   ProducerCommand
 *
 * @author     Francesco Bianco <bianco@javanile.org>
 * @license    https://goo.gl/KPZ2qI  MIT License
 * @copyright  2015-2017 Javanile.org
 */

namespace Javanile\Producer\Commands;

class TestCommand extends Command
{
    /**
     * InitCommand constructor.
     *
     * @param $cwd
     */
    public function __construct($cwd)
    {
        $this->cwd = $cwd;
    }

    /**
     * Run test command.
     *
     * @param $args
     *
     * @return string
     */
    public function run($args)
    {
        // test if phpunit are installed
        $phpunit = $this->cwd.'/vendor/bin/phpunit';
        if (!file_exists($phpunit)) {
            return "> Producer: Install phpunit via composer (not global).\n";
        }

        // run all tests on all repository projects
        if (!isset($args[0]) || !$args[0]) {
            return $this->rulAllTests();
        }

        // run all tests on one project
        if (is_dir($this->cwd.'/repository/'.$args[0])) {
            return $this->runProjectTests($args);
        }

        //
        $test = str_replace('\\', '/', $args[0]).'Test';

        // run root-project test file if exist
        if (file_exists($file = $this->cwd.'/tests/'.$test.'.php')) {
            return $this->runFileTests($file, $args);
        }

        // run single unit test throught repository projects
        $path = $this->cwd.'/repository/';
        foreach (scandir($path) as $name) {
            if ($name[0] == '.' || !is_dir($path.'/'.$name)) {
                continue;
            }
            if (file_exists($file = $path.'/'.$name.'/tests/'.$test.'.php')) {
                return $this->runFileTests($name, $test, $file, $args);
            }
        }

        return "> Producer: Test case class '{$args[0]}' not found.\n";
    }

    /**
     *
     */
    private function runAllTests()
    {
        $test = 'tests';
        $path = $this->cwd.'/repository';

        foreach (scandir($path) as $name) {
            if ($name[0] != '.' && is_dir($path.'/'.$name)) {
                echo shell_exec(__DIR__.'/../exec/test-dox.sh '.$this->cwd.' '.$name.' '.$test);
            }
        }
    }

    /**
     * Run project tests.
     */
    private function runProjectTests($args)
    {
        $name = $args[0];

        return $this->exec('test-dox', [$name, 'tests']);
    }

    /**
     * Run test inside a test case file.
     */
    private function runFileTests($name, $test, $file, $args)
    {
        $item = isset($args[1]) ? intval($args[1]) : null;

        if (!$item) {
            return $this->exec('test-dox', [$name, 'tests/'.$test]);
        }

        $classes = get_declared_classes();
        require_once $file;
        $diff = array_diff(get_declared_classes(), $classes);
        $class = array_pop($diff);
        if (!class_exists($class)) {
            return "> Producer: Test class '{$class}' not found.\n";
        }
        $methods = array_filter(get_class_methods($class), function ($method) {
            return preg_match('/^test[A-Z]/', $method);
        });
        if (!isset($methods[$item - 1])) {
            return "> Producer: Test class '{$class}' have less than '{$item}' methods.\n";
        }

        $filter = "/::".$methods[$item - 1]."/";

        return $this->exec('test-filter', [$name, 'tests/'.$test, $filter]);
    }
}
