<?php

namespace Modules\NsMultiStore\Services;

use App\Exceptions\NotAllowedException;
use App\Models\Customer;
use App\Models\CustomerAccountHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Member related business rules.
 *
 * Members are globally shared across stores (no store isolation), the opening
 * store ("owner_store_id") is only kept for statistics and performance
 * attribution.
 */
class MemberService
{
    public function __construct( protected StoreService $storeService )
    {
        // ...
    }

    /**
     * Runs before a member is created: assign the opening store and enforce
     * the global phone uniqueness.
     */
    public function assertCanCreate( Customer $customer ): void
    {
        if ( empty( $customer->owner_store_id ) ) {
            $customer->owner_store_id = $this->storeService->getCurrentStoreId();
        }

        $this->assertPhoneAvailable( $customer->phone, null );
    }

    /**
     * Runs before a member is updated: enforce the phone uniqueness and the
     * phone edit permission (headquarter or the opening store only).
     */
    public function assertCanUpdate( Customer $customer ): void
    {
        if ( ! $customer->isDirty( 'phone' ) ) {
            return;
        }

        $this->assertPhoneAvailable( $customer->phone, $customer->id );

        $user = Auth::user();

        /**
         * Console commands and queued jobs don't carry a session context,
         * they are considered trusted.
         */
        if ( ! $user instanceof User ) {
            return;
        }

        if ( $this->storeService->hasAccessToAllStores( $user ) ) {
            return;
        }

        $storeId = $this->storeService->getCurrentStoreId();

        if ( $storeId !== null && (int) $customer->owner_store_id === (int) $storeId ) {
            return;
        }

        throw new NotAllowedException( __m( '仅总部或开卡门店可修改手机号', 'NsMultiStore' ) );
    }

    /**
     * Runs before a wallet history is created: record the store that performed
     * the operation and validate refunds.
     */
    public function prepareHistory( CustomerAccountHistory $history ): void
    {
        if ( empty( $history->store_id ) ) {
            $history->store_id = $this->storeService->getCurrentStoreId();
        }

        if ( $history->operation === CustomerAccountHistory::OPERATION_REFUND ) {
            $this->assertRefundAllowed( $history );
        }
    }

    /**
     * A phone number must be globally unique across members.
     */
    protected function assertPhoneAvailable( ?string $phone, ?int $ignoreId ): void
    {
        if ( empty( $phone ) ) {
            return;
        }

        $query = Customer::where( 'phone', trim( $phone ) );

        if ( $ignoreId !== null ) {
            $query->where( 'id', '!=', $ignoreId );
        }

        if ( $query->exists() ) {
            throw new NotAllowedException( __m( '该手机号已被使用，请直接关联已有会员', 'NsMultiStore' ) );
        }
    }

    /**
     * A store can only refund the amount it credited itself.
     */
    protected function assertRefundAllowed( CustomerAccountHistory $history ): void
    {
        $refundStoreId = (int) ( $history->store_id ?? $this->storeService->getCurrentStoreId() );

        $lastCredit = CustomerAccountHistory::where( 'customer_id', $history->customer_id )
            ->where( 'operation', CustomerAccountHistory::OPERATION_ADD )
            ->whereNotNull( 'store_id' )
            ->orderByDesc( 'id' )
            ->first();

        if ( $lastCredit && (int) $lastCredit->store_id !== $refundStoreId ) {
            throw new NotAllowedException( __m( '只能退款本店铺充值的金额', 'NsMultiStore' ) );
        }
    }
}
