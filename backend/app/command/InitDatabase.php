<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class InitDatabase extends Command
{
    protected function configure()
    {
        $this->setName('db:init')
            ->setDescription('Initialize SQLite database and create tables');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('Initializing database...');

        try {
            $sqlFile = root_path() . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'Geetest_Table.sql';

            if (!file_exists($sqlFile)) {
                $output->writeln('<error>Migration file not found: ' . $sqlFile . '</error>');
                return;
            }

            $sql = file_get_contents($sqlFile);

            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    Db::execute($statement);
                }
            }

            $output->writeln('<info>Database initialized successfully!</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
        }
    }
}
