@extends('layouts.admin')

@section('title')
    Statistics
@endsection

@section('content-header')
    <h1>Statistics<small>Overview of your panel's servers, users, and live node usage.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Statistics</li>
    </ol>
@endsection

@section('content')

<div class="row">
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-server"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Servers</span>
                <span class="info-box-number">{{ $totalServers }}</span>
                <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                <span class="progress-description">
                    {{ $activeServers }} active &bull; {{ $suspendedServers }} suspended &bull; {{ $installingServers }} installing
                </span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Users</span>
                <span class="info-box-number">{{ $totalUsers }}</span>
                <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                <span class="progress-description">{{ $adminUsers }} admin{{ $adminUsers === 1 ? '' : 's' }}</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-sitemap"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Nodes</span>
                <span class="info-box-number">{{ $totalNodes }}</span>
                <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                <span class="progress-description">{{ $wingsAgentConfigured }} with Wings Agent</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-link"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Allocations Used</span>
                <span class="info-box-number">{{ $usedAllocations }} / {{ $totalAllocations }}</span>
                <div class="progress">
                    <div class="progress-bar" style="width:{{ $totalAllocations > 0 ? round($usedAllocations / $totalAllocations * 100) : 0 }}%"></div>
                </div>
                <span class="progress-description">{{ $totalAllocations > 0 ? round($usedAllocations / $totalAllocations * 100) : 0 }}% in use</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-database"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Databases</span>
                <span class="info-box-number">{{ $totalDatabases }}</span>
                <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                <span class="progress-description">server databases created</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-th-large"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Eggs Available</span>
                <span class="info-box-number">{{ $totalEggs }}</span>
                <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                <span class="progress-description">server configuration eggs</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-lock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Suspended Servers</span>
                <span class="info-box-number">{{ $suspendedServers }}</span>
                <div class="progress">
                    <div class="progress-bar" style="width:{{ $totalServers > 0 ? round($suspendedServers / $totalServers * 100) : 0 }}%"></div>
                </div>
                <span class="progress-description">{{ $totalServers > 0 ? round($suspendedServers / $totalServers * 100) : 0 }}% of all servers</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="info-box">
            <span class="info-box-icon"><i class="fa fa-download"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Installing</span>
                <span class="info-box-number">{{ $installingServers }}</span>
                <div class="progress"><div class="progress-bar" style="width:100%"></div></div>
                <span class="progress-description">servers currently installing</span>
            </div>
        </div>
    </div>
</div>

@php
    $memPct  = $nodeTotalMemory > 0 ? round($serverTotalMemory / $nodeTotalMemory * 100) : 0;
    $diskPct = $nodeTotalDisk   > 0 ? round($serverTotalDisk   / $nodeTotalDisk   * 100) : 0;

    $fmtMib = function(int $mib): string {
        if ($mib >= 1024 * 1024) return round($mib / 1024 / 1024, 1) . ' TiB';
        if ($mib >= 1024)        return round($mib / 1024, 1) . ' GiB';
        return $mib . ' MiB';
    };
