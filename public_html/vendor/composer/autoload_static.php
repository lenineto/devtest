<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6d398d14dfb196351d0ca30d8ad4c024
{
    public static $prefixLengthsPsr4 = array (
        'V' => 
        array (
            'VariableAnalysis\\' => 17,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'VariableAnalysis\\' => 
        array (
            0 => __DIR__ . '/..' . '/sirbrillig/phpcs-variable-analysis/VariableAnalysis',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit6d398d14dfb196351d0ca30d8ad4c024::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit6d398d14dfb196351d0ca30d8ad4c024::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
