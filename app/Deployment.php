<?php

namespace App;

use App\GoogleCloud\CloudBuildConfig;
use App\GoogleCloud\CloudRunConfig;
use App\Services\GoogleApi;
use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUCCESSFUL = 'successful';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'operation_name',
        'status',
        'image',
        'commit_hash',
        'commit_message',
        'initiator_id',
    ];

    public function environment()
    {
        return $this->belongsTo('App\Environment');
    }

    public function steps()
    {
        return $this->hasMany('App\DeploymentStep');
    }

    public function project()
    {
        return $this->environment->project;
    }

    public function sourceProvider()
    {
        return $this->project()->sourceProvider;
    }

    public function initiator()
    {
        return $this->belongsTo('App\User', 'initiator_id')->withDefault([
            'name' => 'Anonymous User',
        ]);
    }

    /**
     * The git repo for this deployment
     *
     * @return string
     */
    public function repository(): string
    {
        return $this->project()->repository;
    }

    /**
     * Get the tarball URL for the deployment.
     *
     * @return string
     */
    public function tarballUrl()
    {
        return $this->sourceProvider()->client()->tarballUrl($this);
    }

    /**
     * Get the Git clone URL with a token.
     *
     * @return string
     */
    public function cloneUrl()
    {
        return $this->sourceProvider()->client()->cloneUrl($this);
    }

    /**
     * Mark a deployment as in progress
     */
    public function markAsInProgress()
    {
        $this->update(['status' => static::STATUS_IN_PROGRESS]);
    }

    /**
     * Mark a deployment as failed
     */
    public function markAsFailed()
    {
        $this->update(['status' => static::STATUS_FAILED]);
    }

    /**
     * Mark a deployment as successful
     */
    public function markAsSuccessful()
    {
        $this->update(['status' => static::STATUS_SUCCESSFUL]);
    }

    /**
     * Create a CloudBuild using a configuration.
     */
    public function submitBuild(CloudBuildConfig $cloudBuild)
    {
        return $this->client()->createImageForBuild($cloudBuild);
    }

    /**
     * Get the Build operation from CloudBuild
     */
    public function getBuildOperation()
    {
        return $this->client()->getCloudBuildOperation($this->operation_name);
    }

    /**
     * Get Cloud Run service
     */
    public function getCloudRunService()
    {
        return $this->client()->getCloudRunService($this->environment->slug(), $this->environment->project->region);
    }

    /**
     * Record the built image from CloudBuild.
     */
    public function recordBuiltImage($buildId)
    {
        $build = $this->client()->getBuild($buildId);

        $image = sprintf(
            "%s@%s",
            $build['results']['images'][0]['name'],
            $build['results']['images'][0]['digest']
        );

        $this->update(['image' => $image]);
    }

    /**
     * Create a cloud run service for this deployment
     *
     * @return void
     */
    public function createCloudRunService()
    {
        $cloudRunConfig = new CloudRunConfig($this);

        $this->client()->createCloudRunService($cloudRunConfig);
    }

    /**
     * Update the Cloud Run service with this deployment
     *
     * @return void
     */
    public function updateCloudRunService()
    {
        $cloudRunConfig = new CloudRunConfig($this);

        $this->client()->replaceCloudRunService($cloudRunConfig);
    }

    /**
     * Redeploy a given deployment
     *
     * @param int|null $initiatorId
     * @return Deployment
     */
    public function redeploy($initiatorId = null)
    {
        return $this->environment->deploy(
            $this->commit_hash,
            $this->commit_message,
            $initiatorId
        );
    }

    /**
     * Get the environment variables for this deployment.
     *
     * @return EnvVars
     */
    public function envVars(): EnvVars
    {
        $vars = EnvVars::fromString($this->environment->environmental_variables);

        $vars->set('IS_RAFTER', 'true');

        if ($this->project()->isLaravel()) {
            $vars->inject([
                'RAFTER_QUEUE' => $this->environment->queueName(),
                'RAFTER_PROJECT_ID' => $this->environment->projectId(),
                'RAFTER_REGION' => $this->project()->region,
                'CACHE_DRIVER' => 'firestore',
                'QUEUE_CONNECTION' => 'rafter',
                'SESSION_DRIVER' => 'firestore',
                'LOG_CHANNEL' => 'syslog',
            ]);

            if ($this->environment->usesDatabase()) {
                $database = $this->environment->database;

                $vars->inject([
                    'DB_DATABASE' => $database->name,
                    'DB_USERNAME' => $database->databaseUser(),
                    'DB_PASSWORD' => $database->databasePassword(),
                    'DB_SOCKET' => "/cloudsql/{$database->connectionString()}"
                ]);
            }
        }

        return $vars;
    }

    public function client(): GoogleApi
    {
        return $this->environment->client();
    }
}
