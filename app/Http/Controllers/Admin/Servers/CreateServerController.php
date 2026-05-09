<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Location;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Eloquent\NestRepository;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Http\Requests\Admin\ServerFormRequest;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Models\RolexDev\ServerSplitterWhitelist;
use Pterodactyl\Models\RolexDev\ReverseProxyWhitelist;

class CreateServerController extends Controller
{
    /**
     * CreateServerController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private NestRepository $nestRepository,
        private NodeRepository $nodeRepository,
        private ServerCreationService $creationService,
        private ViewFactory $view
    ) {
    }

    /**
     * Displays the create server page.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function index(): View|RedirectResponse
    {
        $nodes = Node::all();
        if (count($nodes) < 1) {
            $this->alert->warning(trans('admin/server.alerts.node_required'))->flash();

            return redirect()->route('admin.nodes');
        }

        // we want to know, in the blade, when a node has hit its limit so the
        // dropdown option can be disabled. eager load the server count here so
        // we don't execute an extra query for each node in the view.
        $locations = Location::query()
            ->with(['nodes' => function ($q) {
                $q->withCount('servers');
            }])
            ->get();

        $nests = $this->nestRepository->getWithEggs();

        \JavaScript::put([
            'nodeData' => $this->nodeRepository->getNodesForServerCreation(),
            'nests' => $nests->map(function ($item) {
                return array_merge($item->toArray(), [
                    'eggs' => $item->eggs->keyBy('id')->toArray(),
                ]);
            })->keyBy('id'),
        ]);

        // Determine addon states for conditional rendering
        $settingsRaw = app(SettingsRepository::class)->get('settings::app:addons:hyperv1', '{}');
        $addonSettings = json_decode($settingsRaw, true);
        $addonSplitterEnabled = $addonSettings['addons']['server-splitter']['enabled'] ?? false;
        $addonProxyEnabled    = $addonSettings['addons']['ReverseProxy']['enabled'] ?? false;

        return $this->view->make('admin.servers.new', [
            'locations'            => $locations,
            'nests'                => $nests,
            'addonSplitterEnabled' => $addonSplitterEnabled,
            'addonProxyEnabled'    => $addonProxyEnabled,
        ]);
    }

    /**
     * Create a new server on the remote system.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException
     * @throws \Throwable
     */
    public function store(ServerFormRequest $request): RedirectResponse
    {
        $data = $request->except(['_token']);
        if (!empty($data['custom_image'])) {
            $data['image'] = $data['custom_image'];
            unset($data['custom_image']);
        }

        $server = $this->creationService->handle($data);

        // Save Server Splitter whitelist limit if provided
        $splitLimit = (int) $request->input('split_limit', 0);
        if ($splitLimit > 0) {
            ServerSplitterWhitelist::updateOrCreate(
                ['server_id' => $server->id],
                ['split_limit' => $splitLimit]
            );
        }

        // Save Reverse Proxy whitelist limit if provided
        $proxyLimit = (int) $request->input('proxy_limit', 0);
        if ($proxyLimit > 0) {
            ReverseProxyWhitelist::updateOrCreate(
                ['server_id' => $server->id],
                ['proxy_limit' => $proxyLimit]
            );
        }

        $this->alert->success(trans('admin/server.alerts.server_created'))->flash();

        return new RedirectResponse('/admin/servers/view/' . $server->id);
    }
}
