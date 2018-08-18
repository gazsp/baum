<?php

namespace Baum\Console;

use Illuminate\Console\Command;
use Baum\Generators\ModelGenerator;
use Baum\Generators\MigrationGenerator;
use Symfony\Component\Console\Input\InputArgument;

class InstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'baum:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffolds a new migration and model suitable for Baum.';

    /**
     * Migration generator instance.
     *
     * @var \Baum\Generators\MigrationGenerator
     */
    protected $migrator;

    /**
     * Model generator instance.
     *
     * @var \Baum\Generators\ModelGenerator
     */
    protected $modeler;

    /**
     * Create a new command instance.
     *
     * @param \Baum\Generators\MigrationGenerator $migrator
     * @param \Baum\Generators\ModelGenerator $modeler
     *
     * @return void
     */
    public function __construct(MigrationGenerator $migrator, ModelGenerator $modeler)
    {
        parent::__construct();

        $this->migrator = $migrator;
        $this->modeler = $modeler;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->fire();
    }

    /**
     * Execute the console command.
     *
     * Basically, we'll write the migration and model stubs out to disk inflected
     * with the name provided. Once its done, we'll `dump-autoload` for the entire
     * framework to make sure that the new classes are registered by the class
     * loaders.
     *
     * @return void
     */
    public function fire()
    {
        $name = $this->input->getArgument('name');
        $this->writeMigration($name);
        $this->writeModel($name);
    }

    /**
     * Get the command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'Name to use for the scaffolding of the migration and model.'],
        ];
    }

    /**
     * Write the migration file to disk.
     *
     * @param string $name
     *
     * @return void
     */
    protected function writeMigration($name)
    {
        $output = pathinfo($this->migrator->create($name, $this->getMigrationsPath()), PATHINFO_FILENAME);
        $this->line("      <fg=green;options=bold>create</fg=green;options=bold>  $output");
    }

    /**
     * Write the model file to disk.
     *
     * @param string $name
     *
     * @return void
     */
    protected function writeModel($name)
    {
        $output = pathinfo($this->modeler->create($name, $this->getModelsPath()), PATHINFO_FILENAME);
        $this->line("      <fg=green;options=bold>create</fg=green;options=bold>  $output");
    }

    /**
     * Get the path to the migrations directory.
     *
     * @return string
     */
    protected function getMigrationsPath()
    {
        return $this->laravel->databasePath();
    }

    /**
     * Get the path to the models directory.
     *
     * @return string
     */
    protected function getModelsPath()
    {
        return $this->laravel->basePath();
    }
}
