<?php
/**
 * 业务多线程处理
 * @author winerQin
 * @date 2017-05-18
 */

include_once('Thread.php');

class Start extends Thread
{
    // 同时运行的进程数.
    const PROGRESS_NUM = 10;

    /**
     * Process.
     *
     * (1) 简单语句：空语句（就一个；号），return,break,continue,throw, goto,global,static,unset,echo, 内置的HTML文本，分号结束的表达式等均算一个语句。
     * (2) 复合语句：完整的if/elseif,while,do...while,for,foreach,switch,try...catch等算一个语句。
     * (3) 语句块：{} 括出来的语句块。
     * (4) 最后特别的：declare块本身也算一个语句(按道理declare块也算是复合语句，但此处特意将其独立出来)。
     * 所有的statement, function_declare_statement, class_declare_statement就构成了所谓的低级语句(low-level statement)。
     * 
     * -- for 循环在时间 declare(ticks = 1) 情况下只算一个语句。所以，此时会失效。
     * -- 针对这种复合语句以及语句块，所以，我们需要使用 pcntl_signal_dispatch() 告诉 PHP，我们触发了一个信号。
     * -- 在 run 中编写的方法请一定要确定是事务型的。要么成功要么失败。要处于好失败情况下的数据处理。
     * -- 已经将 pcntl_signal_dispatch() 写入 isTimeout();
     * 
     * @return void
     */
    public function run()
    {
        for (;;) {
            sleep(1);
        }
    }
}

// 执行多线程业务处理.
$objThread = Start::getInstance(Start::PROGRESS_NUM);
$objThread->setChildOverNewCreate(false);
$objThread->start();
