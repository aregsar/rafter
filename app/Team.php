<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $guarded = [];

    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * Eager load the following relations.
     *
     * @var array
     */
    protected $with = [
        'projects',
    ];

    public function owner()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany('App\User');
    }

    public function googleProjects()
    {
        return $this->hasMany('App\GoogleProject');
    }

    public function projects()
    {
        return $this->hasMany('App\Project');
    }

    public function databaseInstances()
    {
        return $this->hasManyThrough('App\DatabaseInstance', 'App\GoogleProject');
    }

    /**
     * Whether the given user belongs to a team.
     *
     * @param User $user
     * @return boolean
     */
    public function hasUser(User $user)
    {
        return $this->owner->is($user) || $this->users->contains($user->id);
    }
}
