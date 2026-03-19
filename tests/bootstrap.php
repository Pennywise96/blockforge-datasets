<?php

$autoloadPaths = [
    dirname(__DIR__).'/vendor/autoload.php',
    dirname(__DIR__, 2).'/blockforge-dev/vendor/autoload.php',
    dirname(__DIR__, 2).'/blockforge-cms/vendor/autoload.php',
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require $autoloadPath;

        return;
    }
}

fwrite(STDERR, "Unable to locate a Composer autoloader for blockforge-datasets tests.\n");

exit(1);
