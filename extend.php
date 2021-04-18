<?php

use ArchLinux\RedirectLL\Middleware\LLRedirect;
use Flarum\Extend;
use Flarum\Http\Middleware\ResolveRoute;

return [
    (new Extend\Middleware('forum'))
        ->insertBefore(ResolveRoute::class, LLRedirect::class)
];
