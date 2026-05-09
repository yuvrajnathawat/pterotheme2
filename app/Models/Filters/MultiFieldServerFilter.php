<?php
namespace Pterodactyl\Models\Filters;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\Filters\Filter;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
class MultiFieldServerFilter implements Filter
{
    private const IPV4_REGEX = '/^(?:[0-9]{1,3}\.){0,3}[0-9]{1,3}(\:\d{1,5})?$/';
    public function __invoke(Builder $query, $value, string $property)
    {
        if ($query->getQuery()->from !== 'servers') {
            throw new BadMethodCallException('Cannot use the MultiFieldServerFilter against a non-server model.');
        }
        if (preg_match(self::IPV4_REGEX, $value) || preg_match('/^:\d{1,5}$/', $value)) {
            $query
                ->select('servers.*')
                ->join('allocations', 'allocations.server_id', '=', 'servers.id')
                ->where(function (Builder $builder) use ($value) {
                    $parts = explode(':', $value);
                    $builder->when(
                        !Str::startsWith($value, ':'),
                        function (Builder $builder) use ($parts) {
                            $builder->orWhere('allocations.ip', 'LIKE', "{$parts[0]}%");
                            if (!is_null($parts[1] ?? null)) {
                                $builder->where('allocations.port', 'LIKE', "{$parts[1]}%");
                            }
                        },
                        function (Builder $builder) use ($value) {
                            $builder->orWhere('allocations.port', 'LIKE', substr($value, 1) . '%');
                        }
                    );
                })
                ->groupBy('servers.id');
            return;
        }
        $queryBuilder = $query->getQuery();
        $joins = $queryBuilder->joins ?? [];

        $hasUsersJoin = false;
        foreach ($joins as $join) {
            if ($join->table === 'users') {
                $hasUsersJoin = true;
                break;
            }
        }

        $query->select('servers.*');

        if (! $hasUsersJoin) {
            $query->leftJoin('users', 'servers.owner_id', '=', 'users.id');
        }

        $query->where(function (Builder $builder) use ($value) {
            $builder->where('servers.uuid', $value)
                ->orWhere('servers.uuid', 'LIKE', "$value%")
                ->orWhere('servers.uuidShort', $value)
                ->orWhere('servers.external_id', $value)
                ->orWhereRaw('LOWER(servers.name) LIKE ?', ["%$value%"])
                ->orWhereRaw('LOWER(users.username) LIKE ?', ["%$value%"])
                ->orWhereRaw('LOWER(users.email) LIKE ?', ["%$value%"])
                ->orWhere('servers.exp_date', 'LIKE', "%$value%");

                // Flexible date matching for exp_date
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $value, $matches)) {
                    $part1 = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $part2 = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $year = strlen($matches[3]) === 2 ? '20' . $matches[3] : $matches[3];
                    $builder->orWhere('servers.exp_date', 'LIKE', "$year-$part1-$part2%");
                    $builder->orWhere('servers.exp_date', 'LIKE', "$year-$part2-$part1%");
                }
                
                $months = [
                    'january' => '01', 'jan' => '01', 'february' => '02', 'feb' => '02',
                    'march' => '03', 'mar' => '03', 'april' => '04', 'apr' => '04',
                    'may' => '05', 'june' => '06', 'jun' => '06', 'july' => '07', 'jul' => '07',
                    'august' => '08', 'aug' => '08', 'september' => '09', 'sep' => '09',
                    'october' => '10', 'oct' => '10', 'november' => '11', 'nov' => '11',
                    'december' => '12', 'dec' => '12',
                ];
                $lowVal = strtolower(trim($value));
                if (isset($months[$lowVal])) {
                    $builder->orWhere('servers.exp_date', 'LIKE', "%-{$months[$lowVal]}-%");
                }
                
                if (preg_match('/^(20\d{2})$/', $value, $matches)) {
                    $builder->orWhere('servers.exp_date', 'LIKE', "{$matches[1]}-%");
                }
            })
            ->groupBy('servers.id');
    }
}
