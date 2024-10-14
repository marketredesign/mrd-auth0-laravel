<?php

namespace Marketredesign\MrdAuth0Laravel\Facades;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Marketredesign\MrdAuth0Laravel\Contracts\DatasetRepository;
use Marketredesign\MrdAuth0Laravel\Repository\Fakes\FakeDatasetRepository;

/**
 * @method static Collection getUserDatasetIds(bool $managedOnly = false, bool $cached = true, string $guard = null)
 * @method static ResourceCollection getUserDatasets(bool $mngOnly = false, bool $cached = true, string $guard = null)
 * @method static int fakeCount(bool $managedOnly = false)
 * @method static void fakeClear()
 * @method static void fakeAddDatasets(Collection $ids, bool $isManager = false)
 *
 * @see DatasetRepository
 * @see FakeDatasetRepository
 */
class Datasets extends Facade
{
    public static function fake(): void
    {
        self::$app->singleton(DatasetRepository::class, FakeDatasetRepository::class);
    }

    /**
     * @inheritDocs
     */
    protected static function getFacadeAccessor(): string
    {
        return DatasetRepository::class;
    }
}
