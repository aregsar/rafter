<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Project;
use Faker\Generator as Faker;

$factory->define(Project::class, function (Faker $faker) {
    return [
        'name' => $faker->text(20),
        'type' => $faker->randomElement(array_keys(Project::TYPES)),
        'team_id' => factory('App\Team'),
        'google_project_id' => factory('App\GoogleProject'),
        'source_provider_id' => factory('App\SourceProvider'),
        'repository' => 'rafter/rafter',
        'region' => 'us-central1',
    ];
});

$factory->state(Project::class, 'laravel', [
    'type' => 'laravel',
]);

$factory->state(Project::class, 'nodejs', [
    'type' => 'nodejs',
]);

$factory->state(Project::class, 'rails', [
    'type' => 'rails',
]);
