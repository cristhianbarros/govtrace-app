<?php

return [
    // make demo: how long to wait for the worker to seal the demo reports (one per ledger) before publishing.
    'seal_wait_seconds' => (int) env('DEMO_SEAL_WAIT_SECONDS', 180),
];
