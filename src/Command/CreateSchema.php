<?php

namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateSchema extends Command
{
    private $container;

    public function __construct($container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this
        // the name of the command (the part after "bin/console")
        ->setName('app:create:schema')

        // the short description shown while running "php bin/console list"
        ->setDescription('Create the schema if none exists.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp("This command allows you to create the schema if it does not already exists.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sm = $this->container['db']->getSchemaManager();

        if (count($sm->listTables())) {
            throw new \Exception('Tables already exists');
        }

        $schema = new \Doctrine\DBAL\Schema\Schema();
        $farms = $schema->createTable('farms');
        $farms->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $farms->addColumn('wwoof_id', 'integer');
        $farms->addColumn('title', 'string', ['length' => 32]);
        $farms->addColumn('accepting', 'integer');
        $farms->addColumn('description', 'text');
        $farms->setPrimaryKey(['id']);

        $pictures = $schema->createTable('pictures');
        $pictures->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $pictures->addColumn('farm_id', 'integer', ['unsigned' => true]);
        $pictures->addColumn('url', 'string', ['length' => 255]);
        $pictures->addForeignKeyConstraint($farms, ['farm_id'], ['id'], ['onDelete' => 'CASCADE']);
        $pictures->setPrimaryKey(['id']);

        $farms_meta = $schema->createTable('farms_meta');
        $farms_meta->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
        $farms_meta->addColumn('wwoof_id', 'integer', ['unsigned' => true]);
        $farms_meta->addColumn('refused', 'integer');
        $farms_meta->addForeignKeyConstraint($farms, ['wwoof_id'], ['wwoof_id']);
        $farms_meta->setPrimaryKey(['id']);

        foreach ($schema->toSql($sm->getDatabasePlatform()) as $query) {
            $this->container['db']->executeQuery($query);
        }
    }
}
