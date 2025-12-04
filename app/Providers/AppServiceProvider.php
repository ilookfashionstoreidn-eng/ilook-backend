<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\StringType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Doctrine\DBAL\Types\Types;
use Illuminate\Support\Facades\DB;



class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        Schema::defaultStringLength(191);
    
        try {
            if (DB::connection() instanceof \Illuminate\Database\MySqlConnection) {
                DB::connection()->getDoctrineConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
            }
        } catch (\Throwable $e) {
        }
    }
    
    
}
