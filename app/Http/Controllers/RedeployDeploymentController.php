<?php

namespace App\Http\Controllers;

use App\Deployment;
use App\Environment;
use App\Project;

class RedeployDeploymentController extends Controller
{
    public function __invoke(Project $project, Environment $environment, Deployment $deployment)
    {
        $this->authorize('update', $deployment);

        $newDeployment = $deployment->redeploy(auth()->user()->id);

        return redirect()->route('projects.environments.deployments.show', [$project, $environment, $newDeployment])
            ->with('notify', 'Deployment has been redeployed');
    }
}
