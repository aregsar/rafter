<?php

namespace App\GoogleCloud;

use App\Deployment;
use Illuminate\Support\Facades\URL;

class CloudBuildConfig
{
    protected $attributes = [];
    protected $manual = false;
    protected $deployment;
    protected $environment;

    public function __construct(Deployment $deployment) {
        $this->deployment = $deployment;
        $this->environment = $deployment->environment;
    }

    /**
     * Mark a manual deployment (i.e. not Git-based)
     *
     * @param string $bucket
     * @param string $object
     * @return self
     */
    public function forManualPush($bucket, $object)
    {
        $this->manual = true;
        $this->attributes['bucket'] = $bucket;
        $this->attributes['object'] = $object;

        return $this;
    }

    /**
     * Whether it's a manual deployment
     *
     * @return boolean
     */
    public function isManual()
    {
        return $this->manual;
    }

    /**
     * Whether it's a git-based project
     *
     * @return boolean
     */
    public function isGitBased()
    {
        return ! $this->isManual();
    }

    /**
     * Get the source spec for the Build.
     *
     * @return array
     */
    public function source()
    {
        if ($this->isManual()) {
            return [
                'storageSource' => [
                    'bucket' => $this->attributes['bucket'],
                    'object' => $this->attributes['object'],
                ],
            ];
        }

        return [];
    }

    /**
     * The step to download the Git repository
     *
     * @return array
     */
    protected function downloadGitRepoStep()
    {
        return [
            'name' => 'gcr.io/cloud-builders/git',
            'args' => ['clone', '--depth=1', $this->deployment->cloneUrl()],
        ];

        // TODO: Get tarball working, as it will be much smaller
        // return [
        //     'name' => 'gcr.io/cloud-builders/curl',
        //     'args' => [$this->deployment->tarballUrl(), '-L', '--output', 'repo.tar.gz'],
        // ];
    }

    /**
     * Get the name of the repo's project
     *
     * @return string
     */
    protected function repoName()
    {
        return explode('/', $this->deployment->repository())[1];
    }

    /**
     * Get the project type.
     *
     * @return string
     */
    protected function projectType()
    {
        return $this->deployment->project()->type;
    }

    /**
     * Get the Build instructions URL
     *
     * @param string $file
     * @return string
     */
    protected function buildInstructions($file)
    {
        URL::forceRootUrl(config('app.url'));

        return route('build-instructions', [
            'type' => $this->projectType(),
            'file' => $file
        ]);
    }

    /**
     * The steps required to build this image
     *
     * @return array
     */
    public function steps()
    {
        $steps = [
            // Pull the image down so we can build from cache
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'entrypoint' => 'bash',
                'args' => ['-c', "docker pull {$this->imageLocation()}:latest || exit 0"],
            ],

            $this->isGitBased() ? $this->downloadGitRepoStep() : [],

            // Copy the Dockerfile we need
            [
                'name' => 'gcr.io/cloud-builders/curl',
                'args' => [$this->buildInstructions('Dockerfile'), '--output', 'Dockerfile'],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],

            // Copy the entrypoint we need
            [
                'name' => 'gcr.io/cloud-builders/curl',
                'args' => [$this->buildInstructions('docker-entrypoint'), '--output', 'docker-entrypoint.sh'],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],

            // TEST: Show the dir
            [
                'name' => 'ubuntu',
                'args' => ['ls', '-la', './'],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],

            // Build the image
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'args' => [
                    'build',
                    '-t', $this->imageLocation(),
                    '--cache-from', "{$this->imageLocation()}:latest",
                    '.'
                ],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],

            // Upload it to GCR
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'args' => ['push', $this->imageLocation()],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],
        ];

        return collect($steps)->filter();
    }

    /**
     * The location of the image on GCR
     *
     * @return string
     */
    public function imageLocation()
    {
        return "gcr.io/\$PROJECT_ID/{$this->environment->slug()}";
    }

    /**
     * The images that will be built.
     *
     * @return array
     */
    public function images()
    {
        return [
            $this->imageLocation(),
        ];
    }

    /**
     * The instructions to send to the Cloud Build API
     *
     * @return array
     */
    public function instructions()
    {
        return array_filter([
            'source' => $this->source(),
            'steps' => $this->steps(),
            'images' => $this->images(),
        ]);
    }
}
