# 说明
PHP  以 pcntl 与 posix 两个扩展实现的 PHP 多进程工具。简单实用，方便一些轻量级的后台任务实现并发处理。而 pcntl 与 posix 只有 *unix 环境支持。也就是说 windows 系统不支持。

# 使用
1. 使用 composer 安装即可。
```
$ composer require fingerqin/phpthread 1.0
```

2. 示例
`start.php`

```
<?php
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
```

## 启动 ##
```
php start.php
```

后台启动
```
nohup php start.php &
```

默认日志是写入用户目录的 nohup.log

也可以使用 `Supervisor` 进程管理工具实现进程的监控与重启。


## 结合其他框架使用 ##
在具体的框架业务类中继承 Thread 类，实现其 run() 方法。然后运行就可以了。

## 注意 ##
1) 请确定安装了 pcntl 和 posix 扩展。
2) 请确保是在 Cli 模式运行。

## 平滑重启 ##
```
// 只需要 kill 掉主进程的 PID 即可实现子进程平常重启。
$kill -9 PID
```
