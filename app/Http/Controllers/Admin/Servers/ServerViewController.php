<?php
namespace Pterodactyl\Http\Controllers\Admin\Servers;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Servers\EnvironmentService;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pterodactyl\Repositories\Eloquent\NestRepository;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Eloquent\MountRepository;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Traits\Controllers\JavascriptInjection;
use Pterodactyl\Repositories\Eloquent\LocationRepository;
use Pterodactyl\Repositories\Eloquent\DatabaseHostRepository;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Illuminate\Support\Facades\DB;
use JavaScript;
use Pterodactyl\Models\RolexDev\ServerSplitterWhitelist;
use Pterodactyl\Models\RolexDev\ReverseProxyWhitelist;

class ServerViewController extends Controller
{
    use JavascriptInjection;
    /**
     * ServerViewController constructor.
     */
    public function __construct(
        private DatabaseHostRepository $databaseHostRepository,
        private LocationRepository $locationRepository,
        private MountRepository $mountRepository,
        private NestRepository $nestRepository,
        private NodeRepository $nodeRepository,
        private ServerRepository $repository,
        private EnvironmentService $environmentService,
        private ViewFactory $view
    ) {
    }

    /**
     * Returns the index view for a server.
     */
    public function index(Request $request, Server $server): View
    {
        return $this->view->make('admin.servers.view.index', compact('server'));
    }

    /**
     * Returns the server details page.
     */
    public function details(Request $request, Server $server): View
    {
        $settingsRaw = app(SettingsRepository::class)->get('settings::app:addons:hyperv1', '{}');
        $settings = json_decode($settingsRaw, true);
        $billingEnabled = $settings['addons']['billing']['enabled'] ?? false;
        $billingCategories = [];
        $billingGames = [];
        if ($billingEnabled) {
            $billingCategories = DB::table('game_category')->get();
            $billingGames = DB::table('games')
                ->join('game_category', 'games.category_id', '=', 'game_category.id')
                ->select('games.*', 'game_category.title as category_name')
                ->get();
        }

        return $this->view->make('admin.servers.view.details', compact('server', 'billingCategories', 'billingGames', 'billingEnabled'));
    }

    /**
     * Returns a view of server build settings.
     */
    public function build(Request $request, Server $server): View
    {
        $allocations = $server->node->allocations->toBase();

        // Load addon settings to determine visibility
        $settingsRaw = app(SettingsRepository::class)->get('settings::app:addons:hyperv1', '{}');
        $addonSettings = json_decode($settingsRaw, true);
        $addonSplitterEnabled = $addonSettings['addons']['server-splitter']['enabled'] ?? false;
        $addonProxyEnabled    = $addonSettings['addons']['ReverseProxy']['enabled'] ?? false;
        $addonServerTypeChangerEnabled = $addonSettings['addons']['server-type-changer']['enabled'] ?? false;

        // Load existing per-server limits
        $splitterRow   = ServerSplitterWhitelist::where('server_id', $server->id)->first();
        $proxyRow      = ReverseProxyWhitelist::where('server_id', $server->id)->first();
        $splitterLimit = $splitterRow ? $splitterRow->split_limit : 0;
        $proxyLimit    = $proxyRow    ? $proxyRow->proxy_limit    : 0;

        return $this->view->make('admin.servers.view.build', [
            'server'               => $server,
            'assigned'             => $allocations->where('server_id', $server->id)->sortBy('port')->sortBy('ip'),
            'unassigned'           => $allocations->where('server_id', null)->sortBy('port')->sortBy('ip'),
            'addonSplitterEnabled' => $addonSplitterEnabled,
            'addonProxyEnabled'    => $addonProxyEnabled,
            'addonServerTypeChangerEnabled' => $addonServerTypeChangerEnabled,
            'splitterLimit'        => $splitterLimit,
            'proxyLimit'           => $proxyLimit,
        ]);
    }

    /**
     * Returns the server startup management page.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function startup(Request $request, Server $server): View
    {
        $nests = $this->nestRepository->getWithEggs();
        $variables = $this->environmentService->handle($server);

        $this->plainInject([
            'server' => $server,
            'server_variables' => $variables,
            'nests' => $nests->map(function (Nest $item) {
                return array_merge($item->toArray(), [
                    'eggs' => $item->eggs->keyBy('id')->toArray(),
                ]);
            })->keyBy('id'),
        ]);

        return $this->view->make('admin.servers.view.startup', compact('server', 'nests'));
    }

    /**
     * Returns all the databases that exist for the server.
     */
    public function database(Request $request, Server $server): View
    {
        return $this->view->make('admin.servers.view.database', [
            'hosts' => $this->databaseHostRepository->all(),
            'server' => $server,
        ]);
    }

    /**
     * Returns all the mounts that exist for the server.
     */
    public function mounts(Request $request, Server $server): View
    {
        $server->load('mounts');

        return $this->view->make('admin.servers.view.mounts', [
            'mounts' => $this->mountRepository->getMountListForServer($server),
            'server' => $server,
        ]);
    }

    /**
     * Returns the base server management page, or an exception if the server
     * is in a state that cannot be recovered from.
     *
     * @throws DisplayException
     */
    public function manage(Request $request, Server $server): View
    {
        if ($server->status === Server::STATUS_INSTALL_FAILED) {
            throw new DisplayException('This server is in a failed install state and cannot be recovered. Please delete and re-create the server.');
        }

        $nodes = $this->nodeRepository->all();
        $canTransfer = false;
        if (count($nodes) >= 2) {
            $canTransfer = true;
        }

        JavaScript::put([
            'nodeData' => $this->nodeRepository->getNodesForServerCreation(),
        ]);

        // load locations with nodes and count of servers so the transfer modal
        // can disable and label options correctly.
        $locations = $this->locationRepository->getBuilder()
            ->with(['nodes' => function ($q) {
                $q->withCount('servers');
            }])
            ->get();

        // Collect node IDs that have a Wings agent endpoint configured.
        $agentNodeIds = [];
        try {
            $settingsRaw = app(SettingsRepository::class)->get('settings::app:addons:hyperv1', '{}');
            $addonSettings = json_decode($settingsRaw, true) ?: [];
            foreach ($addonSettings['addons']['wings-addon']['node_endpoints'] ?? [] as $ep) {
                if (!empty($ep['node_id'])) {
                    $agentNodeIds[] = (int) $ep['node_id'];
                }
            }
        } catch (\Throwable) {}

        $agentTransfer = $server->agentTransfer()->with(['sourceNode', 'destNode'])->first();

        return $this->view->make('admin.servers.view.manage', [
            'server' => $server,
            'locations' => $locations,
            'canTransfer' => $canTransfer,
            'agentNodeIds' => $agentNodeIds,
            'agentTransfer' => $agentTransfer,
        ]);
    }

    /**
     * Returns the server deletion page.
     */
    public function delete(Request $request, Server $server): View
    {
        return $this->view->make('admin.servers.view.delete', compact('server'));
    }
}
