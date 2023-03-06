<?php

namespace Srdante\LaravelSinglestoreBackup\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SinglestoreBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'singlestore:backup {--init} (--differential} {--timeout=} {--multipart_chunk_size_mb=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('init') && $this->option('differential')) {
            throw new InvalidArgumentException('You can\'t use --init and --differential options at the same time.');
        }

        /*
         * Start backup command
         */
        $this->warn('Starting backup... This might take a while.');

        [$database, $bucket, $config, $credentials] = $this->getParameters();

        $with = '';
        if ($this->option('init')) {
            $with = 'WITH INIT';
        }
        if ($this->option('differential')) {
            $with = 'WITH DIFFERENTIAL';
        }

        $timeout = '';
        if ($this->option('timeout')) {
            $timeout = "TIMEOUT {$this->option('timeout')}";
        }

        /**
         * Do backup query
         */
        try {
            $result = DB::statement("BACKUP DATABASE ? {$with} TO S3 ? {$timeout} CONFIG ? CREDENTIALS ? ;", [$database, $bucket, $config, $credentials]);
        } catch (\Exception) {
            $this->error('Backup failed. Please check your database credentials.');

            return Command::FAILURE;
        }

        $this->info('Backup created successfully.');

        return Command::SUCCESS;
    }

    /**
     * Get query binding parameters.
     *
     * @return array
     */
    public function getParameters()
    {
        return [
            config('database.connections.singlestore.database'),

            config('singlestore-backup.bucket'),

            json_encode([
                'endpoint_url' => config('singlestore-backup.endpoint'),
                ($this->option('multipart_chunk_size_mb'))
                    ? ['multipart_chunk_size_mb' => $this->option('multipart_chunk_size_mb')]
                    : [],
            ]),

            json_encode([
                'aws_access_key_id' => config('singlestore-backup.access_key'),
                'aws_secret_access_key' => config('singlestore-backup.secret_key'),
            ]),
        ];
    }
}
