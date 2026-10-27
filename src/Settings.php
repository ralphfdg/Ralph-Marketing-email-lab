<?php

declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

final class Settings
{
    public static function required(string $key): string
    {
        $val = getenv($key);
        if ($val === false || trim($val) === '') {
            throw new RuntimeException("Missing required environment variable: {$key}");
        }
        return trim($val);
    }

    public static function optional(string $key, string $default = ''): string
    {
        $val = getenv($key);
        return ($val === false || trim($val) === '') ? $default : trim($val);
    }

    public static function queue(): SqsClient
    {
        $config = [
            'version' => 'latest',
            'region'  => self::optional('AWS_REGION', 'us-east-1'),
        ];

        $profile = getenv('AWS_PROFILE');
        if ($profile !== false && trim($profile) !== '') {
            $config['profile'] = trim($profile);
        }

        return new SqsClient($config);
    }

    public static function mailtrap(): array
    {
        return [
            'host'         => self::optional('MAILTRAP_HOST', 'sandbox.smtp.mailtrap.io'),
            'port'         => (int) self::optional('MAILTRAP_PORT', '2525'),
            'username'     => self::required('MAILTRAP_USERNAME'),
            'password'     => self::required('MAILTRAP_PASSWORD'),
            'from_address' => self::optional('MAIL_FROM_ADDRESS', 'marketing@example.com'),
        ];
    }

    public static function sendDelaySeconds(): int
    {
        return max(0, (int) self::optional('MAILTRAP_DELAY_SECONDS', '11'));
    }
}