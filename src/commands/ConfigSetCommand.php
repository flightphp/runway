<?php

namespace flight\commands;

class ConfigSetCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('config:set', 'Set a config value in config.php (uses modern [] syntax)', $config);

        $this
            ->argument('<key>', 'Config key (dot notation: database.host)')
            ->argument('<value>', 'JSON-encoded value')
            ->usage(
                '<bold>  runway config:set database.host \'{"host":"127.0.0.1"}\'</end>' . PHP_EOL .
                '<bold>  runway config:set cache.enabled true</end>' . PHP_EOL
            );
    }

    public function execute($key, $value = '')
    {
        $io = $this->app()->io();

        $appConfigPath = RUNWAY_PROJECT_ROOT . '/app/config/config.php';
        $dotRunwayPath = RUNWAY_PROJECT_ROOT . '/.runway-config.json';

        if (is_file($appConfigPath) === true) {
            // Preferred target: app config PHP file
            $configError = $this->setConfig($appConfigPath, $key, $value);
        } else {
            // Fallback: write to .runway-config.json (create if missing)
            $configError = $this->setConfigJson($dotRunwayPath, $key, $value);
        }

        if ($configError === 1) {
            $io->error("Failed to update config: $key", true);
            return 1;
        }

        $io->ok("Config updated: $key", true);
        return 0;
    }

	/**
	 * Sets a config up
	 *
	 * @param string $configPath the path to the config.php file
	 * @param string $key        the config key
	 * @param mixed $value       whatever value
	 * @return integer
	 */
	protected function setConfig(string $configPath, string $key, $value = ''): int
	{
		$io = $this->app()->io();

        if (is_file($configPath) === false) {
            $io->error("Config file not found: $configPath", true);
            return 1;
        }

        // Load current config
        $config = (static function () use ($configPath) {
            return include $configPath;
        })();

        if (is_array($config) === false) {
            $io->error("config.php must return an array", true);
            return 1;
        }

        // Parse JSON value
        $decoded = json_decode($value, true);
        if ((isset($decoded[0]) === true && ($decoded[0] === '{' || $decoded[0] === '[')) && json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON: " . json_last_error_msg(), true);
            return 1;
        } elseif (ctype_print($value) === true && json_last_error() !== JSON_ERROR_NONE) {
			// Fallback: treat as string
			$decoded = $value;
		}

        // Deep merge into config
        $parts = explode('.', $key);
        $ref = &$config;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $decoded;

        // Backup original
        if (!copy($configPath, $configPath . '.bak')) {
            $io->error("Failed to create backup", true);
            return 1;
        }

        // Read source
        $source = file_get_contents($configPath);
        if ($source === false) {
            $io->error("Failed to read config file", true);
            return 1;
        }

        // Find return [...] block
        if (!preg_match('#\breturn\s*\[[\s\S]*?\];#i', $source, $m)) {
            $io->error("Could not find return [...] block", true);
            return 1;
        }
        $oldBlock = $m[0];

        // Detect indentation
        preg_match('/^([ \t]*)\[/m', $oldBlock, $indentMatch);
        $indent = $indentMatch[1] ?? '    ';

        // Generate new block with short syntax
        $lines = ["return ["];
        foreach ($config as $k => $v) {
            // compute base indent (number of spaces) for values so nested
            // array lines align under the value correctly
            $baseIndent = strlen($indent) + 4;
            $exported = $this->var_export_short($v, $baseIndent);
            $lines[] = $indent . "    " . var_export($k, true) . " => $exported,";
        }
        $lines[] = $indent . "];";
        $newBlock = implode("\n", $lines);

        // Replace and save
        $newSource = str_replace($oldBlock, $newBlock, $source);
        if (file_put_contents($configPath, $newSource) === false) {
            $io->error("Failed to write config file", true);
            return 1;
        }

		return 0;
	}

    /**
     * Set a config in a JSON file (used when app/config/config.php is not present).
     * Creates a backup and writes pretty JSON.
     */
    public function setConfigJson(string $jsonPath, string $key, $value = ''): int
    {
        $io = $this->app()->io();

        $data = [];
        if (is_file($jsonPath) === true) {
            $contents = file_get_contents($jsonPath);
            if ($contents === false) {
                $io->error("Failed to read JSON config: $jsonPath", true);
                return 1;
            }
            $data = json_decode($contents, true);
            if (!is_array($data)) {
                $data = [];
            }
        }

        // Parse incoming value
        $decoded = json_decode($value, true);
        if ((isset($value[0]) === true && ($value[0] === '{' || $value[0] === '[')) && json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON: " . json_last_error_msg(), true);
            return 1;
        } elseif (ctype_print($value) === true && json_last_error() !== JSON_ERROR_NONE) {
            $decoded = $value;
        }

        // Deep merge into data
        $parts = explode('.', $key);
        $ref = &$data;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $decoded;

        // Backup original if present
        if (is_file($jsonPath) === true) {
            if (!copy($jsonPath, $jsonPath . '.bak')) {
                $io->error("Failed to create backup", true);
                return 1;
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $io->error("Failed to encode JSON: " . json_last_error_msg(), true);
            return 1;
        }

        if (file_put_contents($jsonPath, $json) === false) {
            $io->error("Failed to write JSON config", true);
            return 1;
        }

        return 0;
    }

    /**
     * var_export() with short array syntax []
     */
    private function var_export_short(mixed $value, int $indent = 0): string
    {
        $pad = str_repeat(' ', $indent);
        $step = 4;

        if (is_array($value)) {
            // Multiline empty array with a blank inner line
            if (empty($value)) {
                $innerPad = str_repeat(' ', $indent + $step);
                return "[\n$innerPad\n$pad]";
            }

            $items = [];
            $isAssoc = !array_is_list($value);
            $innerPad = str_repeat(' ', $indent + $step);

            foreach ($value as $k => $v) {
                $exported = $this->var_export_short($v, $indent + $step);
                if ($isAssoc) {
                    $items[] = $innerPad . var_export($k, true) . " => " . $exported . ",";
                } else {
                    $items[] = $innerPad . $exported . ",";
                }
            }

            $content = implode("\n", $items);
            return "[\n$content\n$pad]";
        }

        return var_export($value, true);
    }
}