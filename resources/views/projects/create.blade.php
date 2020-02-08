@extends('layouts.app')

@section('content')
<!-- TODO: Conditionally show form based on whether user has connected GitHub -->
@component('components.card')
    @include('components.flash')

    @slot('title')
        <h1>Create a Project</h1>
    @endslot

    <form action="{{ route('projects.store') }}" method="POST">
        @csrf
        @include('components.form.input', [
            'name' => 'name',
            'label' => 'Project Name',
            'required' => true,
        ])
        @include('components.form.select', [
            'name' => 'google_project_id',
            'label' => 'Google Project',
            'required' => true,
            'options' => $googleProjects->reduce(function ($memo, $p) {
                $memo[$p->id] = $p->name;
                return $memo;
            }, [])
        ])
        @include('components.form.select', [
            'name' => 'type',
            'label' => 'Project Type',
            'required' => true,
            'options' => $types
        ])
        @include('components.form.select', [
            'name' => 'region',
            'label' => 'Region',
            'required' => true,
            'options' => $regions,
        ])
        @include('components.form.select', [
            'name' => 'source_provider_id',
            'label' => 'Deployment Source',
            'required' => true,
            'options' => $sourceProviders->reduce(function ($memo, $p) {
                $memo[$p->id] = $p->name;
                return $memo;
            }, []),
        ])
        @include('components.form.input', [
            'name' => 'repository',
            'label' => 'GitHub Repository',
            'required' => true,
        ])
        <div class="text-right">
            @component('components.button')
            Create Project
            @endcomponent
        </div>
    </form>
@endcomponent
@endsection
