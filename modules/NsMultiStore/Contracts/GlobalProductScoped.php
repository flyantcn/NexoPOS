<?php

namespace Modules\NsMultiStore\Contracts;

/**
 * 标记模型为全局商品池，StoreScope 需放行 store_id IS NULL 的记录。
 */
interface GlobalProductScoped
{
    /**
     * 返回 true 表示该模型是全局商品池，StoreScope 需放行 store_id IS NULL
     */
    public function isGlobalPool(): bool;
}