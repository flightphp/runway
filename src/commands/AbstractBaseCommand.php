<?php

declare(strict_types=1);

namespace flight\commands;

use flight\util\Json;

abstract class AbstractBaseCommand extends \Ahc\Cli\Input\Command
{
    /**
     * Symfony-like constants for InputOption and InputArgument compatibility.
     * These mirror the numeric values used by Symfony's Console component.
     */
    public const VALUE_NONE = 1;
    public const VALUE_REQUIRED = 2;
    public const VALUE_OPTIONAL = 4;
    public const VALUE_IS_ARRAY = 8;

    public const REQUIRED = 1;
    public const OPTIONAL = 2;
    public const IS_ARRAY = 4;

    /** @var array<string,mixed> */
    protected array $config;

    protected string $projectRoot;

    /**
     * Construct
     *
     * @param string $name        Good ol' name
     * @param string $description Good ol' description
     * @param array<string,mixed>  $config      config from .runway-config.json
     */
    public function __construct(string $name, string $description, array $config)
    {
        parent::__construct($name, $description);
        $this->config = $config;
        $this->projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : getcwd();
    }

    /**
     * Gets a value from the config
     * @param string $name
     * @return mixed
     */
    protected function getConfigValue(string $name)
    {
        return $this->app()->handle([$this->projectRoot . '/vendor/bin/runway', 'config:get', $name]);
    }

    /**
     * Sets a value in the config
     * @param string $name
     * @param mixed $value
     */
    protected function setConfigValue(string $name, $value)
    {
        if (is_array($value) || is_object($value)) {
            $value = Json::encode($value);
        }
        return $this->app()->handle([$this->projectRoot . '/vendor/bin/runway', 'config:set', $name, $value]);
    }

    /**
     * Gets the 'runway' key from the config
     *
     * @return void
     */
    protected function getRunwayConfig()
    {
        return $this->getConfigValue('runway');
    }

    /**
     * Gets a single value from the 'runway' config
     *
     * @param string $key the config key to get (dot notation)
     * @return mixed
     */
    protected function getRunwayConfigValue(string $key)
    {
        return $this->getConfigValue('runway.' . $key);
    }

    /**
     * Sets the 'runway' key in the config
     *
     * @param array $newConfig the whole config array to set
     * @return void
     */
    protected function setRunwayConfig(array $newConfig): void
    {
        $this->setConfigValue('runway', $newConfig);
    }

    /**
     * Sets a single value inside the 'runway' config
     *
     * @param string $key   the config key to set (dot notation)
     * @param mixed  $value the value to set
     * @return void
     */
    protected function setRunwayConfigValue(string $key, $value): void
    {
        $this->setConfigValue('runway.' . $key, $value);
    }

    /**
     * Symfony-style addOption shim.
     * Maps to Ahc\Cli's option($raw, $desc, $filter = null, $default = null).
     *
     * @param string             $name     Long option name (no leading dashes)
     * @param string|string[]|null $shortcut Short option char or array of chars (without dashes)
     * @param int|null           $mode     One of VALUE_* constants
     * @param string             $description
     * @param mixed|null         $default
     *
     * @return $this
     */
    public function addOption(string $name, $shortcut = null, ?int $mode = null, string $description = '', $default = null): self
    {
        // Build raw option format Ahc\Cli expects, e.g. "-s, --name" or "--name"
        $raw = '';

        if ($shortcut) {
            if (\is_array($shortcut)) {
                // take the first shortcut for the display (Ahc supports single short)
                $first = (string) ($shortcut[0] ?? '');
            } else {
                $first = (string) $shortcut;
            }

            $first = ltrim($first, '-');

            $raw = $first !== '' ? "-{$first}, --{$name}" : "--{$name}";
        } else {
            $raw = "--{$name}";
        }

        // Determine default based on mode
        $effectiveDefault = $default;

        if ($mode === null) {
            $mode = self::VALUE_OPTIONAL;
        }

        if ($mode === self::VALUE_NONE) {
            // flag without value. Use boolean false as a default to indicate absence.
            $effectiveDefault = $default ?? false;
        }

        if (($mode & self::VALUE_IS_ARRAY) === self::VALUE_IS_ARRAY) {
            $effectiveDefault = $default ?? [];
        }

        $this->option($raw, $description, null, $effectiveDefault);

        return $this;
    }

    /**
     * Symfony-style addArgument shim.
     * Maps to Ahc\Cli's argument($raw, $desc = '', $default = null)
     *
     * @param string   $name
     * @param int|null $mode One of REQUIRED/OPTIONAL/IS_ARRAY
     * @param string   $description
     * @param mixed    $default
     *
     * @return $this
     */
    public function addArgument(string $name, ?int $mode = null, string $description = '', $default = null): self
    {
        if ($mode === null) {
            $mode = self::OPTIONAL;
        }

        $raw = '';

        $isArray = ($mode & self::IS_ARRAY) === self::IS_ARRAY;
        $isRequired = ($mode & self::REQUIRED) === self::REQUIRED;

        if ($isRequired) {
            $raw = $isArray ? "<{$name}...>" : "<{$name}>";
        } else {
            $raw = $isArray ? "[{$name}...]" : "[{$name}]";
        }

        $this->argument($raw, $description, $default);

        return $this;
    }

    /**
     * Symfony-style setters/getters for name/description/help and application.
     */
    public function setName(string $name): self
    {
        $this->_name = $name;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->_desc = $description;

        return $this;
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function getDescription(): string
    {
        return $this->_desc;
    }

    public function setHelp(string $help): self
    {
        $this->_usage = $help;

        return $this;
    }

    public function getHelp(): ?string
    {
        return $this->_usage ?? null;
    }

    /**
     * Symfony-style getApplication shim. Returns the underlying Ahc App instance.
     *
     * @return null|\Ahc\Cli\Application
     */
    public function getApplication()
    {
        return $this->app();
    }
}
