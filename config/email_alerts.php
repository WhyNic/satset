<?php

return [
    // Kill switch: set EMAIL_ALERTS_ENABLED=false to stop sending without affecting the queue.
    'enabled' => (bool) env('EMAIL_ALERTS_ENABLED', true),

    // Active tickets (waiting or called) one email may hold at the same outlet.
    'max_active_per_email' => (int) env('EMAIL_ALERTS_MAX_ACTIVE_PER_EMAIL', 3),
];
