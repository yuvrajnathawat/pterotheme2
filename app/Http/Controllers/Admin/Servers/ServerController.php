<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Filters\AdminServerFilter;
use Pterodactyl\Models\Node;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Throwable;

class ServerController extends Controller
{
    private const ADDON_SETTINGS_KEY = 'settings::app:addons:hyperv1';

    public function __construct(
        private ViewFactory $view,
        private SettingsRepository $settingsRepository,
    ) {}

    /**
     * Returns all the servers that exist on the system using a paginated result set. If
     * a query is passed along in the request it is also passed to the repository function.
     */
    public function index(Request $request): View
    {
        // pull nodes for the filter dropdown
        $nodes = Node::query()->orderBy('name')->get();

        // Determine which node IDs have a Wings Agent endpoint configured
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
                    ->with('node', 'user', 'allocation')
            )
            ->allowedFilters([
                AllowedFilter::exact('owner_id'),
                AllowedFilter::exact('node_id'),
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
                AllowedSort::field('node', 'nodes.name'),
                'status',
                'exp_date',
            ])
            ->paginate(config()->get('pterodactyl.paginate.admin.servers'));

        return $this->view->make('admin.servers.index', [
            'servers'      => $servers,
            'nodes'        => $nodes,
            'agentNodeIds' => $agentNodeIds,
        ]);
    }
}
