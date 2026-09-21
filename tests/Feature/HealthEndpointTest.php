<?php

it('answers the health endpoint', function () {
    $this->get('/up')->assertOk();
});
