<?php

namespace App\Http\Controllers;

use App\Environment;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HookDeploymentController extends Controller
{
    public function store(Request $request, $type)
    {
        if ($request->header('X-GitHub-Event') !== 'push') {
            return response('', 200);
        }

        // Log::info($request->all());

        $installationId = $request->installation['id'];
        $branch = str_replace("refs/heads/", "", $request->ref);
        $repository = $request->repository['full_name'];
        $hash = $request->head_commit['id'];
        $message = $request->head_commit['message'];
        $senderEmail = $request->pusher['email'] ?? null;

        $environment = Environment::query()
            ->where('branch', $branch)
            ->whereHas('project.sourceProvider', function ($query) use ($installationId, $type) {
                $query->where([
                    ['installation_id', $installationId],
                    ['type', $type],
                ]);
            })
            ->whereHas('project', function ($query) use ($repository) {
                $query->where('repository', $repository);
            })
            ->first();

        if ($environment) {
            $user = User::where('email', $senderEmail)->first();
            $initiatorId = null;

            if ($user && $environment->project->team->hasUser($user)) {
                $initiatorId = $user->id;
            }

            $environment->deploy($hash, $message, $initiatorId);
        }

        return response('', 200);
    }
}
