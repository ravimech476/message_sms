<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetPracticeRequest;
use App\Http\Resources\PracticeResource;
use App\Models\Practice;
use Illuminate\Database\Eloquent\Builder;

class PracticesController extends Controller
{
    /**
     * Get practices.
     *
     * @param GetPracticeRequest $practiceRequest
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function __invoke(GetPracticeRequest $practiceRequest)
    {
        $practices = Practice::with([
            'domain',
            'domain.providers',
            'domain.providers.credentials',
        ]);
        $practices = $this->applyFilters($practices, $practiceRequest)
                        ->orderBy('practice_name')
                        ->paginate($practiceRequest->get('perPage', 20));

        return PracticeResource::collection($practices);
    }

    /**
     * Handle filters.
     *
     * @param Builder $query
     * @param GetPracticeRequest $request
     * @return Builder
     */
    protected function applyFilters(Builder $query, GetPracticeRequest $request): Builder
    {
        if (filter_var($request->get('hasDomain'), FILTER_VALIDATE_BOOLEAN) === true) {
            $query->has('domain');
        }

        if ($request->has('provider')) {
            $provider = $request->get('provider');

            if ($provider === 'none') {
                $query->whereDoesntHave('domain.providers', function (Builder $query) use ($provider) {
                    $query->where('is_default', 1);
                });
            } elseif ($provider !== null) {
                $query->whereHas('domain.providers', function (Builder $query) use ($provider) {
                    $query->where('provider', $provider);
                    $query->where('is_default', 1);
                });
            }
        }

        if ($request->get('stp')) {
            $query->whereSTP($request->get('stp'));
        }

        if ($request->get('ccg')) {
            $query->whereCCG($request->get('ccg'));
        }

        if ($request->get('name')) {
            $query->where('practice_name', 'like', '%'. $request->get('name') . '%')
                ->orWhere('ods_code', $request->get('name'));
        }

        return $query;
    }

}
