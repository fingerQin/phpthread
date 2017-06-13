<?php
/**
 * 多进程工具封装。
 * -- 1、本工具请确定 PHP 已经安装了 pcntl 与 posix 两个扩展。
 * -- 2、进程信号
 * SIGHUP  : 挂起信号,编号为1。一般用于告诉守护进程平滑重启服务器。
 * SIGQUIT : 退出信号,编号为3。
 * SIGTERM : 软件终止信号,编号为15。
 * SIGINT  : 中断信号,编号为2。当用户输入 Ctrl + C 时由终端驱动程序发送 INT 信号。
 * SIGKILL : 杀死/删除进程，编号为9。
 * 
 * @author winerQin
 * @date 2017-05-17
 */

abstract class Thread
{
    private $threadNum        = 10;      // 总的进程数量。
    public static $instance   = null;    // 当前对象实例。
    private $childProCount    = 0;       // 当前子进程数量。 
    protected static $timeout = 3;       // 子进程超时时间。单位(秒)。 0代表不超时。需要子进程在指定方法实现。
    protected $startTime      = 0;       // 子进程启动时的时间戳。
    protected $shareMemoryId  = null;    // 共享内存ID。用于控制子进程平滑退出。

    /**
     * 构造方法实现单例。 
     *
     * @return void
     */
    private function __construct() {}

    /**
     * 单例对象实现。
     * @param  integer $threadNum 线程数量。
     * @return instance
     */
    final public static function getInstance($threadNum = 10) 
    {
        if (self::$instance == null) {
            self::$instance = new static;
        }
        if ($threadNum > 0) {
            self::$instance->threadNum = $threadNum;
        }
        return self::$instance;
    }

    /**
     * 启动脚本.
     */
    final public function start()
    {
        $pid = posix_getpid(); // 获取当前进程ID。亦是当前所有子进程的父进程ID。
        file_put_contents('master.pid', $pid);

        $shm_key = ftok(__FILE__, 't');
        $this->shareMemoryId = shmop_open($shm_key, "c", 0644, 100);
        shmop_write($this->shareMemoryId, "exit", 0);

        declare(ticks = 1);
        // 配合pcntl_signal使用，简单的说，是为了让系统产生时间云，让信号捕捉函数能够捕捉到信号量。
        if (function_exists('pcntl_fork')) {
            while(true) {
                $this->childProCount++; // 子进程数量加1。
                $pid = pcntl_fork();
                if ($pid) {
                    if ($this->childProCount >= $this->threadNum) {
                        // 如果已经达到指定的进程数量,则挂起当前主进程。直到有子进程退出才执行此
                        pcntl_wait($status);
                    }
                } else {
                    register_shutdown_function(array($this, "shutdown"));
                    $this->registerSubProcessPcntlSignal();
                    $this->startTime = time();
                    $this->run();
                    exit();
                }
            }
        } else {
            echo "You have no extension: pcntl_fork!\n";
            exit(0);
        }
    }

    /**
     * 获取进程数.
     */
    final public function getThreadNum()
    {
        return $this->threadNum;
    }

    /**
     * 设置进程数.
     *
     * @param integer $num 进程数量。
     * @return void
     */
    final public function setThreadNum($num)
    {
        $this->threadNum = $num;
    }

    /**
     * 设置子进程超时时间。
     *
     * @param integer $timeout 超时时间。单位(秒)。
     * 
     * @return void
     */
    final public function setTimeout($timeout = 60) 
    {
        self::$timeout = $timeout;
    }

    /**
     * 获取子进程超时时间。
     *
     * @return integer
     */
    final public function getTimeout()
    {
        return self::$timeout;
    }

    /**
     * 判断当前子进程超时了。
     * --1 当前时间戳 - 子进程启动时时间戳 > 子进程超时时间阀值
     * --2 请将该方法放在 run() 内部第一行运行，如果有循环，请放在每一次循环内部第一行运行。
     *
     * @param boolean $timeoutExit 超时是否退出。true-退出、false-不退出。只返回是否超时。
     * 
     * @return boolean|void true-超时、false-未超时。
     */
    protected function isTimeout($timeoutExit = false) 
    {
        pcntl_signal_dispatch();
        // 子进程接收父进程的指令。
        $subProcessOrder = shmop_read($this->shareMemoryId, 0, 4);
        if ($subProcessOrder == "exit") {
            exit(0);
        }
        $time = time();
        if (($time - $this->startTime) > self::$timeout) {
            if ($timeoutExit == true) {
                exit(0);
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * 给子进程注册指定信号。
     *
     * -- 以下信号注册之后，通过指定的方法来处理。
     * -- 可以实现子进程异常退出的重启。
     * -- 如果不注册进程退出的信号，则系统会以信号的定义进行进程的处理。我们这里选择忽略。
     * -- 只有时钟信号和 SIGKILL 信号会关闭子进程。
     * 
     * @return void
     */
    final protected function registerSubProcessPcntlSignal() 
    {
        pcntl_signal(SIGTERM, array($this, "signalSubProcessHandler"));
        pcntl_signal(SIGHUP, array($this, "signalSubProcessHandler"));
        pcntl_signal(SIGINT, array($this, "signalSubProcessHandler"));
        pcntl_signal(SIGCHLD, array($this, "signalSubProcessHandler"));
        pcntl_signal(SIGQUIT, array($this, "signalSubProcessHandler"));
        pcntl_signal(SIGILL, array($this, "signalSubProcessHandler"));
        pcntl_signal(SIGPIPE, array($this, "signalSubProcessHandler"));
    }

    /**
     * 子进程指定信号处理器。
     *
     * @param integer $signo 信号编号。
     * 
     * @return void
     */
    final protected function signalSubProcessHandler($signo) 
    {
        $subPID  = posix_getpid();
        $errData = error_get_last();
        $errStr  = json_encode($errData);
        $this->writeLog("The child process (PID: {$subPID}) receives the signal: {$signo}.");
    }

    /**
     * 写日志。
     * @param  string $log 日志内容。
     * @return void
     */
    final protected function writeLog($log) 
    {
        $time = time();
        $logFilePath = date('YmdH', $time) . '.log';
        $log = date('Y-m-d H:i:s') . " ErrMsg:" . $log . "\n";
        file_put_contents($logFilePath, $log, FILE_APPEND);
    }

    /**
     * 子进程脚本正常退出时访问该方法。
     * @return void
     */
    protected function shutdown() 
    {
        $errInfo = error_get_last();
        if (!empty($errInfo)) {
            $this->writeLog(json_encode($errInfo));
        } else {
            $this->writeLog('The PHP script exits normally!');
        }
    }

    /**
     * 抽象的业务方法.
     */
    abstract public function run();

}
