<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Node;
use Pterodactyl\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\View\View;

class NodeController extends Controller
{
    
    public function __construct(private ViewFactory $view)
    {
    }

    /**
     * Returns a listing of nodes on the system.
     */
    public function index(Request $request): View
    {
        $nodes = QueryBuilder::for(
            Node::query()->with('location')->withCount('servers')
        )
            ->allowedFilters(['uuid', 'name'])
            ->allowedSorts(['sort', 'id'])
            ->defaultSort('sort')
            ->paginate(25);

        return $this->view->make('admin.nodes.index', ['nodes' => $nodes]);
    }

    /**
     * Updates the sort order of nodes based on user drag-and-drop.
     */
    public function reorder(Request $request): JsonResponse
    {
        if (!Auth::user()->root_admin && !Auth::user()->hasAdminPermission('admin.nodes.edit')) {
            abort(403);
        }

        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:nodes,id',
        ]);

        $order = array_values($data['order']);

        // Compute a new global ordering while preserving nodes outside the reorder subset.
        $allIds = Node::orderBy('sort')->orderBy('id')->pluck('id')->toArray();
        $subset = array_intersect($allIds, $order);
        if (count($subset) === 0) {
            return response()->json(['success' => true]);
        }

        // Place the reordered nodes at the position of the first node in the subset.
        $insertIndex = array_search($subset[0], $allIds, true);
        $remaining = array_values(array_diff($allIds, $subset));
        array_splice($remaining, $insertIndex, 0, $order);

        DB::transaction(function () use ($remaining) {
            foreach ($remaining as $index => $id) {
                DB::table('nodes')->where('id', $id)->update(['sort' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }
}
