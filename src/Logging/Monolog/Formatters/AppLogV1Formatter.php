<?php

declare(strict_types=1);

namespace NB\AppComponents\Logging\Monolog\Formatters;

use Monolog\Formatter\FormatterInterface;
use NB\AppComponents\Logging\Exceptions\IllegalServiceException;

class AppLogV1Formatter implements FormatterInterface
{
    use HelperTrait;

    const SCHEMA = 'app.log.v1';

    /**
     *  @var string Service name.
     */
    protected string $service;

    /**
     * @var string Environment, e.g: "dev", "test", "prod".
     */
    protected string $environment;

    /**
     * @var array Logging level names.
     */
    protected static array $levelNames = [
        'DEBUG' => 'debug',
        'INFO' => 'info',
        'NOTICE' => 'info',
        'WARNING' => 'warning',
        'ERROR' => 'error',
        'CRITICAL' => 'fatal',
        'ALERT' => 'fatal',
        'EMERGENCY' => 'panic',
    ];

    /**
     * @param string $service
     * @param string $environment
     *
     * @throws IllegalServiceException service shouldn't be empty
     */
    public function __construct(string $service, string $environment)
    {
        if (empty($service)) {
            throw new IllegalServiceException();
        }

        $this->service = $service;
        $this->environment = $environment;
    }

    /**
     * Return formatted json string.
     *
     * {@inheritdoc}
     *
     * @param array $record
     *      [
     *          'message' => (string) $message,
     *          'context' => $context,
     *          'level' => $level,
     *          'level_name' => $levelName,
     *          'channel' => $this->name,
     *          'datetime' => $ts,
     *          'extra' => array(),
     *      ]
     *
     * @return string json string of array
     *      [
     *          schema: (string),        // this field always equals 'app.log.v1'
     *          t: (string),             // time, iso8601 format with millsecond, the same as golang
     *          l: (string),             // level, enum of https://gitlab.haochang.tv/orz/protobuf/blob/master/protobuf/logiterator/main.proto 内的Level为准
     *          s: (string),             // service
     *          c: (string),             // channel
     *          e: (string),             // environment
     *          m: (string),             // msg
     *          ctx: { … },              // context
     *      ]
     */
    public function format(array $record)
    {
        $context = $record['context'] ?? [];
        if (!is_array($context)) {
            $type = $this->getType($context);
            $context = [];
            $context['ctxErr'][] = [
                'errMsg' => 'invalid context type in log data',
                'type' => $type,
            ];
        }

        // 保证context是一个关联数组
        if (isset($context[0])) {
            $context['ctxErr'][] = [
                'errMsg' => 'context should be an assoc array',
            ];
        }

        $l = static::$levelNames[$record['level_name']] ?? 'warning';
        if (!isset(static::$levelNames[$record['level_name']])) {
            $context['ctxErr'][] = [
                'errMsg' => 'undefined level name',
                'levelName' => $record['level_name'],
            ];
        }

        $dt = $record['datetime'] ?? new \DateTime();
        if (!($dt instanceof \DateTimeInterface)) {
            $dt = new \DateTime();
        }

        $data = [
            'schema' => self::SCHEMA,
            't' => $dt->format('c'),
            'l' => $l,
            's' => $this->service,
            'c' => $record['channel'],
            'e' => $this->environment,
            'm' => $record['message'],
            'ctx' => $context ?: new class{},
        ];

        $options = JSON_UNESCAPED_SLASHES;

        // 不使用JSON_UNESCAPED_UNICODE
        // syslog写入到kafka时，有emoji字符时会导致数据丢失
        $raw = json_encode($data, $options);
        if ($raw === false) {
            $data['ctx'] = [
                'error' => [
                    'msg' => "json encode app log error: ". json_last_error_msg(),
                ],
            ];

            return json_encode($data, $options) . "\n";
        }

        return "{$raw}\n";
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }

        return $records;
    }
}
