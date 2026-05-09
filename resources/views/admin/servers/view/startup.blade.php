@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }}: Startup
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>Control startup parameters for this server.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">Startup</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Startup Command Modification</h3>
            </div>
            <form action="{{ route('admin.servers.view.startup.update', $server->id) }}" method="POST">
                <div class="box-body">
                    {!! csrf_field() !!}
                    <div class="form-group">
                        <label for="pStartup" class="control-label">Startup Command</label>
                        <input id="pStartup" name="startup" class="form-control" type="text" value="{{ old('startup', $server->startup) }}">
                        <p class="text-muted small">The following data replacements are made to the startup command: <code>{{variable}}</code>.</p>
                    </div>
                    <div class="form-group">
                        <label for="pDefaultStartupCommand" class="control-label">Default Service Start Command</label>
                        <input id="pDefaultStartupCommand" class="form-control" type="text" value="{{ $server->egg->startup }}" readonly>
                    </div>
                    @foreach($variables as $variable)
                    <div class="form-group">
                        <label for="env_{{ $variable->env_variable }}" class="control-label">
                            {{ $variable->name }}
                            @if($variable->required)<span class="label label-danger">Required</span>@endif
                        </label>
                        <input
                            id="env_{{ $variable->env_variable }}"
                            name="environment[{{ $variable->env_variable }}]"
                            class="form-control"
                            type="text"
                            value="{{ old('environment.' . $variable->env_variable, $variable->server_value ?? $variable->default_value) }}"
                            {{ $variable->user_editable ? '' : 'readonly' }}
                        >
                        <p class="text-muted small">{{ $variable->description }}</p>
                    </div>
                    @endforeach
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Save Modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
