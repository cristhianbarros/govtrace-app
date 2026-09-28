<?php

it('responds 200 on the health endpoint', function () {
    $this->get('/up')->assertOk();
});
