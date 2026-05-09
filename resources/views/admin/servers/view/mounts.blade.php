@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }}: Mounts
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>Manage mounts for this server.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">Mounts</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Active Mounts</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Source</th>
                            <th>Target</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($server->mounts as $mount)
                        <tr>
                            <td>{{ $mount->id }}</td>
                            <td>{{ $mount->name }}</td>
                            <td><code>{{ $mount->source }}</code></td>
                            <td><code>{{ $mount->target }}</code></td>
                            <td>
                                <form action="{{ route('admin.servers.view.mounts.store', $server->id) }}" method="POST">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <input type="hidden" name="mount_id" value="{{ $mount->id }}">
                                    <button type="submit" class="btn btn-xs btn-danger">Remove</button>
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
                <h3 class="box-title">Add Mount</h3>
            </div>
            <form action="{{ route('admin.servers.view.mounts.store', $server->id) }}" method="POST">
                <div class="box-body">
                    {!! csrf_field() !!}
                    <div class="form-group">
                        <label for="pMountId" class="control-label">Mount</label>
                        <select id="pMountId" name="mount_id" class="form-control">
                            @foreach($mounts as $mount)
                                <option value="{{ $mount->id }}">{{ $mount->name }} ({{ $mount->source }} → {{ $mount->target }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success btn-sm">Add Mount</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
