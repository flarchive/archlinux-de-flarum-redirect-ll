<?php

namespace ArchLinux\RedirectLL;

use ArchLinux\RedirectLL\Middleware\LLRedirect;
use Flarum\Extend;

return [
    (new Extend\Middleware('forum'))
        ->insertBefore('flarum.forum.route_resolver', LLRedirect::class)
];
