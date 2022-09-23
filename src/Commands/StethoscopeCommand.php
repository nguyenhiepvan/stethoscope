<?php

namespace MohsenAbrishami\Stethoscope\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class StethoscopeCommand extends Command
{
    public $storage;

    public function __construct()
    {
        parent::__construct();

        $this->storage = Storage::disk(config('stethoscope.storage.driver'));
    }


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stethoscope:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'monitor memory usage, cpu usage, network connection and nginx status';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $file = now()->format('Y-m-d');

        $log = '';

        if ($this->storage->exists($file))
            $log = $this->storage->get($file);

        $log = $this->cpuMonitor($log);
        $log = $this->memoryMonitor($log);
        $log = $this->networkConnection($log);
        $log = $this->webServerMonitor($log);

        if ($log != '')
            $this->storage->put($file, $log);
    }

    protected function cpuMonitor($log)
    {
        if (!config('stethoscope.monitoring_enable.cpu'))
            return null;

        $cpuUsage = exec(" grep 'cpu ' /proc/stat | awk '{print ($2+$4)*100/($2+$4+$5)}' ");

        $message = date('H:i:s') . " ===> cpu uage:  $cpuUsage \n";

        print_r($message);

        if ($cpuUsage > config(('stethoscope.thereshold.cpu')))
            $log .= $message;

        return $log;
    }

    protected function memoryMonitor($log)
    {
        if (!config('stethoscope.monitoring_enable.memory'))
            return null;

        $memoryUsage = exec(" free | grep Mem | awk '{print $3/$2 * 100.0}' ");

        $message = date('H:i:s') . " ===> memory uage:  $memoryUsage \n";

        print_r($message);

        if ($memoryUsage > config(('stethoscope.thereshold.memory')))
            $log .= $message;

        return $log;
    }

    protected function networkConnection($log)
    {
        if (!config('stethoscope.monitoring_enable.network'))
            return null;

        try {
            $networkConnction = Http::get('www.google.com')->successful();
        } catch (Exception $e) {
            $networkConnction = 'false';
        }

        $message = date('H:i:s') . " ===> network connection:  $networkConnction \n";

        print_r($message);

        if (!$networkConnction)
            $log .= $message;

        return $log;
    }

    protected function webServerMonitor($log)
    {
        if (!config('stethoscope.monitoring_enable.web_server'))
            return null;

        $nginxStatus = exec('systemctl status nginx', $out, $exit_code);

        $message = date('H:i:s') . " ===> nginx status:  $nginxStatus \n";

        print($message);

        if (!$nginxStatus)
            $log .= $message;

        return $log;
    }
}
