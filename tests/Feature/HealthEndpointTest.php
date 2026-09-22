<?php

test('responde no endpoint de saúde', function () {
    $this->get('/up')->assertOk();
});
