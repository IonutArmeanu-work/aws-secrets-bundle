<?php declare(strict_types=1);

namespace AwsSecretsBundle\DependencyInjection;

use Aws\SecretsManager\SecretsManagerClient;
use Exception;

/**
 * Class SecretsManagerClientFactory
 * @package AwsSecretsBundle\DependencyInjection
 * @author  James Matsumura <james@casechek.com>
 */
class SecretsManagerClientFactory
{
    /**
     * @param string $region
     * @param null|string $key
     * @param null|string $secret
     * @param null|string $version
     * @return SecretsManagerClient
     * @throws \Exception
     */
    public static function createClient(
        string $region,
        string $version,
        ?string $key,
        ?string $secret
    ): SecretsManagerClient
    {
        $config = [
            'region' => $region,
            'version' => $version
        ];

        if ($key && $secret) {
            $config['credentials'] = [
                'key' => $key,
                'secret' => $secret
            ];
        } else if (
            ($key && !$secret) ||
            (!$key && $secret)
        ) {
            throw new Exception('Both key and secret must be provided or neither');
        }

        return new SecretsManagerClient($config);
    }
}
