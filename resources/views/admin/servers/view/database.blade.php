@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }}: Databases
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>Manage databases for this server.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">Databases</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Active Databases</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Database</th>
                            <th>Username</th>
                            <th>Connections From</th>
                            <th>Host</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($server->databases as $database)
                        <tr>
                            <td>{{ $database->database }}</td>
                            <td>{{ $database->username }}</td>
                            <td>{{ $database->remote }}</td>
                            <td>{{ $database->host->host }}:{{ $database->host->port }}</td>
                            <td>
                                <form action="{{ route('api.application.servers.databases.view', ['server' => $server->id, 'database' => $database->id]) }}" method="POST">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xs-12">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Create New Database</h3>
            </div>
            <form action="{{ route('admin.servers.view.database.store', $server->id) }}" method="POST">
                <div class="box-body">
                    {!! csrf_field() !!}
                    <div class="form-group col-md-6">
                        <label for="pDatabaseHost" class="control-label">Database Host</label>
                        <select id="pDatabaseHost" name="database_host_id" class="form-control">
                            @foreach($hosts as $host)
                                <option value="{{ $host->id }}">{{ $host->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="pDatabaseName" class="control-label">Database Name</label>
                        <div class="input-group">
                            <span class="input-group-addon">s{{ $server->id }}_</span>
                            <input id="pDatabaseName" type="text" name="database" class="form-control" placeholder="database">
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="pRemote" class="control-label">Connections From</label>
                        <input id="pRemote" type="text" name="remote" class="form-control" value="%">
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success btn-sm">Create Database</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
