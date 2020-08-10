<?php

declare(strict_types=1);

namespace NB\AppComponents\Logging\Monolog\Processors;

use Monolog\Processor\ProcessorInterface;

/**
 * 增加hostname processor.
 */
class HostnameProcessor implements ProcessorInterface
{
    /**
     * @var array processor 处理选项
     */
    private array $options = [
        'fieldName' => 'hostname',              // extra中保存信息的字段名
    ];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @param array $records 符合monolog record结构, 参见: https://github.com/Seldaek/monolog/blob/master/doc/message-structure.md
     *
     * @return array
     */
    public function __invoke(array $records)
    {
        $fieldName = $this->options['fieldName'];
        if ($hostname = gethostname()) {
            $records['extra'][$fieldName]['hostname'] = $hostname;
        }

        return $records;
    }
}