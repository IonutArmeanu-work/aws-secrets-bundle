<?php declare(strict_types=1);

namespace AwsSecretsBundle\DependencyInjection;

use Aws\SecretsManager\SecretsManagerClient;
use AwsSecretsBundle\AwsSecretsEnvVarProcessor;
use AwsSecretsBundle\Command\AwsSecretValueCommand;
use AwsSecretsBundle\Provider\AwsSecretsArrayEnvVarProvider;
use AwsSecretsBundle\Provider\AwsSecretsCachedEnvVarProvider;
use AwsSecretsBundle\Provider\AwsSecretsEnvVarProvider;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AwsSecretsExtension
 * @package AwsSecretsBundle\DependencyInjection
 * @author  Joe Mizzi <joe@casechek.com>
 *
 * @codeCoverageIgnore
 */
class AwsSecretsExtension extends Extension
{
    /**
     * Loads a specific configuration.
     *
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $configs = $this->processConfiguration($configuration, $configs);

        $container->setParameter('aws_secrets.ttl', $configs['ttl']);
        $container->setParameter('aws_secrets.ignore', $configs['ignore']);
        $container->setParameter('aws_secrets.delimiter', $configs['delimiter']);

        $container->register('aws_secrets.secrets_manager_client', SecretsManagerClient::class)
            ->setFactory([SecretsManagerClientFactory::class, 'createSecretsManagerClient'])
            ->addArgument($configs['client_config'])
            ->addArgument($configs['ignore'])
            ->setPublic(false);

        $container->setAlias('aws_secrets.client', 'aws_secrets.secrets_manager_client')
            ->setPublic(true);

        if ($configs['cache'] === 'apcu') {
            $definition = new ChildDefinition('cache.adapter.apcu');
        } elseif ($configs['cache'] === 'filesystem') {
            $definition = new ChildDefinition('cache.adapter.filesystem');
        } else {
            $definition = new ChildDefinition('cache.adapter.array');
        }

        $definition->addTag('cache.pool');
        $container->setDefinition('aws_secrets.cache', $definition);

        $container->register('aws_secrets.env_var_provider', AwsSecretsEnvVarProvider::class)
            ->setArgument('$secretsManagerClient', new Reference('aws_secrets.client'));

        $container->register('aws_secrets.env_var_cached_provider', AwsSecretsCachedEnvVarProvider::class)
            ->setArgument('$cacheItemPool', new Reference('aws_secrets.cache'))
            ->setArgument('$decorated', new Reference('aws_secrets.env_var_provider'))
            ->setArgument('$ttl', $container->getParameter('aws_secrets.ttl'));

        $container->register('aws_secrets.env_var_array_provider', AwsSecretsArrayEnvVarProvider::class)
            ->setArgument('$decorated', new Reference('aws_secrets.env_var_cached_provider'));

        $container->register('aws_secrets.env_var_processor', AwsSecretsEnvVarProcessor::class)
            ->setArgument('$provider', new Reference('aws_secrets.env_var_array_provider'))
            ->setArgument('$ignore', $container->getParameter('aws_secrets.ignore'))
            ->setArgument('$delimiter', $container->getParameter('aws_secrets.delimiter'))
            ->addTag('container.env_var_processor');

        $container->register('aws_secrets.secret_value.command', AwsSecretValueCommand::class)
            ->setArgument('$secretsManagerClient', new Reference('aws_secrets.client'))
            ->addTag('console.command');
    }
}
