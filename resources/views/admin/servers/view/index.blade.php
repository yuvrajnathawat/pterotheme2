@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }}
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>{{ $server->uuid }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li class="active">{{ $server->name }}</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Server Information</h3>
            </div>
            <div class="box-body">
                <table class="table table-hover">
                    <tbody>
                        <tr><td>UUID</td><td>{{ $server->uuid }}</td></tr>
                        <tr><td>Name</td><td>{{ $server->name }}</td></tr>
                        <tr><td>Owner</td><td><a href="{{ route('admin.users.view', $server->owner_id) }}">{{ $server->user->email }}</a></td></tr>
                        <tr><td>Node</td><td><a href="{{ route('admin.nodes.view', $server->node_id) }}">{{ $server->node->name }}</a></td></tr>
                        <tr><td>Status</td><td>{{ $server->status ?? 'Active' }}</td></tr>
                        <tr><td>Memory</td><td>{{ $server->memory }} MB</td></tr>
                        <tr><td>Disk</td><td>{{ $server->disk }} MB</td></tr>
                        <tr><td>CPU</td><td>{{ $server->cpu }}%</td></tr>
                        <tr><td>Created</td><td>{{ $server->created_at }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
