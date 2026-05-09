<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Throwable;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Allocation;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Models\Filters\AdminServerFilter;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Traits\Controllers\JavascriptInjection;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\Repositories\Eloquent\LocationRepository;
use Pterodactyl\Repositories\Eloquent\AllocationRepository;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Models\NodeBackupConfig;

class NodeViewController extends Controller
{
    use JavascriptInjection;

    private const ADDON_SETTINGS_KEY = 'settings::app:addons:hyperv1';

    /**
     * NodeViewController constructor.
     */
    public function __construct(
        private AllocationRepository $allocationRepository,
        private LocationRepository $locationRepository,
        private NodeRepository $repository,
        private ServerRepository $serverRepository,
        private SoftwareVersionService $versionService,
        private ViewFactory $view,
        private SettingsRepository $settingsRepository
    ) {
    }

    /**
     * Check whether the Wings Agent "Live Node Statistics" feature is enabled.
     */
    private function isLiveNodeStatsEnabled(): bool
    {
        try {
            $raw  = $this->settingsRepository->get(self::ADDON_SETTINGS_KEY, '{}');
            $data = json_decode($raw, true);
            return (bool) ($data['addons']['wings-addon']['live_node_stats'] ?? false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check whether Wings Agent firewall management is enabled.
     * This is additionally gated by live node stats.
     */
    private function isFirewallManagementEnabled(): bool
    {
        try {
            $raw = $this->settingsRepository->get(self::ADDON_SETTINGS_KEY, '{}');
            $data = json_decode($raw, true);

            $liveStats = (bool) ($data['addons']['wings-addon']['live_node_stats'] ?? false);
            $firewall = (bool) ($data['addons']['wings-addon']['firewall_management'] ?? false);

            return $liveStats && $firewall;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Returns index view for a specific node on the system.
     */
    public function index(Request $request, Node $node): View
    {
        $node = $this->repository->loadLocationAndServerCount($node);

        $liveNodeStatsEnabled = $this->isLiveNodeStatsEnabled();
        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        return $this->view->make('admin.nodes.view.index', [
            'node'                 => $node,
            'stats'                => $this->repository->getUsageStats($node),
            'version'              => $this->versionService,
            'liveNodeStatsEnabled' => $liveNodeStatsEnabled,
            'firewallManagementEnabled' => $firewallManagementEnabled,
        ]);
    }

    /**
     * Returns the settings page for a specific node.
     */
    public function settings(Request $request, Node $node): View
    {
        $liveNodeStatsEnabled = $this->isLiveNodeStatsEnabled();
        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        return $this->view->make('admin.nodes.view.settings', [
            'node'                 => $node,
            'locations'            => $this->locationRepository->all(),
            'liveNodeStatsEnabled' => $liveNodeStatsEnabled,
            'firewallManagementEnabled' => $firewallManagementEnabled,
        ]);
    }

    /**
     * Return the node configuration page for a specific node.
     */
    public function configuration(Request $request, Node $node): View
    {
        $liveNodeStatsEnabled = $this->isLiveNodeStatsEnabled();
        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        return $this->view->make('admin.nodes.view.configuration', [
            'node'                 => $node,
            'liveNodeStatsEnabled' => $liveNodeStatsEnabled,
            'firewallManagementEnabled' => $firewallManagementEnabled,
        ]);
    }

    /**
     * Return the node allocation management page.
     */
    public function allocations(Request $request, Node $node): View
    {
        $node = $this->repository->loadNodeAllocations($node);
        $liveNodeStatsEnabled = $this->isLiveNodeStatsEnabled();
        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        $this->plainInject(['node' => Collection::wrap($node)->only(['id'])]);

        return $this->view->make('admin.nodes.view.allocation', [
            'node'                 => $node,
            'liveNodeStatsEnabled' => $liveNodeStatsEnabled,
            'firewallManagementEnabled' => $firewallManagementEnabled,
            'allocations'          => Allocation::query()->where('node_id', $node->id)
                ->groupBy('ip')
                ->orderByRaw('INET_ATON(ip) ASC')
                ->get(['ip']),
        ]);
    }

    /**
     * Return a listing of servers that exist for this specific node.
     */
    public function servers(Request $request, Node $node): View
    {
        $liveNodeStatsEnabled = $this->isLiveNodeStatsEnabled();
        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        $this->plainInject([
            'node' => Collection::wrap($node->makeVisible(['daemon_token_id', 'daemon_token']))
                ->only(['scheme', 'fqdn', 'daemonListen', 'daemon_token_id', 'daemon_token']),
        ]);

        // All nodes for the transfer-to-node dropdown
        $nodes = Node::query()->orderBy('name')->get();

        // Determine which nodes have a Wings Agent endpoint configured
        $agentNodeIds = [];
        try {
            $raw       = $this->settingsRepository->get(self::ADDON_SETTINGS_KEY, '{}');
            $data      = json_decode($raw, true) ?: [];
            $endpoints = $data['addons']['wings-addon']['node_endpoints'] ?? [];
            foreach ($endpoints as $ep) {
                $nid = (int) ($ep['node_id'] ?? 0);
                if ($nid > 0) {
                    $agentNodeIds[] = $nid;
                }
            }
        } catch (Throwable) {}

        $servers = QueryBuilder::for(
                Server::query()
                    ->leftJoin('nodes', 'servers.node_id', '=', 'nodes.id')
                    ->leftJoin('users', 'users.id', '=', 'servers.owner_id')
                    ->select('servers.*')
                    ->where('servers.node_id', $node->id)
                    ->with(['node', 'user', 'allocation', 'subSplit', 'splits', 'nest', 'egg'])
            )
            ->allowedFilters([
                AllowedFilter::callback('status', function (Builder $query, $value) {
                    if ($value === 'active') {
                        $query->whereNull('servers.status');
                    } elseif ($value === 'suspended') {
                        $query->where('servers.status', 'suspended');
                    }
                }),
                AllowedFilter::exact('exp_date'),
                AllowedFilter::custom('*', new AdminServerFilter()),
            ])
            ->allowedSorts([
                'name',
                'uuid',
                AllowedSort::field('owner', 'users.username'),
                'status',
                'exp_date',
            ])
            ->paginate(config()->get('pterodactyl.paginate.admin.servers'));

        return $this->view->make('admin.nodes.view.servers', [
            'node'                      => $node,
            'liveNodeStatsEnabled'      => $liveNodeStatsEnabled,
            'firewallManagementEnabled' => $firewallManagementEnabled,
            'servers'                   => $servers,
            'nodes'                     => $nodes,
            'agentNodeIds'              => $agentNodeIds,
        ]);
    }

    /**
     * Return the Wings Agent live statistics page for a node.
     * Aborts with 404 if live node stats are disabled.
     */
    public function wingsStats(Request $request, Node $node): View
    {
        if (!$this->isLiveNodeStatsEnabled()) {
            abort(404);
        }

        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        return $this->view->make('admin.nodes.view.wings-stats', [
            'node'                 => $node,
            'liveNodeStatsEnabled' => true,
            'firewallManagementEnabled' => $firewallManagementEnabled,
        ]);
    }

    /**
     * Return the Wings Agent port detail page.
     * Aborts with 404 if live node stats are disabled.
     */
    public function wingsPortDetail(Request $request, Node $node, int $port): View
    {
        if (!$this->isLiveNodeStatsEnabled()) {
            abort(404);
        }

        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        $selectedProtocol = strtolower(trim((string) $request->query('protocol', '')));
        if (!in_array($selectedProtocol, ['tcp', 'tcp6', 'udp', 'udp6'], true)) {
            $selectedProtocol = null;
        }

        return $this->view->make('admin.nodes.view.wings-port-detail', [
            'node'                 => $node,
            'port'                 => $port,
            'selectedProtocol'     => $selectedProtocol,
            'liveNodeStatsEnabled' => true,
            'firewallManagementEnabled' => $firewallManagementEnabled,
        ]);
    }

        /**
         * Return the Firewall management page for a node.
         * Aborts with 404 if live node stats (Wings Agent) are disabled.
         */
    public function firewall(Request $request, Node $node): View
    {
        if (!$this->isFirewallManagementEnabled()) {
            abort(404);
        }

        return $this->view->make('admin.nodes.view.firewall', [
            'node'                 => $node,
            'liveNodeStatsEnabled' => true,
            'firewallManagementEnabled' => true,
        ]);
    }

    /**
     * Return the Node Logs page.
     */
    public function nodeLogs(Request $request, Node $node): View
    {
        $liveNodeStatsEnabled = $this->isLiveNodeStatsEnabled();
        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        return $this->view->make('admin.nodes.view.logs', [
            'node'                     => $node,
            'liveNodeStatsEnabled'     => $liveNodeStatsEnabled,
            'firewallManagementEnabled' => $firewallManagementEnabled,
        ]);
    }

    /**
     * Return the Node Backups management page.
     */
    public function backups(Request $request, Node $node): View
    {
        $liveNodeStatsEnabled = $this->isLiveNodeStatsEnabled();
        $firewallManagementEnabled = $this->isFirewallManagementEnabled();

        $config = NodeBackupConfig::where('node_id', $node->id)->first();

        // Decrypt the discord webhook URL so the form shows the plain URL, not ciphertext
        if ($config && !empty($config->discord_webhook_url)) {
            try {
                $config->discord_webhook_url = \Illuminate\Support\Facades\Crypt::decryptString($config->discord_webhook_url);
            } catch (\Exception) {
                // Already plain text (or invalid) — leave as-is
            }
        }

        return $this->view->make('admin.nodes.view.backups', [
            'node' => $node,
            'backupConfig' => $config,
            'liveNodeStatsEnabled' => $liveNodeStatsEnabled,
            'firewallManagementEnabled' => $firewallManagementEnabled,
        ]);
    }
}
