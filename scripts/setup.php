<?php

declare(strict_types=1);

$interactor = new Ahc\Cli\IO\Interactor();

$interactor->boldBlue('Welcome to the Runway setup wizard!', true);
$interactor->blue('This wizard will help you get your settings correct for your Flight project.', true);
$possible_file_locations = [
    '1' => 'public/index.php',
    '2' => 'index.php',
    '3' => 'web/index.php',
    '4' => 'www/index.php',
    '5' => 'other'
];
$choice = $interactor->choice('Where is your root index.php file located for your project?', $possible_file_locations, '1');
if ($choice === '5') {
    $index_location = $interactor->prompt('Please enter the path to your root index.php file: ');
} else {
    $index_location = $possible_file_locations[$choice];
}

$json = json_encode([
    'index_root' => $index_location
], JSON_PRETTY_PRINT);

$interactor->boldGreen('Your settings have been saved!', true);
file_put_contents(getcwd() . '/.runway-config.json', $json);