@endphp

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bar-chart fa-fw"></i> Resource Allocation Overview</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong><i class="fa fa-microchip fa-fw text-yellow"></i> CPU Allocated to Servers</strong></p>
                        <div class="progress progress-sm">
                            <div class="progress-bar progress-bar-info" style="min-width:2em; width:100%"></div>
                        </div>
                        <p class="text-muted small">
                            <strong>{{ number_format($serverTotalCpu) }}%</strong> total CPU allocated across all servers
                        </p>
                    </div>
                    <div class="col-md-4">
                        <p><strong><i class="fa fa-server fa-fw text-aqua"></i> Memory Allocation</strong></p>
                        <div class="progress progress-sm" style="margin-bottom:4px;">
                            <div class="progress-bar progress-bar-{{ $memPct >= 90 ? 'danger' : ($memPct >= 70 ? 'warning' : 'success') }}"
                                 style="width:{{ min($memPct, 100) }}%"></div>
                        </div>
                        <p class="text-muted small">
                            <strong>{{ $fmtMib($serverTotalMemory) }}</strong> / <strong>{{ $fmtMib($nodeTotalMemory) }}</strong>
                            &mdash; <strong>{{ $memPct }}%</strong> allocated
                        </p>
                    </div>
                    <div class="col-md-4">
                        <p><strong><i class="fa fa-hdd-o fa-fw text-green"></i> Disk Allocation</strong></p>
                        <div class="progress progress-sm" style="margin-bottom:4px;">
                            <div class="progress-bar progress-bar-{{ $diskPct >= 90 ? 'danger' : ($diskPct >= 70 ? 'warning' : 'success') }}"
                                 style="width:{{ min($diskPct, 100) }}%"></div>
                        </div>
                        <p class="text-muted small">
                            <strong>{{ $fmtMib($serverTotalDisk) }}</strong> / <strong>{{ $fmtMib($nodeTotalDisk) }}</strong>
                            &mdash; <strong>{{ $diskPct }}%</strong> allocated
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-success" id="live-stats-box">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-circle fa-fw" id="live-indicator" style="color:#f39c12; font-size:10px; vertical-align:middle;"></i>
                    Live Node Usage
                    <small id="live-nodes-summary" style="margin-left:8px;"></small>
                </h3>
                <div class="box-tools pull-right">
                    <span class="badge bg-green" id="live-status-badge">Connecting…</span>
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body" id="live-stats-body">
                <div id="live-loading" class="text-center" style="padding:20px;">
                    <i class="fa fa-refresh fa-spin fa-2x"></i>
                    <p style="margin-top:10px; color:#888;">Fetching live data from Wings Agents…</p>
                </div>

                <div id="live-stats-content" style="display:none;">
                    <div class="row" id="live-totals-row">
                        <div class="col-lg-2 col-xs-6">
                            <div class="description-block border-right">
                                <span class="description-percentage text-green" id="lt-cpu">–</span>
                                <h5 class="description-header">Total CPU</h5>
                                <span class="description-text">across all nodes</span>
                            </div>
                        </div>
                        <div class="col-lg-2 col-xs-6">
                            <div class="description-block border-right">
                                <span class="description-percentage text-aqua" id="lt-mem">–</span>
                                <h5 class="description-header">Memory Used</h5>
                                <span class="description-text" id="lt-mem-total">–</span>
                            </div>
                        </div>
                        <div class="col-lg-2 col-xs-6">
                            <div class="description-block border-right">
                                <span class="description-percentage text-yellow" id="lt-disk">–</span>
                                <h5 class="description-header">Disk Used</h5>
                                <span class="description-text" id="lt-disk-total">–</span>
                            </div>
                        </div>
                        <div class="col-lg-2 col-xs-6">
                            <div class="description-block border-right">
                                <span class="description-percentage text-green" id="lt-netin">–</span>
                                <h5 class="description-header">Network In</h5>
                                <span class="description-text">bytes/s</span>
                            </div>
                        </div>
                        <div class="col-lg-2 col-xs-6">
                            <div class="description-block border-right">
                                <span class="description-percentage text-red" id="lt-netout">–</span>
                                <h5 class="description-header">Network Out</h5>
                                <span class="description-text">bytes/s</span>
                            </div>
                        </div>
                        <div class="col-lg-2 col-xs-6">
                            <div class="description-block">
                                <span class="description-percentage text-purple" id="lt-conns">–</span>
                                <h5 class="description-header">Connections</h5>
                                <span class="description-text">total open</span>
                            </div>
                        </div>
                    </div>

                    <hr style="margin:10px 0;">

                    <div class="table-responsive" style="margin-top:10px;">
                        <table class="table table-condensed table-hover" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>Node</th>
                                    <th>Status</th>
                                    <th>CPU</th>
                                    <th>Memory</th>
                                    <th>Disk</th>
                                    <th>Net In</th>
                                    <th>Net Out</th>
                                    <th>Conns</th>
                                    <th>Agent Version</th>
                                </tr>
                            </thead>
                            <tbody id="live-nodes-table-body">
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="live-unavailable" style="display:none;">
                    <div class="callout callout-warning">
                        <h4><i class="fa fa-warning"></i> Wings Agent Not Configured</h4>
                        <p>No Wings Agent endpoints are configured. Configure node endpoints in the Wings Agent Addon settings to see live node statistics here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-clock-o fa-fw"></i> Recently Created Servers <small>(last 7 days)</small></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body no-padding">
                @if($recentServers->isEmpty())
                    <div class="box-body">
                        <p class="text-muted text-center" style="margin:20px 0;">No servers created in the last 7 days.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th>Server Name</th>
                                    <th>UUID</th>
                                    <th>Owner</th>
                                    <th>Node</th>
                                    <th>Created</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentServers as $server)
                                <tr>
                                    <td><strong>{{ $server->name }}</strong></td>
                                    <td><code>{{ substr($server->uuid, 0, 8) }}</code></td>
                                    <td>
                                        @if($server->user)
                                            <a href="{{ route('admin.users.view', $server->user->id) }}">
                                                {{ $server->user->name_first }} {{ $server->user->name_last }}
                                                <small class="text-muted">({{ $server->user->username }})</small>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $server->node->name ?? '—' }}</td>
                                    <td title="{{ $server->created_at }}">
                                        {{ $server->created_at->diffForHumans() }}
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.servers.view', $server->id) }}" class="btn btn-xs btn-default" style="cursor:pointer;">
                                            <i class="fa fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@section('footer-scripts')
    @parent

    <script>
    (function () {
        var liveStatsUrl = '{{ route('admin.statistics.live') }}';
        var refreshInterval = 6000;
        var timer = null;

        function fmtBytes(b) {
            if (b === null || b === undefined) return '—';
            if (b >= 1073741824) return (b / 1073741824).toFixed(1) + ' GB';
            if (b >= 1048576)    return (b / 1048576).toFixed(1) + ' MB';
            if (b >= 1024)       return (b / 1024).toFixed(1) + ' KB';
            return b + ' B';
        }

        function fmtBytesPerSec(b) {
            return fmtBytes(b) + '/s';
        }

        function fmtCpu(v) {
            return parseFloat(v).toFixed(1) + '%';
        }

        function statusBadge(online) {
            if (online) {
                return '<span class="label label-success"><i class="fa fa-check-circle"></i> Online</span>';
            }
            return '<span class="label label-danger"><i class="fa fa-times-circle"></i> Offline</span>';
        }

        function fetchLiveStats() {
            $.ajax({
                url: liveStatsUrl,
                method: 'GET',
                timeout: 8000,
                success: function (data) {
                    $('#live-loading').hide();

                    if (!data.available) {
                        $('#live-stats-content').hide();
                        $('#live-unavailable').show();
                        $('#live-status-badge').removeClass('bg-green bg-yellow bg-red').addClass('bg-yellow').text('Not Configured');
                        $('#live-indicator').css('color', '#f39c12');
                        return;
                    }

                    $('#live-unavailable').hide();
                    $('#live-stats-content').show();

                    var t = data.totals || {};
                    var nodesOn = data.nodes_online || 0;
                    var nodesTotal = data.nodes_total || 0;

                    $('#lt-cpu').text(fmtCpu(t.cpu || 0));
                    $('#lt-mem').text(fmtBytes(t.memory_used || 0));
                    $('#lt-mem-total').text('of ' + fmtBytes(t.memory_total || 0));
                    $('#lt-disk').text(fmtBytes(t.disk_used || 0));
                    $('#lt-disk-total').text('of ' + fmtBytes(t.disk_total || 0));
                    $('#lt-netin').text(fmtBytesPerSec(t.net_in || 0));
                    $('#lt-netout').text(fmtBytesPerSec(t.net_out || 0));
                    $('#lt-conns').text((t.connections || 0).toLocaleString());

                    $('#live-nodes-summary').text(nodesOn + ' / ' + nodesTotal + ' nodes online');
                    if (nodesOn === nodesTotal && nodesTotal > 0) {
                        $('#live-status-badge').removeClass('bg-green bg-yellow bg-red').addClass('bg-green').text('All Online');
                        $('#live-indicator').css('color', '#00a65a');
                    } else if (nodesOn === 0) {
                        $('#live-status-badge').removeClass('bg-green bg-yellow bg-red').addClass('bg-red').text('All Offline');
                        $('#live-indicator').css('color', '#dd4b39');
                    } else {
                        $('#live-status-badge').removeClass('bg-green bg-yellow bg-red').addClass('bg-yellow').text('Partial');
                        $('#live-indicator').css('color', '#f39c12');
                    }

                    var tbody = $('#live-nodes-table-body');
                    tbody.empty();
                    var nodes = data.nodes || [];
                    if (nodes.length === 0) {
                        tbody.append('<tr><td colspan="9" class="text-center text-muted">No nodes data available.</td></tr>');
                    } else {
                        nodes.forEach(function (n) {
                            var memTotal = n.memory_total || 0;
                            var diskTotal = n.disk_total || 0;
                            var memPct = memTotal > 0 ? Math.round((n.memory_used || 0) / memTotal * 100) : 0;
                            var diskPct = diskTotal > 0 ? Math.round((n.disk_used || 0) / diskTotal * 100) : 0;

                            var memBar = memPct >= 90 ? 'danger' : (memPct >= 70 ? 'warning' : 'success');
                            var diskBar = diskPct >= 90 ? 'danger' : (diskPct >= 70 ? 'warning' : 'success');

                            var row = '<tr>';
                            row += '<td><strong>' + $('<span>').text(n.node_name).html() + '</strong></td>';
                            row += '<td>' + statusBadge(n.online) + '</td>';

                            if (n.online) {
                                row += '<td>' + fmtCpu(n.cpu || 0) + '</td>';
                                row += '<td>';
                                row += '<div class="progress progress-xs" style="margin-bottom:2px;"><div class="progress-bar progress-bar-' + memBar + '" style="width:' + memPct + '%"></div></div>';
                                row += fmtBytes(n.memory_used || 0) + ' / ' + fmtBytes(memTotal);
                                row += '</td>';
                                row += '<td>';
                                row += '<div class="progress progress-xs" style="margin-bottom:2px;"><div class="progress-bar progress-bar-' + diskBar + '" style="width:' + diskPct + '%"></div></div>';
                                row += fmtBytes(n.disk_used || 0) + ' / ' + fmtBytes(diskTotal);
                                row += '</td>';
                                row += '<td>' + fmtBytesPerSec(n.net_in || 0) + '</td>';
                                row += '<td>' + fmtBytesPerSec(n.net_out || 0) + '</td>';
                                row += '<td>' + (n.connections || 0) + '</td>';
                                row += '<td>' + (n.version ? '<code>' + $('<span>').text(n.version).html() + '</code>' : '<span class="text-muted">—</span>') + '</td>';
                            } else {
                                row += '<td colspan="7" class="text-muted">Agent unreachable</td>';
                            }

                            row += '</tr>';
                            tbody.append(row);
                        });
                    }
                },
                error: function () {
                    $('#live-status-badge').removeClass('bg-green bg-yellow bg-red').addClass('bg-red').text('Error');
                    $('#live-indicator').css('color', '#dd4b39');
                }
            });
        }

        fetchLiveStats();
        timer = setInterval(fetchLiveStats, refreshInterval);

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                clearInterval(timer);
            } else {
                fetchLiveStats();
                timer = setInterval(fetchLiveStats, refreshInterval);
            }
        });
    })();
    </script>
@endsection
