<?php

namespace App;

class CloudBuild
{
    const DOCKERFILES = [
        'laravel' => 'https://storage.googleapis.com/rafter-dockerfiles/Dockerfile-laravel',
    ];

    protected $attributes = [];
    protected $manual = false;
    protected $project;

    public function __construct(Project $project) {
        $this->project = $project;
    }

    public function forManualPush($bucket, $object)
    {
        $this->manual = true;
        $this->attributes['bucket'] = $bucket;
        $this->attributes['object'] = $object;

        return $this;
    }

    public function isManual()
    {
        return $this->manual;
    }

    public function source()
    {
        if ($this->isManual()) {
            return [
                'storageSource' => [
                    'bucket' => $this->attributes['bucket'],
                    'object' => $this->attributes['object'],
                ],
            ];
        } else {
            // TODO: Handle GitHub/blank source
        }
    }

    public function steps()
    {
        return [
            // Copy the Dockerfile we need
            [
                'name' => 'gcr.io/cloud-builders/curl',
                'args' => [static::DOCKERFILES['laravel'], '--output', 'Dockerfile'],
            ],

            // TEST: Show the dir
            [
                'name' => 'ubuntu',
                'args' => ['ls', '-la', './'],
            ],

            // Build the image
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'args' => [
                    'build',
                    '-t', $this->imageLocation(),
                    // TODO: Add caching
                    // '--cache-from', "{$this->imageLocation()}:latest",
                    '.'
                ],
            ],

            // Upload it to GCR
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'args' => ['push', $this->imageLocation()],
            ],
        ];
    }

    public function imageLocation()
    {
        // TODO: Use better, sluggified version of project name
        return "gcr.io/\$PROJECT_ID/{$this->project->name}";
    }

    public function images()
    {
        return [
            $this->imageLocation(),
        ];
    }

    public function instructions()
    {
        return [
            'source' => $this->source(),
            'steps' => $this->steps(),
            'images' => $this->images(),
        ];
    }
}
