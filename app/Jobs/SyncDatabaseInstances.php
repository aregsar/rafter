<?php

namespace App\Jobs;

use App\Database;
use App\DatabaseInstance;
use App\GoogleProject;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDatabaseInstances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $googleProject;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(GoogleProject $googleProject)
    {
        $this->googleProject = $googleProject;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $instances = $this->googleProject->client()->getDatabaseInstances();

            foreach ($instances['items'] as $instance) {
                $db = $this->googleProject->databaseInstances()->create([
                    'name' => $instance['name'],
                    'version' => $instance['databaseVersion'],
                    'type' => 'mysql', // TODO: Support Postgres
                    'tier' => $instance['settings']['tier'],
                    'size' => $instance['settings']['dataDiskSizeGb'],
                    'status' => DatabaseInstance::STATUS_ACTIVE,
                    'region' => $instance['region'],
                    'synced' => true,
                ]);

                $databases = $this->googleProject->client()->getDatabases($db);

                foreach ($databases['items'] as $database) {
                    $db->databases()->create([
                        'name' => $database['name'],
                        'status' => Database::STATUS_ACTIVE,
                    ]);
                }
            }
        } catch (Exception $e) {
            $this->fail($e);
        }
    }

    public function failed(Exception $e)
    {
        Log::error($e->getMessage());
        $this->googleProject->setFailed();
    }
}
