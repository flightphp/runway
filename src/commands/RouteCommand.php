<?php

declare(strict_types=1);

namespace flight\commands;

use Flight;
use flight\net\Route;

/**
 * @property-read ?bool $get
 * @property-read ?bool $post
 * @property-read ?bool $delete
 * @property-read ?bool $put
 * @property-read ?bool $patch
 */
class RouteCommand extends AbstractBaseCommand
{
    /**
     * Construct
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('routes', 'Gets all routes for an application', $config);

        $this->option('--get', 'Only return GET requests');
        $this->option('--post', 'Only return POST requests');
        $this->option('--delete', 'Only return DELETE requests');
        $this->option('--put', 'Only return PUT requests');
        $this->option('--patch', 'Only return PATCH requests');
    }

    /**
     * Executes the function
     *
     * @return void
     */
    public function execute()
    {

        $io = $this->app()->io();
        $io->bold('Routes', true);

        $cwd = getcwd();

        $index_root = $cwd . '/' . $this->config['index_root'];
        include($index_root);
        $routes = Flight::router()->getRoutes();
        $arrayOfRoutes = [];
        foreach ($routes as $route) {
            if ($this->shouldAddRoute($route) === true) {
                $arrayOfRoutes[] = [
                    'Pattern' => $route->pattern,
                    'Methods' => implode(', ', $route->methods),
                    'Alias' => $route->alias ?? '',
                    'Streamed' => $route->is_streamed ? 'Yes' : 'No',
                    'Has_Middleware' => $route->middleware ? 'Yes' : 'No'
                ];
            }
        }
        $io->table($arrayOfRoutes, [
            'head' => 'boldGreen'
        ]);
    }

    /**
     * Whether or not to add the route based on the request
     *
     * @param Route $route Flight Route object
     *
     * @return boolean
     */
    public function shouldAddRoute(Route $route)
    {
        $boolval = false;

        $showAll = !$this->get && !$this->post && !$this->put && !$this->delete && !$this->patch;
        if ($showAll === true) {
            $boolval = true;
        } else {
            $methods = [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' ];
            foreach ($methods as $method) {
                $lowercaseMethod = strtolower($method);
                if (
                    $this->{$lowercaseMethod} === true &&
                    (
                        $route->methods[0] === '*' ||
                        in_array($method, $route->methods, true) === true
                    )
                ) {
                    $boolval = true;
                    break;
                }
            }
        }
        return $boolval;
    }
}
