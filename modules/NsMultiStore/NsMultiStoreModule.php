<?php
namespace Modules\NsMultiStore;

use Illuminate\Support\Facades\Event;
use App\Services\Module;

class NsMultiStoreModule extends Module
{
    public function __construct()
    {
        parent::__construct( __FILE__ );
    }
}