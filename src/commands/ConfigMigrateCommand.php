<?php

declare(strict_types=1);

namespace flight\commands;

use flight\commands\AbstractBaseCommand;
use Ahc\Cli\IO\Interactor;
use flight\util\Json;

class ConfigMigrateCommand extends AbstractBaseCommand
{
    /**
     * Construct
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('config:migrate', 'Migrate runway configuration from .runway-config.json to config.php', $config);

        // Add option for config file path
        $this->option('-c --config-file path', 'Path to the runway config file');
    }

    public function interact(Interactor $io): void
    {
        // No interaction needed before execute
    }

    public function execute()
    {
        $io = $this->app()->io();
        $configFile = $this->configFile;
        if (empty($configFile)) {
            $io->error('Please provide the path to the runway config file using the --config-file option.', true);
            return;
        }

        if (!file_exists($configFile)) {
            $io->error("The specified config file does not exist: {$configFile}", true);
            return;
        }

        $runwayConfig = Json::decode(file_get_contents($configFile), true) ?? [];

        $config['runway'] = $this->config['runway'] ?? [];

        // Merge them together, with .runway-config.json taking precedence
        $config['runway'] = array_merge($config['runway'], $runwayConfig);

        $this->setRunwayConfig($config['runway']);

        $io->boldGreen("Runway Config values migrated successfully!", true);
    }
}
