<?php

namespace flight\commands;

class ConfigGetCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('config:get', 'Get a config value from config.php or .runway-config.json', $config);

        $this
            ->argument('[key]', 'Config key (dot notation: database.host)')
            ->usage(
                '<bold>  runway config:get database.host</end>' . PHP_EOL .
                '<bold>  runway config:get</end>' . PHP_EOL
            );
    }

    /**
     * Execute the command.
     *
     * @param string|null $key
     * @return int
     */
    public function execute($key = null)
    {

        $appConfigPath = RUNWAY_PROJECT_ROOT . '/app/config/config.php';
        $dotRunwayPath = RUNWAY_PROJECT_ROOT . '/.runway-config.json';

        if (is_file($appConfigPath) === true) {
            $result = $this->getConfig($appConfigPath, $key);
        } else {
            $result = $this->getConfigJson($dotRunwayPath, $key);
        }

        if ($result !== 0) {
            return 1;
        }

        return 0;
    }

    /**
     * Read a PHP config file (must return array) and print the requested key or whole array.
     *
     * @param string $configPath
     * @param string|null $key
     * @return int
     */
    protected function getConfig(string $configPath, ?string $key = null): int
    {
        $io = $this->app()->io();

        if (is_file($configPath) === false) {
            $io->error("Config file not found: $configPath", true);
            return 1;
        }

        $config = (static function () use ($configPath) {
            return include $configPath;
        })();

        if (is_array($config) === false) {
            $io->error("config.php must return an array", true);
            return 1;
        }

        if ($key === null || $key === '') {
            return $this->outputValue($config);
        }

        $parts = explode('.', $key);
        $ref = $config;
        foreach ($parts as $part) {
            if (is_array($ref) && array_key_exists($part, $ref)) {
                $ref = $ref[$part];
            } else {
                $io->error("Config key not found: $key", true);
                return 1;
            }
        }

        return $this->outputValue($ref);
    }

    /**
     * Read JSON config file and print requested key or whole file.
     *
     * @param string $jsonPath
     * @param string|null $key
     * @return int
     */
    protected function getConfigJson(string $jsonPath, ?string $key = null): int
    {
        $io = $this->app()->io();

        if (is_file($jsonPath) === false) {
            $io->error("Config file not found: $jsonPath", true);
            return 1;
        }

        $contents = file_get_contents($jsonPath);
        if ($contents === false) {
            $io->error("Failed to read JSON config: $jsonPath", true);
            return 1;
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            $io->error("Invalid JSON config: $jsonPath", true);
            return 1;
        }

        if ($key === null || $key === '') {
            return $this->outputValue($data);
        }

        $parts = explode('.', $key);
        $ref = $data;
        foreach ($parts as $part) {
            if (is_array($ref) && array_key_exists($part, $ref)) {
                $ref = $ref[$part];
            } else {
                $io->error("Config key not found: $key", true);
                return 1;
            }
        }

        return $this->outputValue($ref);
    }

    /**
     * Output a value to stdout. Prefer pretty JSON where possible, fallback to var_export.
     *
     * @param mixed $value
     * @return int
     */
    protected function outputValue($value): int
    {
        $io = $this->app()->io();

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $io->raw(var_export($value, true) . PHP_EOL);
                return 0;
            }

            $io->raw($json . PHP_EOL);
            return 0;
        }

        // Scalars: print as-is
        if (is_bool($value)) {
            $io->raw($value ? 'true' : 'false');
            $io->raw(PHP_EOL);
            return 0;
        }

        if ($value === null) {
            $io->raw('null' . PHP_EOL);
            return 0;
        }

        $io->raw((string) $value . PHP_EOL);

        return 0;
    }
}
