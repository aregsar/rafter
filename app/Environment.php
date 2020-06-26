<?php

namespace App;

use App\Casts\Options;
use App\GoogleCloud\CloudBuildSecrets;
use App\GoogleCloud\SchedulerJobConfig;
use App\Services\GoogleApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class Environment extends Model
{
    use HasOptions;

    const INITIAL_ENVIRONMENTS = [
        'production',
    ];

    protected $hidden = [
        'environmental_variables',
    ];

    protected $guarded = [];

    protected $casts = [
        'options' => Options::class,
    ];

    protected $defaultOptions = [
        'web_memory' => '1Gi',
        'worker_memory' => '1Gi',
        'web_cpu' => 1,
        'worker_cpu' => 1,
        'web_request_timeout' => 300,
        'worker_request_timeout' => 300,
        'web_max_requests_per_container' => 80,
        'worker_max_requests_per_container' => 80,
        'web_max_instances' => 1000,
        'worker_max_instances' => 1000,
        'wait_for_checks' => false,
    ];

    public function project()
    {
        return $this->belongsTo('App\Project');
    }

    public function deployments()
    {
        return $this->hasMany('App\Deployment')->latest('id');
    }

    public function database()
    {
        return $this->belongsTo('App\Database');
    }

    public function commands()
    {
        return $this->hasMany('App\Command')->latest();
    }

    public function sourceProvider()
    {
        return $this->project->sourceProvider;
    }

    public function domainMappings()
    {
        return $this->hasMany('App\DomainMapping')->latest();
    }

    /**
     * Get the active deployment
     *
     * @return Deployment
     */
    public function activeDeployment()
    {
        return $this->belongsTo('App\Deployment', 'active_deployment_id');
    }

    public function primaryDomain(): string
    {
        $domain = $this->domainMappings()->active()->first()->domain ?? $this->url;

        return str_replace('https://', '', $domain);
    }

    public function additionalDomainsCount(): int
    {
        return $this->domainMappings()->active()->count();
    }

    public function repository(): ?string
    {
        return $this->project->repository;
    }

    /**
     * Whether the environment is using a database.
     */
    public function usesDatabase()
    {
        return $this->database()->exists();
    }

    /**
     * Create a database for this environment on a given DatabaseInstance.
     */
    public function createDatabase(DatabaseInstance $databaseInstance)
    {
        $database = $databaseInstance->databases()->create([
            'name' => $this->slug(),
        ]);

        $this->database()->associate($database);
        $this->save();

        $database->provision();
    }

    /**
     * Get a slug version of the environment name.
     */
    public function slug()
    {
        return Str::slug($this->project->name . '-' . $this->name);
    }

    /**
     * The queue name is the slug.
     *
     * @return string
     */
    public function queueName()
    {
        return $this->slug();
    }

    /**
     * Get the Google Project ID
     *
     * @return string
     */
    public function projectId()
    {
        return $this->project->googleProject->project_id;
    }

    /**
     * Get the decoded Service Account information for the project.
     *
     * @return array
     */
    public function serviceAccountJson(): array
    {
        return $this->project->googleProject->service_account_json;
    }

    /**
     * Get the Service Account Email to be used for interactions with the API.
     *
     * @return string
     */
    public function serviceAccountEmail(): string
    {
        return $this->serviceAccountJson()['client_email'];
    }

    /**
     * The region/location used for the given environment.
     *
     * @return string
     */
    public function region(): string
    {
        return $this->project->region;
    }

    /**
     * Whether this environment has been successfully deployed at least once.
     *
     * @return boolean
     */
    public function hasBeenDeployedSuccessfully()
    {
        return $this->activeDeployment()->exists();
    }

    /**
     * Provision an environment for the first time.
     *
     * @return void
     */
    public function provision($initialVariables = '')
    {
        $this->setInitialEnvironmentVariables($initialVariables);
        $this->createInitialDeployment();
    }

    /**
     * Set the initial environment variables for this project.
     *
     * While Rafter also injects "hidden" variables at runtime, these variables are
     * set once and not changed by Rafter. This also allows the user to modify them, e.g.
     * if they'd like to rotate the keys or change the name of their app.
     *
     * @return void
     */
    public function setInitialEnvironmentVariables($initialVariables = '')
    {
        $vars = EnvVars::fromString($initialVariables);

        if ($this->project->isLaravel()) {
            $appKey = 'base64:' . base64_encode(Encrypter::generateKey(config('app.cipher')));

            $vars->inject([
                'APP_NAME' => $this->project->name,
                'APP_ENV' => $this->name,
                'APP_KEY' => $appKey,
            ]);
        }

        if ($this->project->isRails()) {
            $vars->inject([
                'RAILS_ENV' => $this->name,
                'RAILS_SERVE_STATIC_FILES' => true,
                'RAILS_LOG_TO_STDOUT' => true,
            ]);
        }

        $this->environmental_variables = $vars->toString();
        $this->save();
    }

    /**
     * Add a single env var to this environment's variables
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function addEnvVar($key, $value)
    {
        $vars = EnvVars::fromString($this->environmental_variables);

        $vars->set($key, $value);

        $this->environmental_variables = $vars->toString();
        $this->save();
    }

    /**
     * Whether an env var exists
     *
     * @param string $key
     * @return boolean
     */
    public function hasEnvVar(string $key)
    {
        $vars = EnvVars::fromString($this->environmental_variables);

        return $vars->has($key);
    }

    /**
     * Get an env var.
     *
     * @param string $key
     * @return mixed
     */
    public function getEnvVar(string $key)
    {
        $vars = EnvVars::fromString($this->environmental_variables);

        return $vars->get($key);
    }

    public function buildSecrets(): CloudBuildSecrets
    {
        return new CloudBuildSecrets($this);
    }

    /**
     * Create an initial deployment on Cloud Run.
     */
    public function createInitialDeployment()
    {
        // TODO: Make more flexible (support manual pushes, etc)
        $deployment = $this->deployments()->create([
            'commit_hash' => $this->sourceProvider()->client()->latestHashFor($this->project->repository, $this->branch),
            'commit_message' => 'Initial Deploy',
            'initiator_id' => $this->project->team->owner->id,
        ]);

        $deployment->createSourceProviderDeployment([
            'manual' => true,
        ]);

        $jobs = DeploymentSteps::for($deployment)
            ->initialDeployment()
            ->get();

        Bus::dispatchChain($jobs);

        return $deployment;
    }

    /**
     * Deploy the HEAD of the current branch.
     * Always done manually by a user.
     *
     * @param int|null $initiatorId
     * @return Deployment
     */
    public function deploy($initiatorId): Deployment
    {
        $commit = $this->sourceProvider()->client()->latestCommitFor($this->repository(), $this->branch);
        $sha = $commit['sha'];
        $message = $commit['commit']['message'];

        $deployment = $this->deployments()->create([
            'commit_hash' => $sha,
            'commit_message' => $message,
            'initiator_id' => $initiatorId,
        ]);

        $deployment->createSourceProviderDeployment([
            'manual' => true,
        ]);

        $steps = DeploymentSteps::for($deployment);

        if (!$this->hasBeenDeployedSuccessfully()) {
            $steps->initialDeployment();
        }

        Bus::dispatchChain($steps->get());

        return $deployment;
    }

    /**
     * Create a new deployment on Cloud Run for a specific hash.
     */
    public function deployHash($commitHash, $initiatorId): Deployment
    {
        $commitMessage = $this->sourceProvider()->client()->messageForHash($this->repository(), $commitHash);
        $deployment = $this->deployments()->create([
            'commit_hash' => $commitHash,
            'commit_message' => $commitMessage,
            'initiator_id' => $initiatorId,
        ]);

        Bus::dispatchChain(DeploymentSteps::for($deployment)->get());

        return $deployment;
    }

    /**
     * Redeploy a deployment without having to wait for a build.
     *
     * @param Deployment $deployment
     * @param int|null $initiatorId
     * @return Deployment
     */
    public function redeploy(Deployment $deployment, $initiatorId): Deployment
    {
        $newDeployment = $this->deployments()->create([
            'commit_hash' => $deployment->commit_hash,
            'commit_message' => $deployment->commit_message,
            'image' => $deployment->image,
            'initiator_id' => $initiatorId,
        ]);

        $newDeployment->createSourceProviderDeployment([
            'manual' => true,
        ]);

        $steps = DeploymentSteps::for($newDeployment);

        if ($this->hasBeenDeployedSuccessfully()) {
            $steps->redeploy();
        } else {
            $steps->initialDeployment();
        }

        Bus::dispatchChain($steps->get());

        return $newDeployment;
    }

    /**
     * Update the URL on the environment. This will only run once.
     */
    public function setUrl($url)
    {
        if (!empty($this->url)) return;

        $this->url = $url;
        $this->save();

        $this->addEnvVar('APP_URL', $this->url);
    }

    /**
     * Set the URL for the worker service. This will only run once.
     *
     * @param string $url
     * @return void
     */
    public function setWorkerUrl($url)
    {
        if (!empty($this->worker_url)) return;

        $this->worker_url = $url;
        $this->save();
    }

    /**
     * Set the name of the web service
     *
     * @param string $name
     * @return void
     */
    public function setWebName($name)
    {
        $this->web_service_name = $name;
        $this->save();
    }

    /**
     * Set the name of the worker service
     *
     * @param string $name
     * @return void
     */
    public function setWorkerName($name)
    {
        $this->worker_service_name = $name;
        $this->save();
    }

    /**
     * Start the scheduler job for every minute, on the minute.
     *
     * @return array
     */
    public function startScheduler()
    {
        return $this->client()->createSchedulerJob(new SchedulerJobConfig($this));
    }

    /**
     * Set a secret using Google Secret Manager for this environment.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setSecret(string $key, string $value)
    {
        return $this->client()->setSecret($key, $value);
    }

    /**
     * Get logs for the service.
     *
     * @return array
     */
    public function logs($serviceName = 'web', $logType = 'all'): array
    {
        $serviceNameProperty = "{$serviceName}_service_name";

        $config = [
            'projectId' => $this->projectId(),
            'serviceName' => $this->$serviceNameProperty,
            'location' => $this->region(),
            'logType' => $logType,
        ];

        return $this->client()->getLogsForService($config);
    }

    /**
     * Add a domain mapping.
     *
     * @param array $data Input data
     * @return
     */
    public function addDomainMapping($data): DomainMapping
    {
        $mapping = $this->domainMappings()->create($data);

        $this->refresh();

        dispatch(function () use ($mapping) {
            $mapping->provision();
        });

        return $mapping;
    }

    /**
     * Whether this environment is set to wait for checks, and those checks are not yet finished.
     *
     * @param string $repository
     * @param string $hash
     * @return boolean
     */
    public function shouldWaitForChecks(string $repository, string $hash)
    {
        return $this->getOption('wait_for_checks') && !$this->sourceProvider()->client()->commitChecksSuccessful($repository, $hash);
    }

    /**
     * Get an optional initiator, provided an email address. It's possible another teammate on
     * a source provider will have initiated a deploy, but not be a part of this Rafter team.
     * In which case, we return null.
     *
     * @param string $email
     * @return User|\Illuminate\Support\Optional
     */
    public function getInitiator($email)
    {
        $user = User::where('email', $email)->first();

        if ($user && $this->project->team->hasUser($user)) {
            return $user;
        }

        return optional();
    }

    public function client(): GoogleApi
    {
        return $this->project->googleProject->client();
    }
}
