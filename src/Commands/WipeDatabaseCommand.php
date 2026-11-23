<?php

namespace Native\Desktop\Commands;

use Illuminate\Database\Console\WipeCommand as BaseWipeCommand;
use Native\Desktop\NativeServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @deprecated v3 renames native:db:wipe to native:db wipe. The old name still works there, prints the new form and goes in a later major version. Its --database and --drop-types options are gone. Nothing changes in v2.
 */
#[AsCommand(name: 'native:db:wipe')]
class WipeDatabaseCommand extends BaseWipeCommand
{
    // The parent declares its own signature, which wins over $name.
    // Inheriting it would register this as a second db:wipe,
    // so the name and options are spelled out here.
    protected $signature = 'native:db:wipe
                {--database= : The database connection to use}
                {--drop-views : Drop all tables and views}
                {--drop-types : Drop all tables and types (Postgres only)}
                {--force : Force the operation to run when in production}';

    protected $description = 'Wipe the database in the NativePHP development environment';

    public function handle()
    {
        (new NativeServiceProvider($this->laravel))->rewriteDatabase();

        return parent::handle();
    }
}
