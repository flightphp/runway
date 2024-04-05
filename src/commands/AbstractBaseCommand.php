<?php

declare(strict_types=1);

namespace flight\commands;

abstract class AbstractBaseCommand extends \Ahc\Cli\Input\Command
{
    /** @var array<string,mixed> */
    protected array $config;

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
    }
}
