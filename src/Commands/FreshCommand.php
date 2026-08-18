<?php

namespace Native\Desktop\Commands;

use Illuminate\Database\Console\Migrations\FreshCommand as BaseFreshCommand;
use Native\Desktop\NativeServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'native:migrate:fresh')]
class FreshCommand extends BaseFreshCommand
{
    // The parent declares its own signature, which wins over $name.
    // Inheriting it would register this as a second migrate:fresh,
    // so the name and options are spelled out here.
    protected $signature = 'native:migrate:fresh
                {--database= : The database connection to use}
                {--drop-views : Drop all tables and views}
                {--drop-types : Drop all tables and types (Postgres only)}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--seed : Indicates if the seed task should be re-run}
                {--seeder= : The class name of the root seeder}
                {--step : Force the migrations to be run so they can be rolled back individually}';

    protected $description = 'Drop all tables and re-run all migrations in the NativePHP development environment';

    public function handle()
    {
        $nativeServiceProvider = new NativeServiceProvider($this->laravel);

        $nativeServiceProvider->removeDatabase();

        $nativeServiceProvider->rewriteDatabase();

        return parent::handle();
    }
}
