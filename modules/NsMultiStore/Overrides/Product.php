<?php

namespace Modules\NsMultiStore\Overrides;

use Modules\NsMultiStore\Contracts\GlobalProductScoped;

/**
 * 让业务代码优先拿到模块版本的 Product（实现 GlobalProductScoped）
 * 通过模块 ServiceProvider 的 resolveRelationUsing 或容器绑定注入
 */
class Product extends \App\Models\Product implements GlobalProductScoped
{
    public function isGlobalPool(): bool
    {
        return is_null( $this->store_id );
    }
}