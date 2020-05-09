<?php

namespace App\Http\Livewire;

use App\Environment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class CommandsList extends Component
{
    use AuthorizesRequests, WithPagination;

    public $environment;

    public $command;

    public function mount(Environment $environment)
    {
        $this->environment = $environment;
    }

    public function render()
    {
        return view('livewire.commands-list', [
            'commands' => $this->environment->commands()->paginate(10),
        ]);
    }

    public function runCommand()
    {
        $this->authorize('update', $this->environment);

        $this->validate([
            'command' => ['required', 'string'],
        ]);

        $command = $this->environment->commands()->create([
            'command' => $this->command,
            'user_id' => request()->user()->id,
        ]);

        $command->dispatch();

        return redirect()->route('projects.environments.commands.show', [
            $this->environment->project,
            $this->environment,
            $command
        ]);
    }
}
