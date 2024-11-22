<?php

namespace Wsmallnews\Express\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Wsmallnews\Express\Express
 */
class Express extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Wsmallnews\Express\Express::class;
    }
}
