<?php

namespace flight\commands;

use flight\util\Json;

/**
 * 
 * @property-read bool $backup
 */
class ConfigSetCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('config:set', 'Set a config value in config.php (uses modern [] syntax)', $config);

        $this
            ->argument('<key>', 'Config key (dot notation: database.host)')
            ->argument('<value>', 'JSON-encoded value')
            ->option('-b --backup', 'Backup the config file before updating')
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

        $valueLowered = strtolower($value);
        if (in_array($valueLowered, ['true', 'false', 'null'])) {
            $value = $valueLowered;
        }

        // Load current config
        $config = (static function () use ($configPath) {
            return include $configPath;
        })();

        if (is_array($config) === false) {
            $io->error("config.php must return an array", true);
            return 1;
        }

        // Parse JSON value, but silently let it fail
        $decoded = json_decode($value, true);
        if ((isset($value[0]) === true && ($value[0] === '{' || $value[0] === '[')) && json_last_error() !== JSON_ERROR_NONE) {
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
        if ($this->backup && !copy($configPath, $configPath . '.bak')) {
            $io->error("Failed to create backup", true);
            return 1;
        }

        // Read source
        $source = file_get_contents($configPath);
        if ($source === false) {
            $io->error("Failed to read config file", true);
            return 1;
        }

        // Find return [...] block using tokenization (robust against ]; in strings/comments)
        $oldBlock = $this->findReturnBlock($source);
        if ($oldBlock === null) {
            $io->error("Could not find return [...] block", true);
            return 1;
        }

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
            $data = Json::decode($contents, true);
            if (!is_array($data)) {
                $data = [];
            }
        }

        $valueLowered = strtolower($value);
        if (in_array($valueLowered, ['true', 'false', 'null'])) {
            $value = $valueLowered;
        }

        // Parse incoming value, but silently let it fail
        $decoded = json_decode($value, true);
        if ((isset($value[0]) === true && ($value[0] === '{' || $value[0] === '[')) && json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON: " . json_last_error_msg(), true);
            return 1;
        } elseif (ctype_print($value) === true && json_last_error() !== JSON_ERROR_NONE) {
            $decoded = $value;
        }

        // We don't actually save the runway key in the JSON file
        $key = str_replace(['runway.', 'runway'], ['', ''], $key);

        // Deep merge into data
        $parts = explode('.', $key);
        $ref = &$data;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        if (is_array($ref) && is_array($decoded)) {
            $ref = array_merge($ref, $decoded);
        } else {
            $ref = $decoded;
        }

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
     *
     * @param mixed $value
     * @param int $indent
     * @return string
     */
    private function var_export_short($value, int $indent = 0): string
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
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
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

    /**
     * Finds the return [...] block in a PHP source string using tokenization.
     *
     * @param string $source The PHP source code
     * @return string|null The block content or null if not found
     */
    protected function findReturnBlock(string $source): ?string
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $startIdx = -1;
        $endIdx = -1;
        $depth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($startIdx === -1) {
                if (is_array($token) && $token[0] === T_RETURN) {
                    $j = $i + 1;
                    while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        $j++;
                    }
                    if ($j < $count && $tokens[$j] === '[') {
                        $startIdx = $i;
                        $i = $j;
                        $depth = 1;
                    }
                }
                continue;
            }

            if ($token === '[') {
                $depth++;
            } elseif ($token === ']') {
                $depth--;
                if ($depth === 0) {
                    $j = $i + 1;
                    while ($j < $count && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_COMMENT || $tokens[$j][0] === T_DOC_COMMENT)) {
                        $j++;
                    }
                    if ($j < $count && $tokens[$j] === ';') {
                        $endIdx = $j;
                        break;
                    }
                }
            }
        }

        if ($startIdx !== -1 && $endIdx !== -1) {
            $block = '';
            for ($i = $startIdx; $i <= $endIdx; $i++) {
                $token = $tokens[$i];
                $block .= is_array($token) ? $token[1] : $token;
            }
            return $block;
        }

        return null;
    }
}
