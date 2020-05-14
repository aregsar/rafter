<?php

use App\Environment;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UsersTableSeeder::class);

        $user = User::first();
        $team = $user->currentTeam;

        /**
         * Connect a GitHub source provider, if provided.
         */
        $gitHubInstallationId = env('SEED_GITHUB_INSTALLATION_ID');
        $gitHubToken = env('SEED_GITHUB_TOKEN');

        if ($gitHubInstallationId && $gitHubToken) {
            $sourceProvider = $user->sourceProviders()->create([
                'name' => 'GitHub',
                'type' => 'GitHub',
                'installation_id' => $gitHubInstallationId,
                'meta' => ['token' => $gitHubToken],
            ]);
        }

        /**
         * Connect a Google Project, if provided.
         */
        $googleProjectName = env('SEED_GOOGLE_PROJECT_NAME');
        $googleProjectId = env('SEED_GOOGLE_PROJECT_ID');
        $googleProjectNumber = env('SEED_GOOGLE_PROJECT_NUMBER');

        if ($googleProjectName && $googleProjectId && $googleProjectNumber && File::exists(__DIR__ . '/../../service-account.json')) {
            $googleProject = $team->googleProjects()->create([
                'name' => $googleProjectName,
                'project_id' => $googleProjectId,
                'project_number' => $googleProjectNumber,
                'service_account_json' => json_decode(File::get(__DIR__ . '/../../service-account.json')),
            ]);

            $project = $team->projects()->create([
                'name' => 'Laravel Example',
                'type' => 'laravel',
                'google_project_id' => $googleProject->id,
                'source_provider_id' => $sourceProvider->id ?? null,
                'repository' => 'rafter-platform/rafter-example-laravel',
                'region' => 'us-central1',
            ]);

            /**
             * TODO: Make this more flexible for other developers. The following logic assumes
             * the developer has deployed the service *at least once* already, and thus is short-circuiting
             * the normal initial environment and deployment creation process.
             *
             * A new developer may want to comment this out, or we can put it behind some sort of flag.
             */
            $environment = $project->environments()->create([
                'name' => 'production',
                'branch' => 'master',
                'url' => env('SEED_SERVICE_WEB_URL', 'https://laravel-example-production-nmyoncbzeq-uc.a.run.app'),
                'worker_url' => env('SEED_SERVICE_WORKER_URL', 'https://laravel-example-production-worker-nmyoncbzeq-uc.a.run.app'),
                'web_service_name' => 'laravel-example-production',
                'worker_service_name' => 'laravel-example-production-worker',
            ]);

            $environment->setInitialEnvironmentVariables();

            $environment->deployments()->create([
                'initiator_id' => $user->id,
                'commit_message' => 'Initial (seeded) deploy.',
                'status' => 'successful',
            ]);

            $environment->domainMappings()->create([
                'domain' => 'laravel-demo.rafter.app',
                'status' => 'active',
            ]);
        }
    }
}
