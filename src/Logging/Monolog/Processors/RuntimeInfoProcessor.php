<?php

declare(strict_types=1);

namespace NB\AppComponents\Logging\Monolog\Processors;

use Monolog\Processor\ProcessorInterface;

/**
 * 增加运行时信息processor.
 */
class RuntimeInfoProcessor implements ProcessorInterface
{
    private array $options = [
        'fieldName' => 'runtimeInfo',           // extra中保存信息的字段名
        'withLanguage' => true,                 // 是否记录程序语言及版本信息
        'withSAPI' => true,                     // 是否记录PHP SAPI名称
        'withPID' => true,                      // 是否记录当前进程的pid
        'withMemoryUsage' => true,              // 是否记录内存使用情况
        'memoryRealUsage' => true,              // 是否记录真实内存情况(系统分配的内存,而非仅仅emollac分配的内存)
        'memoryRealPeakUsage' => true,          // 是否记录真是内存峰值情况(系统分配的内存,而非仅仅emollac分配的内存)
    ];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    public function __invoke(array $records)
    {
        $fieldName = $this->options['fieldName'];

        if (!is_array($records['extra'][$fieldName] ?? [])) {
            goto end;
        }

        if ($this->options['withLanguage']) {
            $records['extra'][$fieldName]['lang'] = 'PHP-'.phpversion();
        }

        if ($this->options['withSAPI']) {
            $records['extra'][$fieldName]['sapi'] = php_sapi_name();
        }

        if ($this->options['withPID']) {
            $records['extra'][$fieldName]['pid'] = getmypid();
        }

        if ($this->options['withMemoryUsage']) {
            $records['extra'][$fieldName]['memoryUsage'] = memory_get_usage($this->options['memoryRealUsage']);
            $records['extra'][$fieldName]['memoryPeakUsage'] = memory_get_peak_usage($this->options['memoryRealPeakUsage']);
        }

        end:
        return $records;
    }
}