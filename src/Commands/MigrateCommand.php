<?php

namespace Native\Desktop\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Illuminate\Database\Migrations\Migrator;
use Native\Desktop\NativeServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @deprecated v3 renames native:migrate to native:db migrate. The old name still works there, prints the new form and goes in a later major version. Its --database, --path, --realpath, --schema-path, --graceful and --isolated options are gone. Nothing changes in v2.
 */
#[AsCommand(name: 'native:migrate')]
class MigrateCommand extends BaseMigrateCommand
{
    protected $description = 'Run the database migrations in the NativePHP development environment';

    public function __construct(Migrator $migrator, Dispatcher $dispatcher)
    {
        $this->signature = 'native:'.$this->signature;

        parent::__construct($migrator, $dispatcher);
    }

    public function handle()
    {
        (new NativeServiceProvider($this->laravel))->rewriteDatabase();

        return parent::handle();
    }
}
