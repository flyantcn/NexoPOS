<?php

namespace Modules\NsMultiStore\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Modules\NsMultiStore\Models\Store;
use Modules\NsMultiStore\Models\UserStoreRelation;
use Modules\NsMultiStore\Services\StoreService;
use Tests\TestCase;

class StoreVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call( 'modules:migrate', [ 'moduleNamespace' => 'NsMultiStore' ] );

        ns()->registerGatePermissions();

        app( StoreService::class )->defineCurrentStore( null );

        UserStoreRelation::query()->delete();
        Store::query()->delete();
    }

    private function makeStore( string $type, ?int $parentId = null ): Store
    {
        return Store::factory()->create( [
            'type' => $type,
            'parent_id' => $parentId,
            'status' => Store::STATUS_ACTIVE,
        ] );
    }

    /**
     * A single store only sees itself.
     */
    public function test_a_single_store_only_sees_itself(): void
    {
        $store = $this->makeStore( Store::TYPE_SINGLE );
        $this->makeStore( Store::TYPE_CHAIN );

        $ids = app( StoreService::class )->computeVisibleStoreIds( $store );

        $this->assertSame( [ $store->id ], $ids );
    }

    /**
     * A franchise store is strongly isolated, even from its siblings.
     */
    public function test_a_franchise_store_is_strongly_isolated(): void
    {
        $headquarter = $this->makeStore( Store::TYPE_SINGLE );

        $franchise = $this->makeStore( Store::TYPE_FRANCHISE, $headquarter->id );
        $this->makeStore( Store::TYPE_FRANCHISE, $headquarter->id );

        $ids = app( StoreService::class )->computeVisibleStoreIds( $franchise );

        $this->assertSame( [ $franchise->id ], $ids );
    }

    /**
     * A chain store sees the other chain stores of its brand (cross-store
     * transfer) but not the stores of another brand.
     */
    public function test_a_chain_store_sees_the_other_chain_stores_of_its_brand(): void
    {
        $headquarter = $this->makeStore( Store::TYPE_CHAIN );

        $chain = $this->makeStore( Store::TYPE_CHAIN, $headquarter->id );
        $sibling = $this->makeStore( Store::TYPE_CHAIN, $headquarter->id );

        $otherHeadquarter = $this->makeStore( Store::TYPE_CHAIN );
        $this->makeStore( Store::TYPE_CHAIN, $otherHeadquarter->id );

        $ids = app( StoreService::class )->computeVisibleStoreIds( $chain );

        $this->assertEqualsCanonicalizing( [ $chain->id, $sibling->id ], $ids );
    }

    /**
     * A chain headquarter sees its chain stores in real time, but a franchise
     * store is kept isolated (the headquarter only receives aggregated
     * reports).
     */
    public function test_a_chain_headquarter_sees_chain_stores_but_not_franchises(): void
    {
        $headquarter = $this->makeStore( Store::TYPE_CHAIN );

        $chainChild = $this->makeStore( Store::TYPE_CHAIN, $headquarter->id );
        $franchiseChild = $this->makeStore( Store::TYPE_FRANCHISE, $headquarter->id );

        $ids = app( StoreService::class )->computeVisibleStoreIds( $headquarter );

        $this->assertEqualsCanonicalizing( [ $headquarter->id, $chainChild->id ], $ids );
        $this->assertNotContains( $franchiseChild->id, $ids );
    }
}
