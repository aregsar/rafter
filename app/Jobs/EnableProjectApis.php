<?php

namespace App\Jobs;

use App\GoogleProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class EnableProjectApis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $googleProject;

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
            $operation = $this->googleProject->client()->enableApis(GoogleProject::REQUIRED_APIS);

            $this->googleProject->update(['operation_name' => $operation['name']]);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }

    public function failed(Throwable $exception)
    {
        $this->googleProject->setFailed();
    }
}
