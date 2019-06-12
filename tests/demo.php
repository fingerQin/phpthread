<?php
/**
 * 示例1。
 * @author fingerQin
 * @date 2019-06-12
 */

require '../vendor/autoload.php';

use \PHPProcess\Thread;

class Start extends Thread
{
    /**
     * Process.
     * 
     * @param  int  $threadNum     进程数量。
     * @param  int  $num           子进程编号。
     * @param  int  $startTimeTsp  子进程启动时间戳。
     * 
     * @return void
     */
    public function run($threadNum, $num, $startTimeTsp)
    {
        while (true) {
            sleep(1);
            $this->isExit($startTimeTsp); // 循环操作时调用。主要为了避免平滑重启时子进程运行异常导致业务中断。
        }
    }
}

// 执行多线程业务处理.
$objThread = Start::getInstance(2); // 2 个子进程。
$objThread->setChildOverNewCreate(true);
// 子进程运行 60 秒自动退出重启新的子进程。一般建议每天重启一次。
$objThread->setRunDurationExit(60);
$objThread->start();