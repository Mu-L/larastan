<?php

declare(strict_types=1);

namespace NunoMaduro\Larastan;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use NunoMaduro\Larastan\Internal\ComposerHelper;
use Orchestra\Testbench\Foundation\Application as Testbench;
use Orchestra\Testbench\Foundation\Config;

/**
 * @internal
 */
final class ApplicationResolver
{
    /**
     * Create symlink on vendor path.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public static function createSymlinkToVendorPath($app, string $vendorDir): void
    {
        $filesystem = new Filesystem();

        $laravelVendorPath = $app->basePath('vendor');

        if (
            "$laravelVendorPath/autoload.php" !== "$vendorDir/autoload.php"
        ) {
            $filesystem->delete($laravelVendorPath);
            $filesystem->link($vendorDir, $laravelVendorPath);
        }

        $app->flush();
    }

    /**
     * Creates an application and registers service providers found.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     *
     * @throws \ReflectionException
     */
    public static function resolve(): Application
    {
        /** @var string $workingPath */
        $workingPath = getcwd();
        if (! defined('TESTBENCH_WORKING_PATH')) {
            define('TESTBENCH_WORKING_PATH', $workingPath);
        }

        if ($composerConfig = ComposerHelper::getComposerConfig($workingPath)) {
            $vendorDir = ComposerHelper::getVendorDirFromComposerConfig($workingPath, $composerConfig);
        } else {
            $vendorDir = $workingPath . '/vendor';
        }

        $resolvingCallback = function ($app) {
            $packageManifest = $app->make(PackageManifest::class);

            $packageManifest->build();
        };

        if (class_exists(Config::class)) {
            $config = Config::loadFromYaml($workingPath);

            static::createSymlinkToVendorPath(Testbench::create($config['laravel']), $vendorDir);

            return Testbench::create(
                $config['laravel'],
                $resolvingCallback,
                ['enables_package_discoveries' => true, 'extra' => $config->getExtraAttributes()]
            );
        }

        static::createSymlinkToVendorPath(Testbench::create(Testbench::applicationBasePath()), $vendorDir);

        return Testbench::create(
            null,
            $resolvingCallback,
            ['enables_package_discoveries' => true]
        );
    }
}
