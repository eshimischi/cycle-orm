<?php

declare(strict_types=1);

use Cycle\ORM\Tests\Util\DontGenerateAttribute;
use Spiral\Tokenizer;

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');

//Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

$tokenizer = new Tokenizer\Tokenizer(new Tokenizer\Config\TokenizerConfig([
    'directories' => [__DIR__ . '/ORM/Functional/Driver/Common'],
    'exclude' => [],
]));

$databases = [
    'sqlite' => [
        'namespace' => 'Cycle\ORM\Tests\Functional\Driver\SQLite',
        'directory' => __DIR__ . '/ORM/Functional/Driver/SQLite/',
    ],
    'mysql' => [
        'namespace' => 'Cycle\ORM\Tests\Functional\Driver\MySQL',
        'directory' => __DIR__ . '/ORM/Functional/Driver/MySQL/',
    ],
    'postgres' => [
        'namespace' => 'Cycle\ORM\Tests\Functional\Driver\Postgres',
        'directory' => __DIR__ . '/ORM/Functional/Driver/Postgres/',
    ],
    'sqlserver' => [
        'namespace' => 'Cycle\ORM\Tests\Functional\Driver\SQLServer',
        'directory' => __DIR__ . '/ORM/Functional/Driver/SQLServer/',
    ],
];

echo "Generating test classes for all database types...\n";

$classes = $tokenizer
    ->classLocator()
    ->getClasses(\Cycle\ORM\Tests\Functional\Driver\Common\BaseTest::class);

foreach ($classes as $class) {
    foreach ($class->getMethods() as $method) {
        if ($method->isAbstract()) {
            echo "Skip class {$class->getName()} with abstract methods.\n";
            continue 2;
        }
    }

    if (
        !$class->isAbstract()
        // Has abstract methods
        || $class->getName() == \Cycle\ORM\Tests\Functional\Driver\Common\BaseTest::class
    ) {
        continue;
    }

    if ($class->getAttributes(DontGenerateAttribute::class) !== []) {
        continue;
    }


    $path = str_replace(
        [\str_replace('\\', '/', __DIR__), 'ORM/Functional/Driver/Common/'],
        '',
        \str_replace('\\', '/', $class->getFileName()),
    );

    $path = ltrim($path, '/');

    foreach ($databases as $driver => $details) {
        $filename = $details['directory'] . $path;
        if (\file_exists($filename)) {
            continue;
        }
        echo "Processing $filename\n";

        $dir = pathinfo($filename, PATHINFO_DIRNAME);

        $namespace = str_replace(
            'Cycle\\ORM\\Tests\\Functional\\Driver\\Common',
            $details['namespace'],
            $class->getNamespaceName(),
        );

        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        file_put_contents(
            $filename,
            sprintf(
                <<<PHP
<?php

declare(strict_types=1);

namespace %s;

// phpcs:ignore
use %s as CommonClass;

/**
 * @group driver
 * @group driver-%s
 */
class %s extends CommonClass
{
    public const DRIVER = '%s';
}

PHP,
                $namespace,
                $class->getName(),
                $driver,
                $class->getShortName(),
                $driver,
            ),
        );
    }
}
