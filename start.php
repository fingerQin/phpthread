<?php
/**
 * 业务多线程处理
 * @author winerQin
 * @date 2017-05-18
 */

include_once('Thread.php');

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
        $startDate = date('Y-m-d H:i:s', $startTimeTsp);
        for (;;) {
            $pid = posix_getpid();
            file_put_contents('log', "启动时间：{$startDate},主进程PID:{$this->masterPid},子进程PID：{$pid},数量:{$threadNum}，编号：{$num}\n", FILE_APPEND);
            sleep(3);
            $this->isExit($startTimeTsp); // 循环操作时间调用。
        }
    }
}

// 执行多线程业务处理.
$objThread = Start::getInstance(2); // 2 个子进程。
$objThread->setChildOverNewCreate(true);
$objThread->setRunDurationExit(10);
$objThread->start();
